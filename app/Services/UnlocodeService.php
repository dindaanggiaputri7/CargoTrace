<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class UnlocodeService
{
    /**
     * Query the official UNECE directory by country, then match the city name.
     * The result is a suggestion only; an administrator must confirm it.
     */
    public function search(string $countryCode, string $city): array
    {
        $countryCode = strtoupper(trim($countryCode));
        $city = trim($city);
        if (!preg_match('/^[A-Z]{2}$/', $countryCode) || strlen(Str::ascii($city)) < 2) {
            return [];
        }

        $records = Cache::remember('cargotrace.unlocode.country.'.$countryCode, now()->addDay(), function () use ($countryCode) {
            $response = Http::acceptJson()->timeout(12)
                ->get('https://unlocode.unece.org/api/v1/locodes/list/'.$countryCode);

            if (!$response->successful()) {
                throw new RuntimeException('Official UN/LOCODE directory is temporarily unavailable.');
            }

            $payload = $response->json();
            if (!is_array($payload)) return [];

            foreach (['data', 'results', 'locodes', 'items'] as $key) {
                if (isset($payload[$key]) && is_array($payload[$key])) {
                    $payload = $payload[$key];
                    break;
                }
            }

            return array_is_list($payload) ? $payload : array_values($payload);
        });

        $needle = Str::lower(Str::ascii($city));
        $matches = [];
        foreach ($records as $record) {
            if (!is_array($record)) continue;
            $row = [];
            foreach ($record as $key => $value) $row[Str::lower(str_replace([' ', '_', '-'], '', (string) $key))] = $value;

            $name = (string) ($row['name'] ?? $row['locationname'] ?? $row['locodename'] ?? $row['description'] ?? '');
            $code = strtoupper((string) ($row['locode'] ?? $row['unlocode'] ?? $row['code'] ?? ''));
            if ($code === '' && isset($row['countrycode'], $row['locationcode'])) {
                $code = strtoupper((string) $row['countrycode'].(string) $row['locationcode']);
            }
            if ($code === '' || $name === '') continue;
            $normalizedName = Str::lower(Str::ascii($name));
            if ($normalizedName === $needle || str_contains($normalizedName, $needle) || str_contains($needle, $normalizedName)) {
                $matches[] = ['code' => $code, 'name' => $name, 'country_code' => $countryCode];
            }
        }

        return array_slice($matches, 0, 8);
    }
}
