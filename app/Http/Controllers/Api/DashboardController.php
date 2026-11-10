<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $shipments = Shipment::query();
        if ($user->isCustomer()) $shipments->where('customer_id', $user->id);

        $base = clone $shipments;
        $activeStatuses = ['CREATED', 'BOOKED', 'PICKED_UP', 'IN_TRANSIT'];
        $stats = [
            'total_shipments' => (clone $base)->count(),
            'active_shipments' => (clone $base)->whereIn('status', $activeStatuses)->count(),
            'in_transit' => (clone $base)->where('status', 'IN_TRANSIT')->count(),
            'delivered' => (clone $base)->where('status', 'DELIVERED')->count(),
            'delayed' => (clone $base)->whereNotNull('current_eta')->whereColumn('current_eta', '>', 'expected_arrival_at')->where('status', '!=', 'DELIVERED')->count(),
        ];

        if (!$user->isCustomer()) {
            $stats['unresolved_exceptions'] = ShipmentException::whereIn('status', ['OPEN', 'INVESTIGATING'])->count();
        } else {
            $stats['unresolved_exceptions'] = ShipmentException::whereIn('status', ['OPEN', 'INVESTIGATING'])
                ->whereHas('shipment', fn ($q) => $q->where('customer_id', $user->id))->count();
        }

        if ($user->isAdmin()) {
            $stats['total_users'] = User::count();
            $stats['active_customers'] = User::where('role', User::ROLE_CUSTOMER)->where('is_active', true)->count();
        }

        return response()->json(['data' => $stats]);
    }
}
