<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Finds schools near a point.
 *
 * Uses the U.S. Department of Education's school locations (NCES, free, no key), public and
 * private, and falls back to OpenStreetMap's Overpass API when that service is down.
 */
class SchoolFinder
{
    /**
     * How far to look, in miles.
     */
    public const int RADIUS_MILES = 2;

    private const string NCES = 'https://services1.arcgis.com/Ua5sjt3LWTPigjyD/arcgis/rest/services';

    /**
     * The closest schools, nearest first.
     *
     * @return list<array{name: string, level: string|null, kind: string|null, miles: float, lat: float, lng: float}>
     */
    public function near(float $lat, float $lng, int $limit = 8): array
    {
        // Rounding to ~100 m lets nearby homes share one answer.
        $key = sprintf('schools:%.3f:%.3f', $lat, $lng);

        $schools = Cache::get($key);

        if ($schools === null) {
            $schools = $this->fromNces($lat, $lng) ?? $this->fromOpenStreetMap($lat, $lng);

            if ($schools === null) {
                return [];
            }

            Cache::put($key, $schools, now()->addDays(30));
        }

        return array_values(array_map(
            fn (array $school): array => $this->shape($school),
            array_slice(array_filter((array) $schools, 'is_array'), 0, $limit),
        ));
    }

    /**
     * One school with every field in its expected type (cached values come back untyped).
     *
     * @param  array<mixed>  $school
     * @return array{name: string, level: string|null, kind: string|null, miles: float, lat: float, lng: float}
     */
    private function shape(array $school): array
    {
        return [
            'name' => (string) ($school['name'] ?? ''),
            'level' => isset($school['level']) ? (string) $school['level'] : null,
            'kind' => isset($school['kind']) ? (string) $school['kind'] : null,
            'miles' => (float) ($school['miles'] ?? 0),
            'lat' => (float) ($school['lat'] ?? 0),
            'lng' => (float) ($school['lng'] ?? 0),
        ];
    }

    /**
     * Public and private schools from the Department of Education, or null if it can't be reached.
     *
     * @return list<array{name: string, level: string|null, kind: string|null, miles: float, lat: float, lng: float}>|null
     */
    private function fromNces(float $lat, float $lng): ?array
    {
        $query = [
            'geometry' => "{$lng},{$lat}",
            'geometryType' => 'esriGeometryPoint',
            'inSR' => 4326,
            'distance' => self::RADIUS_MILES,
            'units' => 'esriSRUnit_StatuteMile',
            'spatialRel' => 'esriSpatialRelIntersects',
            'outFields' => 'NAME,LAT,LON',
            'returnGeometry' => 'false',
            'resultRecordCount' => 60,
            'f' => 'json',
        ];

        try {
            $responses = Http::pool(fn (Pool $pool) => [
                $pool->as('public')->timeout(12)->get(self::NCES.'/Public_School_Locations_Current/FeatureServer/0/query', $query),
                $pool->as('private')->timeout(12)->get(self::NCES.'/Private_School_Locations_Current/FeatureServer/0/query', $query),
            ]);
        } catch (ConnectionException) {
            return null;
        }

        $schools = [];

        foreach (['public' => 'Public', 'private' => 'Private'] as $name => $kind) {
            $response = $responses[$name] ?? null;

            if (! $response instanceof Response || $response->failed() || $response->json('error')) {
                if ($name === 'public') {
                    return null;
                }

                continue;
            }

            foreach ((array) $response->json('features') as $feature) {
                $attributes = $feature['attributes'] ?? [];

                if (blank($attributes['NAME'] ?? null) || ! isset($attributes['LAT'], $attributes['LON'])) {
                    continue;
                }

                $schoolName = $this->tidyName((string) $attributes['NAME']);

                $schools[] = [
                    'name' => $schoolName,
                    'level' => $this->level($schoolName),
                    'kind' => $kind,
                    'miles' => round($this->miles($lat, $lng, (float) $attributes['LAT'], (float) $attributes['LON']), 1),
                    'lat' => (float) $attributes['LAT'],
                    'lng' => (float) $attributes['LON'],
                ];
            }
        }

        return array_values(collect($schools)->sortBy('miles')->unique('name')->all());
    }

    /**
     * Schools from OpenStreetMap, or null if it can't be reached.
     *
     * @return list<array{name: string, level: string|null, kind: string|null, miles: float, lat: float, lng: float}>|null
     */
    private function fromOpenStreetMap(float $lat, float $lng): ?array
    {
        $radius = (int) round(self::RADIUS_MILES * 1609);
        $query = "[out:json][timeout:15];(node[\"amenity\"=\"school\"](around:{$radius},{$lat},{$lng});way[\"amenity\"=\"school\"](around:{$radius},{$lat},{$lng}););out center 60;";

        try {
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => config('app.name').' real estate website ('.config('app.url').')'])
                ->asForm()
                ->post('https://overpass-api.de/api/interpreter', ['data' => $query]);
        } catch (ConnectionException) {
            return null;
        }

        if ($response->failed() || ! is_array($response->json('elements'))) {
            return null;
        }

        return array_values(collect((array) $response->json('elements'))
            ->map(function (array $element) use ($lat, $lng): ?array {
                $tags = $element['tags'] ?? [];
                $schoolLat = $element['lat'] ?? $element['center']['lat'] ?? null;
                $schoolLng = $element['lon'] ?? $element['center']['lon'] ?? null;

                if (blank($tags['name'] ?? null) || $schoolLat === null || $schoolLng === null) {
                    return null;
                }

                return [
                    'name' => (string) $tags['name'],
                    'level' => filled($tags['grades'] ?? null) ? 'Grades '.$tags['grades'] : $this->level((string) $tags['name']),
                    'kind' => match ($tags['operator:type'] ?? null) {
                        'public', 'government' => 'Public',
                        'private', 'religious' => 'Private',
                        default => null,
                    },
                    'miles' => round($this->miles($lat, $lng, (float) $schoolLat, (float) $schoolLng), 1),
                    'lat' => (float) $schoolLat,
                    'lng' => (float) $schoolLng,
                ];
            })
            ->filter()
            ->sortBy('miles')
            ->unique('name')
            ->all());
    }

    /**
     * "DAWSON EL" → "Dawson Elementary", "O HENRY MS" → "O Henry Middle School".
     */
    private function tidyName(string $name): string
    {
        $name = Str::title(Str::lower(trim(preg_replace('/\s+/', ' ', $name))));

        $endings = [
            '/ El$/' => ' Elementary',
            '/ Elem$/' => ' Elementary',
            '/ Ms$/' => ' Middle School',
            '/ M S$/' => ' Middle School',
            '/ J H$/' => ' Junior High',
            '/ Hs$/' => ' High School',
            '/ H S$/' => ' High School',
            '/ Ec$/' => ' Early Childhood Center',
        ];

        $name = preg_replace(array_keys($endings), array_values($endings), $name) ?? $name;

        // Keep short words like "of" and "the" lower case, and initials upper case.
        return preg_replace_callback('/\b(Of|The|And|At|For|In)\b/', fn (array $match) => strtolower($match[0]), ucfirst($name)) ?? $name;
    }

    /**
     * "Elementary", "Middle", "High" or "K-12 / academy" when the name says so.
     */
    private function level(string $name): ?string
    {
        $text = strtolower($name);

        return match (true) {
            str_contains($text, 'elementary') || str_contains($text, 'primary') => 'Elementary',
            str_contains($text, 'middle') || str_contains($text, 'junior high') => 'Middle',
            str_contains($text, 'high') => 'High',
            str_contains($text, 'early childhood') || str_contains($text, 'pre-k') || str_contains($text, 'preschool') => 'Pre-K',
            str_contains($text, 'academy') || str_contains($text, 'charter') || str_contains($text, 'prep') => 'Academy',
            default => null,
        };
    }

    /**
     * Straight-line distance in miles.
     */
    private function miles(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthMiles = 3958.8;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthMiles * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
