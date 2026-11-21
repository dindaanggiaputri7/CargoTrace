<?php

namespace App\Console\Commands;

use App\Models\BookingPayment;
use App\Models\BookingRequest;
use App\Models\BookingRequestHistory;
use App\Services\AuditService;
use App\Services\BookingCommunicationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireBookingPayments extends Command
{
    protected $signature = 'cargotrace:expire-booking-payments';
    protected $description = 'Mark unpaid CargoTrace booking payments as expired after their 24-hour validity period.';

    public function handle(BookingCommunicationService $communication, AuditService $audit): int
    {
        $expiredCount = 0;

        BookingPayment::query()
            ->where('status', 'PENDING')
            ->where(function ($query) {
                $query->where('expires_at', '<=', now())
                    ->orWhere(function ($legacy) {
                        $legacy->whereNull('expires_at')->where('created_at', '<=', now()->subHours(24));
                    });
            })
            ->orderBy('id')
            ->chunkById(100, function ($payments) use (&$expiredCount, $communication, $audit) {
                foreach ($payments as $payment) {
                    DB::transaction(function () use ($payment, &$expiredCount, $communication, $audit) {
                        // Keep the same lock order as checkout and the Midtrans webhook.
                        $booking = BookingRequest::query()->lockForUpdate()->find($payment->booking_request_id);
                        $locked = BookingPayment::query()->lockForUpdate()->find($payment->id);
                        if (!$locked || $locked->status !== 'PENDING') {
                            return;
                        }
                        $expiryTime = $locked->expires_at ?? $locked->created_at?->copy()->addHours(24);
                        if (!$expiryTime || $expiryTime->isFuture()) {
                            return;
                        }

                        $locked->update([
                            'status' => 'EXPIRED',
                            'expires_at' => $expiryTime,
                            'provider_data' => array_merge($locked->provider_data ?? [], [
                                'expired_by_scheduler_at' => now()->toIso8601String(),
                            ]),
                        ]);
                        if ($booking) {
                            $audit->record(null, 'BOOKING_PAYMENT_EXPIRED', 'BookingPayment', $locked->id,
                                'Pembayaran '.$locked->provider_reference.' kedaluwarsa otomatis setelah 24 jam.',
                                ['status' => 'PENDING'], ['status' => 'EXPIRED']);

                            $hasPaid = $booking->payments()->where('status', 'PAID')->exists();
                            $hasActivePending = $booking->payments()->where('status', 'PENDING')
                                ->where(function ($q) { $q->whereNull('expires_at')->orWhere('expires_at', '>', now()); })
                                ->exists();

                            if ($booking->status === 'PENDING_PAYMENT' && !$hasPaid && !$hasActivePending) {
                                $booking->update(['status' => 'QUOTE_ACCEPTED']);
                                BookingRequestHistory::create([
                                    'booking_request_id' => $booking->id,
                                    'actor_id' => null,
                                    'from_status' => 'PENDING_PAYMENT',
                                    'to_status' => 'QUOTE_ACCEPTED',
                                    'note' => 'Pembayaran kedaluwarsa otomatis setelah 24 jam; customer dapat membuat transaksi baru.',
                                    'created_at' => now(),
                                ]);
                                $communication->notify($booking->customer, 'PAYMENT_EXPIRED', 'Batas pembayaran berakhir',
                                    'Pembayaran untuk '.$booking->request_number.' telah kedaluwarsa. Anda dapat memulai pembayaran baru.', $booking);
                            }
                        }

                        $expiredCount++;
                    });
                }
            });

        $this->info("Expired {$expiredCount} pending payment(s).");
        return self::SUCCESS;
    }
}
