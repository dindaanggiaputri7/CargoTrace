<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingPayment;
use App\Models\BookingRequest;
use App\Models\BookingRequestHistory;
use App\Services\AuditService;
use App\Services\BookingCommunicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingPaymentController extends Controller
{
    public function demoConfirm(Request $request, BookingPayment $payment, BookingCommunicationService $communication, AuditService $audit)
    {
        // The demo payment simulator is deliberately unavailable in production.
        abort_unless(app()->environment(['local', 'testing']) && config('cargotrace.allow_demo_payments'), 404);
        abort_unless($request->user()->isCustomer() && (int) $payment->customer_id === (int) $request->user()->id, 404);

        $booking = DB::transaction(function () use ($payment, $request, $communication, $audit) {
            $lockedPayment = BookingPayment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($lockedPayment->provider !== 'mock') {
                throw ValidationException::withMessages(['payment' => ['Payment demo hanya berlaku untuk transaksi simulasi.']]);
            }
            if ($lockedPayment->status === 'PAID') return BookingRequest::findOrFail($lockedPayment->booking_request_id);
            if ($lockedPayment->status !== 'PENDING' || ($lockedPayment->expires_at && $lockedPayment->expires_at->isPast())) {
                throw ValidationException::withMessages(['payment' => ['Pembayaran tidak lagi dapat dikonfirmasi.']]);
            }

            $lockedPayment->update([
                'status' => 'PAID',
                'paid_at' => now(),
                'provider_data' => array_merge($lockedPayment->provider_data ?? [], ['confirmed_in_demo' => true, 'confirmed_at' => now()->toIso8601String()]),
            ]);
            $booking = BookingRequest::query()->lockForUpdate()->findOrFail($lockedPayment->booking_request_id);
            $from = $booking->status;
            if ($from !== 'PENDING_PAYMENT') {
                throw ValidationException::withMessages(['booking' => ['Status permintaan tidak sesuai untuk konfirmasi pembayaran.']]);
            }
            $booking->update(['status' => 'READY_FOR_CONVERSION']);
            BookingRequestHistory::create([
                'booking_request_id' => $booking->id,
                'actor_id' => $request->user()->id,
                'from_status' => $from,
                'to_status' => 'READY_FOR_CONVERSION',
                'note' => 'Pembayaran demo dikonfirmasi; tidak ada dana sungguhan yang diproses.',
                'created_at' => now(),
            ]);
            $audit->record($request->user(), 'BOOKING_DEMO_PAYMENT_CONFIRMED', 'BookingPayment', $lockedPayment->id,
                'Pembayaran demo untuk '.$booking->request_number.' dikonfirmasi.', ['status' => 'PENDING'], ['status' => 'PAID']);
            $communication->notify($booking->customer, 'PAYMENT_PAID', 'Pembayaran tercatat (DEMO)',
                'Pembayaran simulasi untuk '.$booking->request_number.' berhasil. Tidak ada uang sungguhan yang ditagihkan.', $booking);
            $communication->notifyOperators('BOOKING_READY_FOR_CONVERSION', 'Booking siap dikonversi',
                'Pembayaran demo untuk '.$booking->request_number.' tercatat; operator dapat membuat shipment.', $booking);
            return $booking;
        });

        return response()->json(['message' => 'Pembayaran DEMO dikonfirmasi. Tidak ada dana sungguhan yang diproses.', 'data' => $booking->load('latestQuotation', 'latestPayment')]);
    }
    /**
     * Midtrans server-to-server notification. Signature is verified before any state change.
     * Configure the notification URL as /api/integrations/payments/midtrans/notification.
     */
    public function midtransNotification(Request $request, BookingCommunicationService $communication, AuditService $audit)
    {
        $data = $request->validate([
            'order_id' => ['required','string','max:100'],
            'status_code' => ['required','string','max:10'],
            'gross_amount' => ['required','string','max:30'],
            'signature_key' => ['required','string','size:128'],
            'transaction_status' => ['required','string','max:30'],
            'fraud_status' => ['nullable','string','max:30'],
        ]);
        $serverKey = (string) config('services.midtrans.server_key');
        abort_unless($serverKey !== '', 503, 'Payment provider is not configured.');
        $expected = hash('sha512', $data['order_id'].$data['status_code'].$data['gross_amount'].$serverKey);
        abort_unless(hash_equals($expected, $data['signature_key']), 401, 'Invalid payment notification signature.');

        $payment = BookingPayment::where('provider', 'midtrans')
            ->where('provider_reference', $data['order_id'])->firstOrFail();

        DB::transaction(function () use ($payment, $data, $communication, $audit) {
            $locked = BookingPayment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($locked->status === 'PAID') return;
            if (number_format((float)$locked->amount, 2, '.', '') !== number_format((float)$data['gross_amount'], 2, '.', '')) {
                abort(422, 'Payment amount does not match the stored transaction.');
            }

            $transactionStatus = strtolower($data['transaction_status']);
            $fraudStatus = strtolower($data['fraud_status'] ?? '');
            $newStatus = 'PENDING';
            if ($transactionStatus === 'settlement' || ($transactionStatus === 'capture' && $fraudStatus === 'accept')) {
                $newStatus = 'PAID';
            } elseif (in_array($transactionStatus, ['deny','cancel'], true)) {
                $newStatus = 'FAILED';
            } elseif ($transactionStatus === 'expire') {
                $newStatus = 'EXPIRED';
            }

            $locked->update([
                'status' => $newStatus,
                'paid_at' => $newStatus === 'PAID' ? now() : $locked->paid_at,
                'provider_data' => array_merge($locked->provider_data ?? [], [
                    'last_notification_status' => $transactionStatus,
                    'fraud_status' => $fraudStatus ?: null,
                    'notification_received_at' => now()->toIso8601String(),
                ]),
            ]);

            $booking = BookingRequest::query()->lockForUpdate()->findOrFail($locked->booking_request_id);
            if ($newStatus === 'PAID' && $booking->status === 'PENDING_PAYMENT') {
                $booking->update(['status' => 'READY_FOR_CONVERSION']);
                BookingRequestHistory::create([
                    'booking_request_id' => $booking->id, 'actor_id' => null,
                    'from_status' => 'PENDING_PAYMENT', 'to_status' => 'READY_FOR_CONVERSION',
                    'note' => 'Pembayaran dikonfirmasi melalui notifikasi server-to-server Midtrans.',
                    'created_at' => now(),
                ]);
                $audit->record(null, 'BOOKING_PAYMENT_CONFIRMED', 'BookingPayment', $locked->id,
                    'Payment gateway mengonfirmasi pembayaran untuk '.$booking->request_number.'.',
                    ['status' => 'PENDING'], ['status' => 'PAID']);
                $communication->notify($booking->customer, 'PAYMENT_PAID', 'Pembayaran berhasil',
                    'Pembayaran untuk '.$booking->request_number.' telah dikonfirmasi oleh payment gateway.', $booking);
                $communication->notifyOperators('BOOKING_READY_FOR_CONVERSION', 'Booking siap dikonversi',
                    'Pembayaran untuk '.$booking->request_number.' berhasil. Operator dapat membuat shipment.', $booking);
            } elseif (in_array($newStatus, ['FAILED','EXPIRED'], true)) {
                $audit->record(null, 'BOOKING_PAYMENT_'.$newStatus, 'BookingPayment', $locked->id,
                    'Status pembayaran gateway berubah menjadi '.$newStatus.'.',
                    ['status' => 'PENDING'], ['status' => $newStatus]);
            }
        });

        return response()->json(['message' => 'Notification accepted.']);
    }

}
