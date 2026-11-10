<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Carrier;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CarrierController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'active_only' => ['nullable', 'boolean'],
            'transport_mode' => ['nullable', 'string', 'max:40'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = Carrier::query();
        $query->when(($data['active_only'] ?? false), fn ($q) => $q->where('is_active', true));
        $query->when(!empty($data['transport_mode']), fn ($q) => $q->where('transport_mode', $data['transport_mode']));
        $query->when(!empty($data['search']), fn ($q) => $q->where(fn ($s) => $s
            ->where('name', 'like', '%'.$data['search'].'%')->orWhere('code', 'like', '%'.$data['search'].'%')));

        return response()->json($query->withCount('shipments')->orderBy('name')->paginate($data['per_page'] ?? 20)->withQueryString());
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate($this->rules());
        $carrier = Carrier::create($data);
        $audit->record($request->user(), 'CARRIER_CREATED', 'Carrier', $carrier->id, 'Carrier baru dibuat.', null, $carrier->toArray());
        return response()->json(['data' => $carrier], 201);
    }

    public function show(Carrier $carrier)
    {
        return response()->json(['data' => $carrier->loadCount('shipments')]);
    }

    public function update(Request $request, Carrier $carrier, AuditService $audit)
    {
        $data = $request->validate($this->rules($carrier));
        $old = $carrier->only(array_keys($data));
        $carrier->update($data);
        $audit->record($request->user(), 'CARRIER_UPDATED', 'Carrier', $carrier->id, 'Data carrier diperbarui.', $old, $carrier->fresh()->only(array_keys($data)));
        return response()->json(['data' => $carrier->fresh()]);
    }

    public function destroy(Request $request, Carrier $carrier, AuditService $audit)
    {
        if ($carrier->shipments()->exists()) {
            throw ValidationException::withMessages(['carrier' => ['Carrier masih digunakan shipment. Nonaktifkan carrier, jangan hapus.']]);
        }
        $audit->record($request->user(), 'CARRIER_DELETED', 'Carrier', $carrier->id, 'Carrier dihapus.');
        $carrier->delete();
        return response()->json(['message' => 'Carrier berhasil dihapus.']);
    }

    private function rules(?Carrier $carrier = null): array
    {
        $unique = Rule::unique('carriers', 'code');
        if ($carrier) $unique->ignore($carrier->id);
        return [
            'code' => [$carrier ? 'sometimes' : 'required', 'string', 'max:30', $unique],
            'name' => [$carrier ? 'sometimes' : 'required', 'string', 'max:255'],
            'contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'transport_mode' => ['sometimes', 'nullable', 'string', 'max:40'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'support_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'support_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
