<?php

namespace App\Services;

use App\Enums\ListingStatus;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * A rough "what's my home worth" figure.
 *
 * Uses RentCast's estimate when live homes are switched on and requests are left;
 * otherwise works it out from homes on this website (sold and for sale) in the same ZIP code,
 * or the same city when the ZIP has too few.
 */
class HomeValueEstimator
{
    /**
     * Fewest similar homes needed before we trust our own numbers.
     */
    public const int MIN_COMPARABLES = 3;

    public function __construct(private RentCast $rentCast)
    {
        //
    }

    /**
     * @param  array{address: string, zip: string, beds?: int|null, baths?: float|null, sqft?: int|null}  $home
     * @return array{price: int, low: int, high: int, source: 'rentcast'|'local', comparables: int}|null
     */
    public function estimate(array $home): ?array
    {
        $remote = $this->rentCast->valueEstimate([
            'address' => $home['address'].', '.$home['zip'],
            'beds' => $home['beds'] ?? null,
            'baths' => $home['baths'] ?? null,
            'sqft' => $home['sqft'] ?? null,
        ]);

        if ($remote !== null) {
            return [...$remote, 'source' => 'rentcast', 'comparables' => 0];
        }

        return $this->fromOwnListings($home);
    }

    /**
     * @param  array{address: string, zip: string, beds?: int|null, baths?: float|null, sqft?: int|null}  $home
     * @return array{price: int, low: int, high: int, source: 'local', comparables: int}|null
     */
    private function fromOwnListings(array $home): ?array
    {
        $base = Listing::query()
            ->whereIn('status', [ListingStatus::Sold, ListingStatus::ForSale, ListingStatus::Pending])
            ->where('price', '>', 0);

        $comparables = $this->comparables((clone $base)->where('zip', $home['zip']), $home);

        if ($comparables->count() < self::MIN_COMPARABLES) {
            $cityId = Listing::query()->where('zip', $home['zip'])->value('city_id');

            if ($cityId !== null) {
                $comparables = $this->comparables((clone $base)->where('city_id', $cityId), $home);
            }
        }

        if ($comparables->count() < self::MIN_COMPARABLES) {
            return null;
        }

        $sqft = (int) ($home['sqft'] ?? 0);
        $withSize = $comparables->filter(fn (Listing $listing) => $listing->sqft > 0);

        $price = $sqft > 0 && $withSize->count() >= self::MIN_COMPARABLES
            ? (int) round($this->median($withSize->map(fn (Listing $listing) => $listing->price / $listing->sqft)) * $sqft)
            : (int) round($this->median($comparables->pluck('price')));

        $price = (int) round($price / 1000) * 1000;

        return [
            'price' => $price,
            'low' => (int) round($price * 0.9 / 1000) * 1000,
            'high' => (int) round($price * 1.1 / 1000) * 1000,
            'source' => 'local',
            'comparables' => $comparables->count(),
        ];
    }

    /**
     * Similar homes: same number of beds give or take one, when beds are known.
     *
     * @param  Builder<Listing>  $query
     * @param  array{beds?: int|null}  $home
     * @return Collection<int, Listing>
     */
    private function comparables(Builder $query, array $home): Collection
    {
        $beds = $home['beds'] ?? null;

        if ($beds !== null) {
            $query->whereBetween('beds', [max(0, $beds - 1), $beds + 1]);
        }

        return $query->latest('listed_at')->take(40)->get(['id', 'price', 'sqft', 'beds']);
    }

    /**
     * @param  Collection<int, int|float>  $values
     */
    private function median(Collection $values): float
    {
        return (float) $values->median();
    }
}
