<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\AuditService;
use App\Services\GeocodingService;
use Illuminate\Http\Request;
use RuntimeException;

class LocationController extends Controller
{
    public function index(Request $request)
    {
        $query = Location::query();
        $query->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')));
        $query->when($request->filled('country'), fn ($q) => $q->where('country', 'like', '%'.$request->string('country').'%'));
        $query->when($request->filled('search'), fn ($q) => $q->where(fn ($s) => $s->where('name', 'like', '%'.$request->search.'%')->orWhere('city', 'like', '%'.$request->search.'%')));
        return response()->json($query->orderBy('name')->paginate(min($request->integer('per_page', 20), 100)));
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate($this->rules());
        $location = Location::create($data);
        $audit->record($request->user(), 'LOCATION_CREATED', 'Location', $location->id, 'Lokasi baru dibuat.');
        return response()->json(['data' => $location], 201);
    }

    public function show(Location $location)
    {
        return response()->json(['data' => $location]);
    }

    public function update(Request $request, Location $location, AuditService $audit)
    {
        $data = $request->validate($this->rules($location));
        $old = $location->only(array_keys($data));
        $location->update($data);
        $audit->record($request->user(), 'LOCATION_UPDATED', 'Location', $location->id, 'Data lokasi diperbarui.', $old, $data);
        return response()->json(['data' => $location->fresh()]);
    }

    public function destroy(Request $request, Location $location, AuditService $audit)
    {
        abort_if($location->originShipments()->exists() || $location->destinationShipments()->exists(), 422,
            'Lokasi masih dipakai shipment. Jangan hapus; perbarui datanya bila diperlukan.');
        $audit->record($request->user(), 'LOCATION_DELETED', 'Location', $location->id, 'Lokasi dihapus.');
        $location->delete();
        return response()->json(['message' => 'Lokasi berhasil dihapus.']);
    }

    public function geocode(Request $request, GeocodingService $service)
    {
        $data = $request->validate(['query' => ['required', 'string', 'min:3', 'max:180']]);
        try {
            return response()->json(['data' => $service->search($data['query'])]);
        } catch (RuntimeException) {
            return response()->json(['message' => 'Layanan pencarian lokasi sedang tidak tersedia.'], 503);
        }
    }

    private function rules(?Location $location = null): array
    {
        return [
            'code' => ['nullable', 'string', 'max:40', 'unique:locations,code'.($location ? ','.$location->id : '')],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'address' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
