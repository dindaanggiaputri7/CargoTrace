<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $query = Notification::where('user_id', $request->user()->id)->with('shipment:id,shipment_number,status');
        if ($request->boolean('unread_only')) $query->whereNull('read_at');
        return response()->json($query->latest()->paginate(min($request->integer('per_page', 20), 100)));
    }

    public function markRead(Request $request, Notification $notification)
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 404);
        $notification->update(['read_at' => now()]);
        return response()->json(['data' => $notification->fresh()]);
    }

    public function markAllRead(Request $request)
    {
        Notification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);
        return response()->json(['message' => 'Semua notifikasi sudah ditandai telah dibaca.']);
    }
}
