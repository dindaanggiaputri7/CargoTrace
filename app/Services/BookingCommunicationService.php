<?php

namespace App\Services;

use App\Models\BookingRequest;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class BookingCommunicationService
{
    public function notify(?User $user, string $type, string $title, string $message, ?BookingRequest $booking = null): ?Notification
    {
        if (!$user) return null;

        $notification = app(NotificationService::class)->send(
            $user,
            $type,
            $title,
            $message,
            $booking?->shipment,
            $booking ? ['booking_request_id' => $booking->id, 'request_number' => $booking->request_number] : []
        );

        if (config('cargotrace.email_notifications') && $user->email) {
            try {
                Mail::raw($message."\n\nBuka CargoTrace untuk melihat detail permintaan Anda.", function ($mail) use ($user, $title) {
                    $mail->to($user->email, $user->name)->subject('[CargoTrace] '.$title);
                });
            } catch (\Throwable $exception) {
                // Email delivery is optional; it must never roll back the booking workflow.
                Log::warning('CargoTrace booking email could not be sent.', ['exception' => class_basename($exception)]);
            }
        }

        return $notification;
    }

    public function notifyOperators(string $type, string $title, string $message, ?BookingRequest $booking = null): void
    {
        User::query()
            ->where('role', User::ROLE_OPERATOR)
            ->where('is_active', true)
            ->get()
            ->each(fn (User $operator) => $this->notify($operator, $type, $title, $message, $booking));
    }
}
