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
        $serverKey = trim((string) config('services.midtrans.server_key'));
        abort_unless($serverKey !== '', 503, 'Payment provider is not configured.');
        $expected = hash('sha512', $data['order_id'].$data['status_code'].$data['gross_amount'].$serverKey);
        abort_unless(hash_equals($expected, $data['signature_key']), 401, 'Invalid payment notification signature.');

        $payment = BookingPayment::where('provider', 'midtrans')
            ->where('provider_reference', $data['order_id'])->firstOrFail();

        DB::transaction(function () use ($payment, $data, $communication, $audit) {
            // All payment flows lock BookingRequest before BookingPayment to avoid lock-order deadlocks.
            $booking = BookingRequest::query()->lockForUpdate()->findOrFail($payment->booking_request_id);
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

            // Late PENDING notifications must never revive a transaction already marked FAILED/EXPIRED.
            if (in_array($locked->status, ['FAILED', 'EXPIRED'], true) && $newStatus === 'PENDING') {
                return;
            }
            // Repeated gateway notifications for the same state are idempotent.
            if ($locked->status === $newStatus) {
                return;
            }

            $previousPaymentStatus = $locked->status;
            $locked->update([
                'status' => $newStatus,
                'paid_at' => $newStatus === 'PAID' ? now() : $locked->paid_at,
                'provider_data' => array_merge($locked->provider_data ?? [], [
                    'last_notification_status' => $transactionStatus,
                    'fraud_status' => $fraudStatus ?: null,
                    'notification_received_at' => now()->toIso8601String(),
                ]),
            ]);

            if ($newStatus === 'PAID' && in_array($booking->status, ['PENDING_PAYMENT', 'QUOTE_ACCEPTED'], true)) {
                $fromBookingStatus = $booking->status;
                $booking->update(['status' => 'READY_FOR_CONVERSION']);
                BookingRequestHistory::create([
                    'booking_request_id' => $booking->id, 'actor_id' => null,
                    'from_status' => $fromBookingStatus, 'to_status' => 'READY_FOR_CONVERSION',
                    'note' => 'Pembayaran dikonfirmasi melalui notifikasi server-to-server Midtrans.',
                    'created_at' => now(),
                ]);
                $audit->record(null, 'BOOKING_PAYMENT_CONFIRMED', 'BookingPayment', $locked->id,
                    'Payment gateway mengonfirmasi pembayaran untuk '.$booking->request_number.'.',
                    ['status' => $previousPaymentStatus], ['status' => 'PAID']);
                $communication->notify($booking->customer, 'PAYMENT_PAID', 'Pembayaran berhasil',
                    'Pembayaran untuk '.$booking->request_number.' telah dikonfirmasi oleh payment gateway.', $booking);
                $communication->notifyOperators('BOOKING_READY_FOR_CONVERSION', 'Booking siap dikonversi',
                    'Pembayaran untuk '.$booking->request_number.' berhasil. Operator dapat membuat shipment.', $booking);
            } elseif (in_array($newStatus, ['FAILED','EXPIRED'], true)) {
                $audit->record(null, 'BOOKING_PAYMENT_'.$newStatus, 'BookingPayment', $locked->id,
                    'Status pembayaran gateway berubah menjadi '.$newStatus.'.',
                    ['status' => $previousPaymentStatus], ['status' => $newStatus]);
                if ($booking->status === 'PENDING_PAYMENT' && !$booking->payments()->where('status', 'PAID')->exists()) {
                    $booking->update(['status' => 'QUOTE_ACCEPTED']);
                    BookingRequestHistory::create([
                        'booking_request_id' => $booking->id, 'actor_id' => null,
                        'from_status' => 'PENDING_PAYMENT', 'to_status' => 'QUOTE_ACCEPTED',
                        'note' => 'Pembayaran tidak berhasil atau telah kedaluwarsa; customer dapat mencoba kembali.',
                        'created_at' => now(),
                    ]);
                    $communication->notify($booking->customer, 'PAYMENT_'.$newStatus, 'Pembayaran belum berhasil',
                        'Pembayaran untuk '.$booking->request_number.' berstatus '.$newStatus.'. Anda dapat mencoba kembali dari CargoTrace.', $booking);
                }
            }
        });

        return response()->json(['message' => 'Notification accepted.']);
    }

}
