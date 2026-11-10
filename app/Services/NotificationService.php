<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Shipment;
use App\Models\User;

class NotificationService
{
    public function send(
        ?User $user,
        string $type,
        string $title,
        string $message,
        ?Shipment $shipment = null,
        array $data = []
    ): ?Notification {
        if (!$user) return null;

        return Notification::create([
            'user_id' => $user->id,
            'shipment_id' => $shipment?->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data ?: null,
        ]);
    }
}
