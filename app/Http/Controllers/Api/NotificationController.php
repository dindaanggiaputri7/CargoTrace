<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'unread' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = Notification::query()->where('user_id', $request->user()->id)->with('shipment:id,shipment_number,status');
        if ($request->boolean('unread')) $query->whereNull('read_at');
        return response()->json($query->latest()->paginate($data['per_page'] ?? 20)->withQueryString());
    }

    public function markRead(Request $request, Notification $notification)
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 404);
        $notification->update(['read_at' => $notification->read_at ?? now()]);
        return response()->json(['message' => 'Notifikasi ditandai sudah dibaca.', 'data' => $notification->fresh()]);
    }

    public function markAllRead(Request $request)
    {
        $count = Notification::query()->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);
        return response()->json(['message' => 'Notifikasi belum dibaca telah ditandai selesai.', 'updated_count' => $count]);
    }
}
