<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Carrier;
use App\Models\Shipment;
use App\Models\ShipmentException;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $base = Shipment::query();
        if ($user->isCustomer()) $base->where('customer_id', $user->id);

        $total = (clone $base)->count();
        $active = (clone $base)->whereIn('status', Shipment::ACTIVE_STATUSES)->count();
        $delayedQuery = (clone $base)->delayed();
        $delayed = (clone $delayedQuery)->count();
        $exceptionQuery = ShipmentException::query()->whereIn('status', ['OPEN', 'INVESTIGATING']);
        if ($user->isCustomer()) $exceptionQuery->whereHas('shipment', fn ($q) => $q->where('customer_id', $user->id));
        $openExceptionCount = (clone $exceptionQuery)->count();
        $exceptionShipmentCount = (clone $exceptionQuery)->distinct('shipment_id')->count('shipment_id');

        $statusCounts = (clone $base)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $byStatus = collect(Shipment::STATUSES)->map(fn ($status) => ['status' => $status, 'total' => (int) ($statusCounts[$status] ?? 0)])->values();
        $byCarrierQuery = Carrier::query()->withCount(['shipments' => function ($q) use ($user) {
            if ($user->isCustomer()) $q->where('customer_id', $user->id);
        }]);
        $byCarrier = $byCarrierQuery->orderByDesc('shipments_count')->orderBy('name')->get(['id', 'code', 'name'])
            ->map(fn ($carrier) => ['carrier_id' => $carrier->id, 'code' => $carrier->code, 'name' => $carrier->name, 'total' => $carrier->shipments_count]);

        $recent = (clone $base)->with(['customer:id,name', 'carrier:id,name,code', 'origin:id,name,city,country', 'destination:id,name,city,country'])
            ->latest()->limit(5)->get();
        $delayedShipments = (clone $delayedQuery)->with(['customer:id,name', 'carrier:id,name', 'origin:id,name', 'destination:id,name'])
            ->orderByRaw('COALESCE(current_eta, expected_arrival_at) DESC')->limit(5)->get();
        $openExceptions = (clone $exceptionQuery)->with(['shipment:id,shipment_number,customer_id', 'shipment.customer:id,name'])
            ->latest('detected_at')->limit(5)->get();

        $stats = [
            'total_shipments' => $total,
            'active_shipments' => $active,
            'in_transit' => (clone $base)->where('status', 'IN_TRANSIT')->count(),
            'delivered' => (clone $base)->where('status', 'DELIVERED')->count(),
            'delayed' => $delayed,
            'delay_rate' => $active > 0 ? round(($delayed / $active) * 100, 1) : 0.0,
            'open_exceptions' => $openExceptionCount,
            'exception_rate' => $total > 0 ? round(($exceptionShipmentCount / $total) * 100, 1) : 0.0,
            'unread_notifications' => $user->notificationFeed()->whereNull('read_at')->count(),
        ];
        if ($user->isAdmin()) {
            $stats['total_users'] = \App\Models\User::count();
            $stats['active_users'] = \App\Models\User::where('is_active', true)->count();
        }

        return response()->json(['data' => [
            'role' => $user->role,
            'stats' => $stats,
            'charts' => ['shipments_by_status' => $byStatus, 'shipments_by_carrier' => $byCarrier],
            'recent_shipments' => $recent,
            'delayed_shipments' => $delayedShipments,
            'open_exceptions' => $openExceptions,
        ]]);
    }
}
