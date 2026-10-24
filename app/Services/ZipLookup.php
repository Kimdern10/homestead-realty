<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Turns a US ZIP code into its town, state and map position using the free Zippopotam.us service.
 */
class ZipLookup
{
    /**
     * Whether the text is a 5-digit US ZIP code.
     */
    public static function isZip(string $text): bool
    {
        return (bool) preg_match('/^\d{5}$/', trim($text));
    }

    /**
     * Details for a ZIP code, or null when it does not exist or the service is down.
     *
     * @return array{zip: string, place: string, state: string, state_code: string, lat: float, lng: float}|null
     */
    public function find(string $zip): ?array
    {
        $zip = trim($zip);

        if (! self::isZip($zip)) {
            return null;
        }

        $cached = Cache::get("zip:{$zip}");

        if ($cached !== null) {
            return $cached ?: null;
        }

        try {
            $response = Http::timeout(8)->acceptJson()->get("https://api.zippopotam.us/us/{$zip}");
        } catch (ConnectionException) {
            return null;
        }

        if ($response->status() === 404) {
            Cache::put("zip:{$zip}", [], now()->addDays(30));

            return null;
        }

        $place = $response->json('places.0');

        if ($response->failed() || ! is_array($place)) {
            return null;
        }

        $result = [
            'zip' => $zip,
            'place' => (string) ($place['place name'] ?? ''),
            'state' => (string) ($place['state'] ?? ''),
            'state_code' => (string) ($place['state abbreviation'] ?? ''),
            'lat' => (float) ($place['latitude'] ?? 0),
            'lng' => (float) ($place['longitude'] ?? 0),
        ];

        Cache::put("zip:{$zip}", $result, now()->addDays(30));

        return $result;
    }
}
