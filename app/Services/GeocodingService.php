<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeocodingService
{
    public function search(string $query): array
    {
        $response = Http::timeout(10)->retry(1, 300)
            ->withHeaders(['User-Agent' => config('app.name', 'CargoTrace').'/1.0 (shipment visibility app)'])
            ->get('https://nominatim.openstreetmap.org/search', [
                'q' => $query, 'format' => 'jsonv2', 'limit' => 5,
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('Layanan pencarian lokasi sedang tidak tersedia.');
        }

        return collect($response->json() ?? [])->map(fn (array $item) => [
            'display_name' => $item['display_name'] ?? '',
            'latitude' => isset($item['lat']) ? (float) $item['lat'] : null,
            'longitude' => isset($item['lon']) ? (float) $item['lon'] : null,
            'type' => $item['type'] ?? null,
        ])->all();
    }
}
