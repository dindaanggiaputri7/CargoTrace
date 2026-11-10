<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\AuditService;
use App\Services\GeocodingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class LocationController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'type' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:120'],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = Location::query();
        $query->when(!empty($data['type']), fn ($q) => $q->where('type', $data['type']));
        $query->when(!empty($data['country']), fn ($q) => $q->where('country', 'like', '%'.$data['country'].'%'));
        $query->when(!empty($data['search']), fn ($q) => $q->where(fn ($s) => $s
            ->where('name', 'like', '%'.$data['search'].'%')->orWhere('city', 'like', '%'.$data['search'].'%')
            ->orWhere('code', 'like', '%'.$data['search'].'%')));

        return response()->json($query->orderBy('name')->paginate($data['per_page'] ?? 20)->withQueryString());
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate($this->rules());
        $location = Location::create($data);
        $audit->record($request->user(), 'LOCATION_CREATED', 'Location', $location->id, 'Lokasi baru dibuat.', null, $location->toArray());
        return response()->json(['data' => $location], 201);
    }

    public function show(Location $location)
    {
        return response()->json(['data' => $location->loadCount('trackingEvents')]);
    }

    public function update(Request $request, Location $location, AuditService $audit)
    {
        $data = $request->validate($this->rules($location));
        $old = $location->only(array_keys($data));
        $location->update($data);
        $audit->record($request->user(), 'LOCATION_UPDATED', 'Location', $location->id, 'Data lokasi diperbarui.', $old, $location->fresh()->only(array_keys($data)));
        return response()->json(['data' => $location->fresh()]);
    }

    public function destroy(Request $request, Location $location, AuditService $audit)
    {
        if ($location->originShipments()->exists() || $location->destinationShipments()->exists() || $location->trackingEvents()->exists()) {
            throw ValidationException::withMessages(['location' => ['Lokasi sudah digunakan dalam shipment atau tracking. Pertahankan lokasi historis dan perbarui datanya bila perlu.']]);
        }
        $audit->record($request->user(), 'LOCATION_DELETED', 'Location', $location->id, 'Lokasi dihapus.');
        $location->delete();
        return response()->json(['message' => 'Lokasi berhasil dihapus.']);
    }

    public function geocode(Request $request, GeocodingService $service)
    {
        $data = $request->validate(['query' => ['required', 'string', 'min:3', 'max:180']]);
        try {
            $results = $service->search($data['query']);
            return response()->json(['data' => $results, 'message' => $results ? null : 'Tidak ada koordinat yang ditemukan.']);
        } catch (RuntimeException) {
            return response()->json(['message' => 'Layanan geocoding sedang tidak tersedia. Silakan masukkan koordinat secara manual.'], 503);
        }
    }

    private function rules(?Location $location = null): array
    {
        $unique = Rule::unique('locations', 'code');
        if ($location) $unique->ignore($location->id);
        return [
            'code' => ['sometimes', 'nullable', 'string', 'max:40', $unique],
            'name' => [$location ? 'sometimes' : 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'string', 'max:30'],
            'address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'region' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country' => [$location ? 'sometimes' : 'required', 'string', 'max:255'],
            'country_code' => ['sometimes', 'nullable', 'string', 'size:2'],
            'timezone' => ['sometimes', 'nullable', 'timezone'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
