<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads US sale and rental listings from the RentCast API (rentcast.io).
 *
 * Every answer is cached, and requests are counted per calendar month and stopped
 * at the monthly limit, so the free plan is never exceeded by accident.
 */
class RentCast
{
    /**
     * Requests allowed per month (the free plan includes 50).
     */
    public const int MONTHLY_LIMIT = 50;

    /**
     * Whether an API key has been added to .env.
     */
    public function isConfigured(): bool
    {
        return filled(config('services.rentcast.key'));
    }

    /**
     * Whether the admin has switched live homes on (off by default, so nothing is fetched or charged).
     */
    public function isEnabled(): bool
    {
        return Setting::get('live_feed_enabled') === '1';
    }

    /**
     * Switch live homes on or off.
     */
    public function setEnabled(bool $enabled): void
    {
        Setting::put(['live_feed_enabled' => $enabled ? '1' : '0']);
    }

    /**
     * Whether homes can be fetched right now: switched on and a key is set.
     */
    public function isActive(): bool
    {
        return $this->isEnabled() && $this->isConfigured();
    }

    /**
     * How many requests were sent to RentCast this month.
     */
    public function requestsThisMonth(): int
    {
        return (int) Setting::get($this->counterKey());
    }

    /**
     * How many requests are left this month.
     */
    public function requestsLeft(): int
    {
        return max(0, $this->monthlyLimit() - $this->requestsThisMonth());
    }

    /**
     * The monthly request limit (can be raised in .env after upgrading the plan).
     */
    public function monthlyLimit(): int
    {
        return (int) (config('services.rentcast.monthly_limit') ?: self::MONTHLY_LIMIT);
    }

    /**
     * Whether a search was answered recently, so asking again would use no request.
     *
     * @param  array<string, string|int>  $query
     */
    public function isCached(string $type, array $query): bool
    {
        return Cache::has($this->cacheKey($type, $query));
    }

    /**
     * Active listings for a ZIP code or a city, newest first.
     *
     * @param  'sale'|'rent'  $type
     * @param  array<string, string|int>  $query  e.g. ['zipCode' => '78704'] or ['city' => 'Austin', 'state' => 'TX']
     * @return list<array<string, mixed>>
     *
     * @throws RuntimeException when the key is missing, the monthly limit is reached or RentCast fails
     */
    public function listings(string $type, array $query): array
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('Live homes are switched off. Turn them on in Dashboard › Live homes first.');
        }

        $cacheKey = $this->cacheKey($type, $query);

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        if (! $this->isConfigured()) {
            throw new RuntimeException('Add your RentCast API key to the .env file (RENTCAST_API_KEY) to fetch live listings.');
        }

        if ($this->requestsLeft() === 0) {
            throw new RuntimeException("This month's {$this->monthlyLimit()} RentCast requests have been used. Imported homes stay on the website; new searches work again next month.");
        }

        $path = $type === 'rent' ? '/listings/rental/long-term' : '/listings/sale';

        try {
            $response = Http::baseUrl((string) config('services.rentcast.url'))
                ->withHeaders(['X-Api-Key' => (string) config('services.rentcast.key'), 'Accept' => 'application/json'])
                ->timeout(20)
                ->get($path, [...$query, 'status' => 'Active', 'limit' => (int) config('services.rentcast.limit', 50)]);
        } catch (ConnectionException) {
            throw new RuntimeException('RentCast could not be reached. Please try again in a minute.');
        }

        $this->countRequest();

        if ($response->status() === 401 || $response->status() === 403) {
            throw new RuntimeException('RentCast did not accept the API key. Check RENTCAST_API_KEY in the .env file.');
        }

        if ($response->status() === 429) {
            throw new RuntimeException('RentCast says the request limit is reached. Try again later.');
        }

        if ($response->failed()) {
            throw new RuntimeException('RentCast returned an error ('.$response->status().'). Please try again later.');
        }

        $listings = array_values(array_filter((array) $response->json(), 'is_array'));

        Cache::put($cacheKey, $listings, now()->addHours((int) config('services.rentcast.cache_hours', 72)));

        return $listings;
    }

    /**
     * Add one to this month's request count.
     */
    private function countRequest(): void
    {
        // Kept in the settings table so clearing the cache never resets the count.
        Setting::put([$this->counterKey() => (string) ($this->requestsThisMonth() + 1)]);
    }

    private function counterKey(): string
    {
        return 'rentcast_requests_'.now()->format('Y_m');
    }

    /**
     * @param  array<string, string|int>  $query
     */
    private function cacheKey(string $type, array $query): string
    {
        ksort($query);

        return 'rentcast:'.$type.':'.md5((string) json_encode(array_map(fn ($value) => strtolower((string) $value), $query)));
    }
}
