<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Carrier;
use App\Services\AuditService;
use Illuminate\Http\Request;

class CarrierController extends Controller
{
    public function index(Request $request)
    {
        $query = Carrier::query();
        if ($request->boolean('active_only')) $query->where('is_active', true);
        return response()->json($query->orderBy('name')->paginate(min($request->integer('per_page', 20), 100)));
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:carriers,code'],
            'name' => ['required', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $carrier = Carrier::create($data);
        $audit->record($request->user(), 'CARRIER_CREATED', 'Carrier', $carrier->id, 'Carrier baru dibuat.');
        return response()->json(['data' => $carrier], 201);
    }

    public function show(Carrier $carrier)
    {
        return response()->json(['data' => $carrier->loadCount('shipments')]);
    }

    public function update(Request $request, Carrier $carrier, AuditService $audit)
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:30', 'unique:carriers,code,'.$carrier->id],
            'name' => ['sometimes', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $old = $carrier->only(array_keys($data));
        $carrier->update($data);
        $audit->record($request->user(), 'CARRIER_UPDATED', 'Carrier', $carrier->id, 'Data carrier diperbarui.', $old, $data);
        return response()->json(['data' => $carrier->fresh()]);
    }

    public function destroy(Request $request, Carrier $carrier, AuditService $audit)
    {
        abort_if($carrier->shipments()->exists(), 422, 'Carrier masih dipakai shipment. Nonaktifkan saja carrier ini.');
        $audit->record($request->user(), 'CARRIER_DELETED', 'Carrier', $carrier->id, 'Carrier dihapus.');
        $carrier->delete();
        return response()->json(['message' => 'Carrier berhasil dihapus.']);
    }
}
