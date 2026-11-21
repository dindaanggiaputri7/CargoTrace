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
            ->map(function (array $item) {
                $address = $item['address'] ?? [];
                return [
                    'display_name' => $item['display_name'] ?? '',
                    'latitude' => (float) $item['lat'],
                    'longitude' => (float) $item['lon'],
                    'type' => $item['type'] ?? null,
                    'country' => $address['country'] ?? null,
                    'country_code' => strtoupper($address['country_code'] ?? ''),
                    'city' => $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['municipality'] ?? $address['county'] ?? null,
                    'region' => $address['state'] ?? $address['province'] ?? $address['region'] ?? null,
                    'address' => $item['display_name'] ?? null,
                ];
            })->values()->all();
    }
}
