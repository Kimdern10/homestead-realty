<?php

namespace App\Models;

use App\Enums\ListingStatus;
use App\Enums\PropertyType;
use Carbon\CarbonImmutable;
use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $agent_id
 * @property int $city_id
 * @property string $title
 * @property string $slug
 * @property ListingStatus $status
 * @property string $property_type
 * @property int $price
 * @property int|null $previous_price
 * @property CarbonImmutable|null $price_changed_at
 * @property string|null $owner_name
 * @property string|null $owner_email
 * @property string|null $owner_phone
 * @property int|null $beds
 * @property string|null $baths
 * @property int|null $sqft
 * @property string|null $lot_acres
 * @property int|null $year_built
 * @property int|null $garage_spaces
 * @property string $address
 * @property string|null $neighborhood
 * @property string $zip
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string $description
 * @property list<string>|null $amenities
 * @property string|null $video_url
 * @property string|null $virtual_tour_url
 * @property int|null $hoa_fee
 * @property int|null $property_tax
 * @property bool $is_featured
 * @property Carbon|null $featured_until
 * @property bool $is_published
 * @property Carbon $listed_at
 * @property Carbon|null $sold_at
 * @property int $views
 * @property string $source
 * @property string|null $external_id
 * @property string|null $mls_name
 * @property string|null $mls_number
 * @property string|null $listing_office
 * @property Carbon|null $synced_at
 * @property bool $edited_by_admin
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Agent $agent
 * @property-read City $city
 * @property-read Collection<int, ListingPhoto> $photos
 * @property-read ListingPhoto|null $coverPhoto
 */
#[Fillable([
    'agent_id', 'city_id', 'title', 'slug', 'status', 'property_type', 'price', 'beds', 'baths', 'sqft', 'lot_acres',
    'year_built', 'garage_spaces', 'address', 'neighborhood', 'zip', 'latitude', 'longitude', 'description', 'amenities',
    'video_url', 'virtual_tour_url', 'hoa_fee', 'property_tax', 'is_featured', 'is_published', 'listed_at', 'sold_at',
    'source', 'external_id', 'mls_name', 'mls_number', 'listing_office', 'owner_name', 'owner_email', 'owner_phone',
])]
class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory;

    /**
     * Remember the old price when a price goes down, so cards can show the cut.
     */
    protected static function booted(): void
    {
        static::updating(function (Listing $listing): void {
            if (! $listing->isDirty('price')) {
                return;
            }

            $old = (int) $listing->getOriginal('price');

            $listing->previous_price = $listing->price < $old ? max($old, (int) $listing->previous_price) : null;
            $listing->price_changed_at = now();
        });
    }

    public const int MAX_PHOTOS = 30;

    public const int PER_PAGE = 12;

    /**
     * The ways a visitor can sort search results.
     *
     * @var array<string, string>
     */
    public const array SORTS = [
        'newest' => 'Newest',
        'price_asc' => 'Price (low to high)',
        'price_desc' => 'Price (high to low)',
        'beds' => 'Most bedrooms',
        'sqft' => 'Largest',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ListingStatus::class,
            'price' => 'integer',
            'beds' => 'integer',
            'sqft' => 'integer',
            'year_built' => 'integer',
            'garage_spaces' => 'integer',
            'amenities' => 'array',
            'hoa_fee' => 'integer',
            'property_tax' => 'integer',
            'is_featured' => 'boolean',
            'featured_until' => 'datetime',
            'is_published' => 'boolean',
            'listed_at' => 'date',
            'sold_at' => 'date',
            'views' => 'integer',
            'previous_price' => 'integer',
            'price_changed_at' => 'datetime',
            'synced_at' => 'datetime',
            'edited_by_admin' => 'boolean',
        ];
    }

    /**
     * Use the slug in web addresses.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * @return HasMany<ListingPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(ListingPhoto::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The first photo, used on cards.
     *
     * @return HasOne<ListingPhoto, $this>
     */
    public function coverPhoto(): HasOne
    {
        return $this->hasOne(ListingPhoto::class)->ofMany(['sort_order' => 'min', 'id' => 'min']);
    }

    /**
     * Open houses, soonest first.
     *
     * @return HasMany<OpenHouse, $this>
     */
    public function openHouses(): HasMany
    {
        return $this->hasMany(OpenHouse::class)->orderBy('starts_at');
    }

    /**
     * The next open house that hasn't finished.
     *
     * @return HasOne<OpenHouse, $this>
     */
    public function nextOpenHouse(): HasOne
    {
        return $this->hasOne(OpenHouse::class)->ofMany(['starts_at' => 'min'], fn (Builder $query) => $query->where('ends_at', '>', now()));
    }

    /**
     * How much the price was cut, when it went down in the last 60 days.
     */
    public function priceCut(): ?int
    {
        if ($this->previous_price === null || $this->previous_price <= $this->price || $this->price_changed_at?->lt(now()->subDays(60))) {
            return null;
        }

        return $this->previous_price - $this->price;
    }

    /**
     * Whether it was submitted by a private owner (for sale by owner).
     */
    public function isOwnerSubmission(): bool
    {
        return $this->source === 'owner';
    }

    /**
     * @return HasMany<Enquiry, $this>
     */
    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class);
    }

    /**
     * Visitors who saved this home.
     *
     * @return BelongsToMany<User, $this>
     */
    public function savedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')->withTimestamps();
    }

    /**
     * Listings visitors may see.
     *
     * @param  Builder<Listing>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true)->whereHas('agent', fn (Builder $agent) => $agent->where('is_active', true));
    }

    /**
     * Eager load what a listing card needs.
     *
     * @param  Builder<Listing>  $query
     */
    #[Scope]
    protected function forCards(Builder $query): void
    {
        $query->with(['city', 'coverPhoto', 'nextOpenHouse']);
    }

    /**
     * Listings featured right now (a paid feature ends on its date).
     *
     * @param  Builder<Listing>  $query
     */
    #[Scope]
    protected function featuredNow(Builder $query): void
    {
        $query->where('is_featured', true)
            ->where(fn (Builder $when) => $when->whereNull('featured_until')->orWhere('featured_until', '>', now()));
    }

    /**
     * Apply the search filters a visitor chose.
     *
     * @param  Builder<Listing>  $query
     * @param  array<string, mixed>  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['q'] ?? ''));

        if ($search !== '') {
            // "Austin, TX" should match the city Austin.
            $terms = array_values(array_filter(array_map('trim', explode(',', $search))));
            $first = $terms[0] ?? $search;

            $query->where(function (Builder $where) use ($first, $search): void {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $first).'%';

                $where->where('address', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%')
                    ->orWhere('address', 'like', $like)
                    ->orWhere('neighborhood', 'like', $like)
                    ->orWhere('zip', $first)
                    ->orWhere('title', 'like', $like)
                    ->orWhereHas('city', fn (Builder $city) => $city->where('name', 'like', $like)->orWhere('state_code', $first)->orWhere('state', 'like', $like));
            });
        }

        $status = ListingStatus::tryFrom((string) ($filters['status'] ?? ''));

        if ($status !== null) {
            $query->where('status', $status);
        }

        $types = array_values(array_intersect((array) ($filters['types'] ?? []), array_keys(HomeType::options())));

        if ($types !== []) {
            $query->whereIn('property_type', $types);
        }

        if (filled($filters['city'] ?? null)) {
            $query->whereHas('city', fn (Builder $city) => $city->where('slug', $filters['city']));
        }

        if (filled($filters['neighborhood'] ?? null)) {
            $query->where('neighborhood', $filters['neighborhood']);
        }

        if ((int) ($filters['min_price'] ?? 0) > 0) {
            $query->where('price', '>=', (int) $filters['min_price']);
        }

        if ((int) ($filters['max_price'] ?? 0) > 0) {
            $query->where('price', '<=', (int) $filters['max_price']);
        }

        if ((int) ($filters['beds'] ?? 0) > 0) {
            $query->where('beds', '>=', (int) $filters['beds']);
        }

        if ((float) ($filters['baths'] ?? 0) > 0) {
            $query->where('baths', '>=', (float) $filters['baths']);
        }

        $area = self::parseArea((string) ($filters['area'] ?? ''));

        if ($area !== []) {
            $lats = array_column($area, 0);
            $lngs = array_column($area, 1);

            // Narrow to the box around the shape in SQL, then keep only homes inside the shape.
            $inside = self::query()
                ->whereBetween('latitude', [min($lats), max($lats)])
                ->whereBetween('longitude', [min($lngs), max($lngs)])
                ->get(['id', 'latitude', 'longitude'])
                ->filter(fn (Listing $listing) => self::pointInArea((float) $listing->latitude, (float) $listing->longitude, $area))
                ->modelKeys();

            $query->whereKey($inside);
        }
    }

    /**
     * Read a drawn area ("30.27,-97.74;30.29,-97.70;…") into a list of [lat, lng] corners.
     * Anything malformed gives an empty list, which means "no area".
     *
     * @return list<array{0: float, 1: float}>
     */
    public static function parseArea(string $area): array
    {
        $corners = [];

        foreach (array_slice(explode(';', $area), 0, 60) as $pair) {
            $parts = explode(',', $pair);

            if (count($parts) !== 2 || ! is_numeric($parts[0]) || ! is_numeric($parts[1])) {
                return [];
            }

            [$lat, $lng] = [(float) $parts[0], (float) $parts[1]];

            if (abs($lat) > 90 || abs($lng) > 180) {
                return [];
            }

            $corners[] = [$lat, $lng];
        }

        return count($corners) >= 3 ? $corners : [];
    }

    /**
     * Whether a point lies inside the drawn shape (ray casting).
     *
     * @param  list<array{0: float, 1: float}>  $area
     */
    public static function pointInArea(float $lat, float $lng, array $area): bool
    {
        $inside = false;
        $count = count($area);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$latI, $lngI] = $area[$i];
            [$latJ, $lngJ] = $area[$j];

            if (($lngI > $lng) !== ($lngJ > $lng) && $lat < ($latJ - $latI) * ($lng - $lngI) / ($lngJ - $lngI) + $latI) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /**
     * Order the results the way the visitor chose.
     *
     * @param  Builder<Listing>  $query
     */
    #[Scope]
    protected function sortBy(Builder $query, ?string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy('price')->orderByDesc('id'),
            'price_desc' => $query->orderByDesc('price')->orderByDesc('id'),
            'beds' => $query->orderByDesc('beds')->orderByDesc('id'),
            'sqft' => $query->orderByDesc('sqft')->orderByDesc('id'),
            default => $query->orderByDesc('is_featured')->orderByDesc('listed_at')->orderByDesc('id'),
        };
    }

    /**
     * Turn a title and address into a unique web address.
     */
    public static function uniqueSlug(string $text, ?int $ignoreId = null): string
    {
        $base = Str::slug($text) ?: 'home';
        $slug = $base;
        $counter = 2;

        while (static::query()->where('slug', $slug)->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * "$1,250,000", or "$3,200/mo" for rentals.
     */
    public function priceLabel(): string
    {
        return '$'.number_format($this->price).($this->status->isRental() ? '/mo' : '');
    }

    /**
     * A short price for map markers: "$1.25M", "$850K", "$3.2K/mo".
     */
    public function shortPrice(): string
    {
        $price = $this->price;

        $short = match (true) {
            $price >= 1_000_000 => rtrim(rtrim(number_format($price / 1_000_000, 2), '0'), '.').'M',
            $price >= 10_000 => round($price / 1000).'K',
            $price >= 1000 => rtrim(rtrim(number_format($price / 1000, 1), '0'), '.').'K',
            default => (string) $price,
        };

        return '$'.$short.($this->status->isRental() ? '/mo' : '');
    }

    /**
     * The key facts as short phrases: "3 bd", "2.5 ba", "1,850 sqft".
     *
     * @return list<string>
     */
    public function facts(): array
    {
        return array_values(array_filter([
            $this->beds !== null ? ($this->beds === 0 ? 'Studio' : $this->beds.' bd') : null,
            $this->baths !== null ? $this->bathsLabel().' ba' : null,
            $this->sqft ? number_format($this->sqft).' sqft' : null,
            $this->lot_acres && $this->kind() === PropertyType::Land ? rtrim(rtrim((string) $this->lot_acres, '0'), '.').' acre lot' : null,
        ]));
    }

    /**
     * "2.5" or "2" (no trailing ".0").
     */
    public function bathsLabel(): string
    {
        return rtrim(rtrim((string) $this->baths, '0'), '.');
    }

    /**
     * "Austin, TX 78704".
     */
    public function locality(): string
    {
        return "{$this->city->name}, {$this->city->state_code} {$this->zip}";
    }

    /**
     * "1204 Bluebonnet Ln, Austin, TX 78704".
     */
    public function fullAddress(): string
    {
        return "{$this->address}, {$this->locality()}";
    }

    /**
     * Price per square foot for homes for sale.
     */
    public function pricePerSqft(): ?int
    {
        return $this->sqft && ! $this->status->isRental() ? (int) round($this->price / $this->sqft) : null;
    }

    /**
     * How long ago the home was listed, e.g. "3 days on site".
     */
    public function daysOnSite(): int
    {
        return (int) max(0, $this->listed_at->diffInDays(now()));
    }

    /**
     * Whether it was listed in the last week.
     */
    public function isNew(): bool
    {
        return $this->status->isAvailable() && $this->daysOnSite() <= 7;
    }

    /**
     * Get the public URL of the cover photo, if there is one.
     */
    public function coverUrl(bool $small = true): ?string
    {
        return $this->coverPhoto?->url($small);
    }

    /**
     * The admin's home type for this listing.
     *
     * @return BelongsTo<HomeType, $this>
     */
    public function homeType(): BelongsTo
    {
        return $this->belongsTo(HomeType::class, 'property_type', 'slug');
    }

    /**
     * What kind of home it is (house, condo, land…), from its home type.
     */
    public function kind(): PropertyType
    {
        $type = HomeType::cachedBySlug()->get($this->property_type);

        if ($type instanceof HomeType) {
            return $type->kind;
        }

        return PropertyType::tryFrom($this->property_type) ?? PropertyType::House;
    }

    /**
     * The home type's name, e.g. "Townhouse".
     */
    public function typeLabel(): string
    {
        $type = HomeType::cachedBySlug()->get($this->property_type);

        return $type instanceof HomeType ? $type->name : $this->kind()->label();
    }

    /**
     * Whether it is featured right now (paid features end on their date).
     */
    public function isFeaturedNow(): bool
    {
        return $this->is_featured && ($this->featured_until === null || $this->featured_until->isFuture());
    }

    /**
     * Whether the home came from the RentCast listings feed.
     */
    public function isFromApi(): bool
    {
        return ! in_array($this->source, ['manual', 'owner'], true);
    }

    /**
     * A drawing to show on cards when the home has no photos yet (feed homes come without photos).
     */
    public function placeholderUrl(): string
    {
        $art = match ($this->kind()) {
            PropertyType::Condo, PropertyType::Apartment => 'condo',
            PropertyType::Townhouse, PropertyType::MultiFamily => 'townhouse',
            PropertyType::Land => 'land',
            default => 'house',
        };

        return asset("images/homes/{$art}.svg");
    }

    /**
     * Whether the listing has a map position.
     */
    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * The data a map marker needs.
     *
     * @return array{id: int, lat: float, lng: float, price: string, title: string, url: string, photo: string|null, facts: string}
     */
    public function mapPoint(): array
    {
        return [
            'id' => $this->id,
            'lat' => (float) $this->latitude,
            'lng' => (float) $this->longitude,
            'price' => $this->shortPrice(),
            'title' => $this->address,
            'url' => route('listings.show', $this),
            'photo' => $this->coverUrl(),
            'facts' => implode(' · ', $this->facts()),
        ];
    }

    /**
     * Get every stored image path (both copies) so the files can be deleted.
     *
     * @return list<string>
     */
    public function imagePaths(): array
    {
        return array_values($this->photos->flatMap(fn (ListingPhoto $photo): array => $photo->paths())->all());
    }
}
