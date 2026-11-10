<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeocodingService
{
    /** Uses OpenStreetMap Nominatim; no API key is required. Respect its usage policy. */
    public function search(string $query): array
    {
        $endpoint = rtrim((string) config('services.cargotrace_geocoding.endpoint'), '/');
        $userAgent = (string) config('services.cargotrace_geocoding.user_agent');

        try {
            $response = Http::timeout(8)
                ->retry(1, 250)
                ->acceptJson()
                ->withUserAgent($userAgent)
                ->get($endpoint.'/search', [
                    'q' => $query,
                    'format' => 'jsonv2',
                    'limit' => 5,
                    'addressdetails' => 1,
                ]);
        } catch (\Throwable $exception) {
            report($exception);
            throw new RuntimeException('Layanan pencarian lokasi sedang tidak tersedia.');
        }

        if (!$response->successful()) {
            throw new RuntimeException('Layanan pencarian lokasi sedang tidak tersedia.');
        }

        return collect($response->json() ?? [])->filter(fn ($item) => isset($item['lat'], $item['lon']))
            ->map(fn (array $item) => [
                'display_name' => $item['display_name'] ?? '',
                'latitude' => (float) $item['lat'],
                'longitude' => (float) $item['lon'],
                'type' => $item['type'] ?? null,
                'country_code' => strtoupper($item['address']['country_code'] ?? ''),
            ])->values()->all();
    }
}
