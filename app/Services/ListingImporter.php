<?php

namespace App\Services;

use App\Enums\ListingStatus;
use App\Enums\PropertyType;
use App\Models\Agent;
use App\Models\City;
use App\Models\Listing;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Saves RentCast listings as website listings.
 *
 * New homes are given to the agent who covers their ZIP code or city (or the default agent).
 * Homes the admin has edited are never overwritten, so a home marked as sold stays sold.
 */
class ListingImporter
{
    public const string SOURCE = 'rentcast';

    /**
     * US states by their two-letter code.
     *
     * @var array<string, string>
     */
    public const array STATES = [
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California', 'CO' => 'Colorado',
        'CT' => 'Connecticut', 'DE' => 'Delaware', 'DC' => 'District of Columbia', 'FL' => 'Florida', 'GA' => 'Georgia',
        'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
        'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland', 'MA' => 'Massachusetts',
        'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana',
        'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico',
        'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
        'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina', 'SD' => 'South Dakota',
        'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington',
        'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming', 'PR' => 'Puerto Rico',
    ];

    public function __construct(private RentCast $rentCast)
    {
        //
    }

    /**
     * Fetch and save homes for a ZIP code or a city.
     *
     * @param  array<string, string>  $where  ['zipCode' => '78704'] or ['city' => 'Austin', 'state' => 'TX']
     * @param  list<'sale'|'rent'>  $types
     * @return array{created: int, updated: int, kept: int, skipped: int}
     *
     * @throws RuntimeException
     */
    public function import(array $where, array $types = ['sale', 'rent']): array
    {
        $totals = ['created' => 0, 'updated' => 0, 'kept' => 0, 'skipped' => 0];

        foreach ($types as $type) {
            $status = $type === 'rent' ? ListingStatus::ForRent : ListingStatus::ForSale;

            foreach ($this->rentCast->listings($type, $where) as $record) {
                $totals[$this->save($record, $status)]++;
            }
        }

        return $totals;
    }

    /**
     * Save one RentCast record and say what happened.
     *
     * @param  array<string, mixed>  $record
     * @return 'created'|'updated'|'kept'|'skipped'
     */
    public function save(array $record, ListingStatus $status): string
    {
        $externalId = (string) ($record['id'] ?? '');
        $address = trim((string) ($record['addressLine1'] ?? ''));
        $price = (int) round((float) ($record['price'] ?? 0));
        $stateCode = strtoupper((string) ($record['state'] ?? ''));
        $cityName = trim((string) ($record['city'] ?? ''));
        $zip = substr((string) ($record['zipCode'] ?? ''), 0, 5);

        if ($externalId === '' || $address === '' || $price <= 0 || $cityName === '' || ! isset(self::STATES[$stateCode]) || ! preg_match('/^\d{5}$/', $zip)) {
            return 'skipped';
        }

        $listing = Listing::query()->where('external_id', $externalId)->first();

        if ($listing?->edited_by_admin) {
            $listing->forceFill(['synced_at' => now()])->save();

            return 'kept';
        }

        $city = $this->city($cityName, $stateCode, $record);
        $agent = $listing?->agent ?? $this->agentFor($zip, $city);

        if ($agent === null) {
            throw new RuntimeException('Add at least one active agent before importing homes.');
        }

        $type = $this->propertyType((string) ($record['propertyType'] ?? ''));
        $fullAddress = trim($address.' '.($record['addressLine2'] ?? ''));
        $isActive = ($record['status'] ?? 'Active') === 'Active';

        $attributes = [
            'source' => self::SOURCE,
            'external_id' => $externalId,
            'agent_id' => $agent->id,
            'city_id' => $city->id,
            'title' => $this->title($record, $type, $city, $status),
            'status' => $status,
            'property_type' => $type,
            'price' => $price,
            'beds' => isset($record['bedrooms']) ? (int) $record['bedrooms'] : null,
            'baths' => isset($record['bathrooms']) ? round((float) $record['bathrooms'] * 2) / 2 : null,
            'sqft' => isset($record['squareFootage']) ? (int) $record['squareFootage'] : null,
            'lot_acres' => isset($record['lotSize']) && $record['lotSize'] > 0 ? round($record['lotSize'] / 43560, 2) : null,
            'year_built' => isset($record['yearBuilt']) ? (int) $record['yearBuilt'] : null,
            'address' => $fullAddress,
            'zip' => $zip,
            'latitude' => $record['latitude'] ?? null,
            'longitude' => $record['longitude'] ?? null,
            'description' => $this->description($record, $type, $city, $status),
            'hoa_fee' => isset($record['hoa']['fee']) ? (int) $record['hoa']['fee'] : null,
            'mls_name' => $record['mlsName'] ?? null,
            'mls_number' => $record['mlsNumber'] ?? null,
            'listing_office' => $record['listingOffice']['name'] ?? null,
            'is_published' => $isActive,
            'listed_at' => $this->date($record['listedDate'] ?? null) ?? today(),
        ];

        if ($listing === null) {
            Listing::query()->create([
                ...$attributes,
                'slug' => Listing::uniqueSlug((string) ($record['formattedAddress'] ?? "{$fullAddress} {$cityName} {$stateCode} {$zip}")),
                'amenities' => [],
                'is_featured' => false,
            ])->forceFill(['synced_at' => now()])->save();

            return 'created';
        }

        $listing->fill($attributes)->forceFill(['synced_at' => now()])->save();

        return 'updated';
    }

    /**
     * The city a home belongs to, created the first time it is seen.
     *
     * @param  array<string, mixed>  $record
     */
    private function city(string $name, string $stateCode, array $record): City
    {
        $existing = City::query()->where('name', $name)->where('state_code', $stateCode)->first();

        if ($existing) {
            return $existing;
        }

        $slug = Str::slug($name);

        if (City::query()->where('slug', $slug)->exists()) {
            $slug = Str::slug("{$name} {$stateCode}");
        }

        return City::query()->create([
            'name' => $name,
            'slug' => $slug,
            'state' => self::STATES[$stateCode],
            'state_code' => $stateCode,
            'latitude' => $record['latitude'] ?? 39.5,
            'longitude' => $record['longitude'] ?? -98.35,
            'is_featured' => false,
            'sort_order' => (int) City::query()->max('sort_order') + 1,
        ]);
    }

    /**
     * The agent who covers this ZIP code, else this city, else the default agent.
     */
    public function agentFor(string $zip, City $city): ?Agent
    {
        $active = Agent::query()->active();

        return (clone $active)->get()->first(fn (Agent $agent) => in_array($zip, $agent->zipCodeList(), true))
            ?? (clone $active)->whereHas('cities', fn ($query) => $query->whereKey($city->id))->orderBy('id')->first()
            ?? (clone $active)->whereKey((int) Setting::get('default_agent_id'))->first()
            ?? (clone $active)->orderBy('id')->first();
    }

    private function propertyType(string $type): PropertyType
    {
        return match ($type) {
            'Condo' => PropertyType::Condo,
            'Townhouse' => PropertyType::Townhouse,
            'Apartment' => PropertyType::Apartment,
            'Multi-Family' => PropertyType::MultiFamily,
            'Land' => PropertyType::Land,
            default => PropertyType::House,
        };
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function title(array $record, PropertyType $type, City $city, ListingStatus $status): string
    {
        $beds = isset($record['bedrooms']) && $type !== PropertyType::Land
            ? ((int) $record['bedrooms'] === 0 ? 'Studio ' : (int) $record['bedrooms'].'-bedroom ')
            : '';

        return Str::ucfirst($beds.strtolower($type->label())).($status->isRental() ? ' for rent' : '').' in '.$city->name;
    }

    /**
     * A plain, factual description built from the listing data (RentCast does not send one).
     *
     * @param  array<string, mixed>  $record
     */
    private function description(array $record, PropertyType $type, City $city, ListingStatus $status): string
    {
        $facts = array_filter([
            isset($record['bedrooms']) ? ((int) $record['bedrooms'] === 0 ? 'a studio layout' : (int) $record['bedrooms'].' '.Str::plural('bedroom', (int) $record['bedrooms'])) : null,
            isset($record['bathrooms']) ? rtrim(rtrim(number_format((float) $record['bathrooms'], 1), '0'), '.').' '.Str::plural('bathroom', (float) $record['bathrooms'] === 1.0 ? 1 : 2) : null,
            isset($record['squareFootage']) ? number_format((int) $record['squareFootage']).' sq ft of living space' : null,
        ]);

        $lines = [
            Str::ucfirst(strtolower($type->label())).' '.($status->isRental() ? 'for rent' : 'for sale').' at '.($record['formattedAddress'] ?? $record['addressLine1']).'.'
                .($facts ? ' It has '.Str::replaceLast(', ', ' and ', implode(', ', $facts)).'.' : ''),
        ];

        if (isset($record['yearBuilt'])) {
            $lines[] = 'Built in '.(int) $record['yearBuilt'].'.';
        }

        if (isset($record['hoa']['fee'])) {
            $lines[] = 'HOA fee: $'.number_format((int) $record['hoa']['fee']).' a month.';
        }

        $lines[] = "Contact us to book a tour or to get more details and photos of this {$city->name} home.";

        return implode(' ', $lines);
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
