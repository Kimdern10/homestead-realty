<?php

namespace Database\Seeders;

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Enums\ListingStatus;
use App\Enums\PropertyType;
use App\Enums\UserRole;
use App\Models\Agent;
use App\Models\City;
use App\Models\Enquiry;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use App\Support\ImageOptimizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fills an empty website with realistic demo cities, agents, homes and enquiries.
 *
 * Photos come from database/seeders/photos (free Unsplash images). Demo logins use the
 * password "password"; in production they get random passwords.
 */
class SampleDataSeeder extends Seeder
{
    /**
     * @var array<string, array{name: string, state: string, code: string, lat: float, lng: float, factor: float, tagline: string, description: string, streets: list<string>, areas: list<array{0: string, 1: float, 2: float, 3: string}>}>
     */
    private const array CITIES = [
        'austin' => [
            'name' => 'Austin', 'state' => 'Texas', 'code' => 'TX', 'lat' => 30.2672, 'lng' => -97.7431, 'factor' => 1.0,
            'tagline' => 'Live music, hill country views and a booming tech scene.',
            'description' => "Austin mixes small-town friendliness with big-city energy. Neighborhoods like Travis Heights and Hyde Park offer tree-lined streets and classic bungalows, while Mueller and East Austin are full of new builds, parks and coffee shops.\n\nWith no state income tax, Lady Bird Lake on your doorstep and some of the best tacos in the country, it's easy to see why so many people move here every year.",
            'streets' => ['Bluebonnet Ln', 'Live Oak St', 'Cedar Ridge Dr', 'Pecan Grove Rd', 'Mockingbird Ln', 'Riverview Ave', 'Barton Hills Dr', 'Sunset Trail'],
            'areas' => [['Travis Heights', 30.2480, -97.7430, '78704'], ['Mueller', 30.2980, -97.7050, '78723'], ['Hyde Park', 30.3060, -97.7280, '78751'], ['Zilker', 30.2620, -97.7690, '78704'], ['East Austin', 30.2620, -97.7240, '78702']],
        ],
        'miami' => [
            'name' => 'Miami', 'state' => 'Florida', 'code' => 'FL', 'lat' => 25.7617, 'lng' => -80.1918, 'factor' => 1.25,
            'tagline' => 'Sunshine, ocean views and year-round outdoor living.',
            'description' => "Miami is all about light, water and culture. Brickell's high-rises offer resort-style condos a short walk from the bay, while Coconut Grove's leafy streets hide some of the city's most private family homes.\n\nExpect warm winters, a world-class food scene and international flights from right on your doorstep.",
            'streets' => ['Bayshore Dr', 'Palm Ave', 'Coral Way', 'Biscayne Pl', 'Hibiscus St', 'Seabreeze Ct', 'Mango Ln', 'Ocean View Blvd'],
            'areas' => [['Brickell', 25.7600, -80.1930, '33131'], ['Coconut Grove', 25.7280, -80.2440, '33133'], ['Edgewater', 25.7960, -80.1880, '33137'], ['Little Havana', 25.7660, -80.2190, '33135'], ['Wynwood', 25.8010, -80.1990, '33127']],
        ],
        'denver' => [
            'name' => 'Denver', 'state' => 'Colorado', 'code' => 'CO', 'lat' => 39.7392, 'lng' => -104.9903, 'factor' => 1.1,
            'tagline' => 'Mountain views, 300 days of sunshine and easy weekend escapes.',
            'description' => "Denver puts the Rocky Mountains less than an hour away. Washington Park and Park Hill are known for brick homes and big parks, while LoHi and the Highlands have modern townhomes close to restaurants and breweries.\n\nSki in the winter, hike in the summer, and enjoy a city that's young, active and growing fast.",
            'streets' => ['Aspen Way', 'Columbine St', 'Summit View Dr', 'Larkspur Ln', 'Pine Ridge Ct', 'Elk Creek Rd', 'Juniper St', 'Alpine Ave'],
            'areas' => [['LoHi', 39.7580, -105.0110, '80211'], ['Washington Park', 39.7000, -104.9700, '80209'], ['Capitol Hill', 39.7320, -104.9800, '80203'], ['Highlands', 39.7620, -105.0300, '80212'], ['Park Hill', 39.7500, -104.9200, '80207']],
        ],
        'nashville' => [
            'name' => 'Nashville', 'state' => 'Tennessee', 'code' => 'TN', 'lat' => 36.1627, 'lng' => -86.7816, 'factor' => 0.95,
            'tagline' => 'Music City charm with a fast-growing downtown.',
            'description' => "Nashville is famous for music, but people stay for the neighborhoods. East Nashville has craftsman homes and local shops, 12 South is walkable and lively, and Sylvan Park offers quiet streets close to downtown.\n\nTennessee has no state income tax on wages, and the cost of living is still friendlier than most big cities.",
            'streets' => ['Magnolia Dr', 'Dogwood Ln', 'Belmont Ave', 'Shelby St', 'Riverside Dr', 'Cumberland Ct', 'Hillcrest Rd', 'Fatherland St'],
            'areas' => [['East Nashville', 36.1800, -86.7500, '37206'], ['12 South', 36.1250, -86.7900, '37204'], ['Germantown', 36.1800, -86.7900, '37208'], ['Sylvan Park', 36.1450, -86.8450, '37209'], ['The Gulch', 36.1520, -86.7890, '37203']],
        ],
        'seattle' => [
            'name' => 'Seattle', 'state' => 'Washington', 'code' => 'WA', 'lat' => 47.6062, 'lng' => -122.3321, 'factor' => 1.45,
            'tagline' => 'Water, mountains and evergreen neighborhoods.',
            'description' => "Seattle sits between Puget Sound and the Cascade Mountains. Ballard and Fremont have a creative, village feel, Queen Anne offers views of the Space Needle and the Sound, and Capitol Hill is the heart of the city's nightlife.\n\nHomes here hold their value well, and there's no state income tax.",
            'streets' => ['Evergreen Pl', 'Harbor View Ave', 'Cascade St', 'Madrona Dr', 'Puget Way', 'Hemlock Ct', 'Salmon Bay Rd', 'Rainier Vista'],
            'areas' => [['Capitol Hill', 47.6250, -122.3210, '98102'], ['Ballard', 47.6680, -122.3840, '98107'], ['Queen Anne', 47.6370, -122.3570, '98109'], ['Fremont', 47.6510, -122.3500, '98103'], ['Columbia City', 47.5600, -122.2870, '98118']],
        ],
        'chicago' => [
            'name' => 'Chicago', 'state' => 'Illinois', 'code' => 'IL', 'lat' => 41.8781, 'lng' => -87.6298, 'factor' => 0.9,
            'tagline' => 'World-class architecture, lakefront parks and great value.',
            'description' => "Chicago gives you big-city living for less. Lincoln Park and Wicker Park are full of classic greystones and brick two-flats, River North has modern high-rise condos, and Logan Square is known for its boulevards and food scene.\n\nThe lakefront trail, the 'L' train and four real seasons make every neighborhood feel connected.",
            'streets' => ['Fullerton Pkwy', 'Armitage Ave', 'Wolcott Ave', 'Kedzie Blvd', 'Lakeview Ter', 'Damen Ave', 'Belden Ave', 'Kenwood Ave'],
            'areas' => [['Lincoln Park', 41.9210, -87.6510, '60614'], ['Wicker Park', 41.9090, -87.6770, '60622'], ['Logan Square', 41.9230, -87.7090, '60647'], ['River North', 41.8920, -87.6340, '60654'], ['Hyde Park', 41.7940, -87.5900, '60615']],
        ],
        'phoenix' => [
            'name' => 'Phoenix', 'state' => 'Arizona', 'code' => 'AZ', 'lat' => 33.4484, 'lng' => -112.0740, 'factor' => 0.85,
            'tagline' => 'Desert sunsets, pools and room to spread out.',
            'description' => "Phoenix offers sunshine almost every day of the year. Arcadia is loved for its citrus trees and mid-century ranch homes, Biltmore for its golf courses and resorts, and Desert Ridge for newer homes close to shopping and hiking trails.\n\nBig lots, backyard pools and lower prices than the coast make it a favorite for families and retirees.",
            'streets' => ['Saguaro Dr', 'Camelback Rd', 'Desert Willow Ln', 'Palo Verde Way', 'Mesquite Ct', 'Sonoran Trail', 'Ocotillo Rd', 'Canyon View Dr'],
            'areas' => [['Arcadia', 33.4990, -111.9750, '85018'], ['Biltmore', 33.5100, -112.0280, '85016'], ['Roosevelt Row', 33.4590, -112.0700, '85004'], ['Ahwatukee', 33.3280, -111.9840, '85044'], ['Desert Ridge', 33.6790, -111.9750, '85050']],
        ],
        'charlotte' => [
            'name' => 'Charlotte', 'state' => 'North Carolina', 'code' => 'NC', 'lat' => 35.2271, 'lng' => -80.8431, 'factor' => 0.8,
            'tagline' => 'A friendly banking hub with leafy, walkable neighborhoods.',
            'description' => "Charlotte is one of the fastest-growing cities in the South. Myers Park and Dilworth are known for grand old trees and historic homes, South End for new apartments along the light rail, and NoDa for its arts scene.\n\nMild winters and easy drives to both the mountains and the beach are a big part of the appeal.",
            'streets' => ['Queens Rd', 'Providence Rd', 'Selwyn Ave', 'Park Rd', 'Magnolia Ave', 'Laurel Ave', 'Kenilworth Ave', 'Belmont Ave'],
            'areas' => [['South End', 35.2130, -80.8580, '28203'], ['Dilworth', 35.2060, -80.8500, '28203'], ['NoDa', 35.2470, -80.8080, '28205'], ['Myers Park', 35.1910, -80.8200, '28207'], ['Plaza Midwood', 35.2220, -80.8070, '28205']],
        ],
    ];

    /**
     * @var list<array{name: string, title: string, cities: list<string>, years: int, sold: int, languages: string, phone: string, bio: string}>
     */
    private const array AGENTS = [
        ['name' => 'Sarah Mitchell', 'title' => 'Broker Associate', 'cities' => ['austin'], 'years' => 14, 'sold' => 312, 'languages' => 'English', 'phone' => '(512) 555-0187', 'bio' => "Sarah has helped more than 300 families buy and sell homes across Austin. She grew up in Travis Heights and knows every street, school and taco truck in South Austin.\n\nBuyers love her honest advice and sellers love her pricing: her listings sell in an average of 18 days."],
        ['name' => 'Elena Rodríguez', 'title' => 'Luxury Condo Specialist', 'cities' => ['miami'], 'years' => 11, 'sold' => 186, 'languages' => 'English and Spanish', 'phone' => '(305) 555-0142', 'bio' => "Elena specializes in Brickell and Edgewater condos and waterfront homes in Coconut Grove. She works with local buyers and clients relocating from Latin America and Europe.\n\nShe'll walk you through HOA rules, flood insurance and everything else that's different about buying in Miami."],
        ['name' => 'James Carter', 'title' => 'Realtor®', 'cities' => ['denver', 'phoenix'], 'years' => 9, 'sold' => 124, 'languages' => 'English', 'phone' => '(720) 555-0119', 'bio' => "James splits his time between Denver and Phoenix, helping clients move between the mountains and the desert. Before real estate he was a builder, so he knows what to look for in an inspection.\n\nWeekends you'll find him on a trail or at an open house."],
        ['name' => 'Olivia Brooks', 'title' => 'Realtor®', 'cities' => ['nashville', 'charlotte'], 'years' => 7, 'sold' => 88, 'languages' => 'English', 'phone' => '(615) 555-0163', 'bio' => "Olivia is a relocation specialist for Nashville and Charlotte. Many of her clients are moving for work, so she's an expert at video tours and closing deals from a distance.\n\nShe answers messages fast and always explains each step in plain language."],
        ['name' => 'David Chen', 'title' => 'Managing Broker', 'cities' => ['seattle'], 'years' => 16, 'sold' => 405, 'languages' => 'English and Mandarin', 'phone' => '(206) 555-0128', 'bio' => "David leads our Seattle team. He has sold homes in every neighborhood from Ballard to Columbia City and is known for calm, careful negotiation in a competitive market.\n\nHe's also a first-time-buyer favorite: he runs a free homebuying class every month."],
        ['name' => 'Marcus Johnson', 'title' => 'Realtor®', 'cities' => ['chicago'], 'years' => 12, 'sold' => 231, 'languages' => 'English', 'phone' => '(312) 555-0174', 'bio' => "Marcus is a Chicago native who knows the city's greystones, two-flats and high-rises inside out. He helps investors find multi-family buildings and families find their first single-family home.\n\nHe has served on the board of the local Realtors' association for five years."],
    ];

    /**
     * Listings per city: [status, type, beds, baths, sqft, base price (sale) or rent].
     *
     * @var list<array{0: ListingStatus, 1: PropertyType, 2: int|null, 3: float|null, 4: int|null, 5: int}>
     */
    private const array MIX = [
        [ListingStatus::ForSale, PropertyType::House, 4, 3.0, 2650, 685000],
        [ListingStatus::ForSale, PropertyType::House, 3, 2.0, 1780, 489000],
        [ListingStatus::ForSale, PropertyType::Condo, 2, 2.0, 1150, 415000],
        [ListingStatus::ForSale, PropertyType::Townhouse, 3, 2.5, 1920, 545000],
        [ListingStatus::Pending, PropertyType::House, 5, 4.0, 3480, 1150000],
        [ListingStatus::ForRent, PropertyType::Apartment, 1, 1.0, 760, 1850],
        [ListingStatus::ForRent, PropertyType::House, 3, 2.0, 1650, 2950],
        [ListingStatus::Sold, PropertyType::House, 3, 2.0, 1540, 452000],
    ];

    private const array HOUSE_STYLES = ['craftsman bungalow', 'modern farmhouse', 'mid-century ranch', 'two-story colonial', 'contemporary home', 'Spanish-style home', 'brick traditional'];

    private const array OPENERS = [
        'Welcome home to this light-filled %s in the heart of %s.',
        'Fall in love with this beautifully updated %s on a quiet, tree-lined street in %s.',
        'Move-in ready %s just minutes from the best of %s.',
        'Rarely available %s in sought-after %s.',
    ];

    /** @var array<string, list<array{path: string, width: int, height: int}>> */
    private array $photoPool = [];

    private int $photoCursor = 0;

    /**
     * Run the database seeds.
     */
    public function run(ImageOptimizer $images): void
    {
        if (Listing::query()->exists()) {
            $this->command?->warn('Listings already exist, so the sample data was not added.');

            return;
        }

        $this->command?->info('Optimizing sample photos (this takes a minute)…');
        $this->preparePhotos($images);

        $password = app()->isProduction() ? Str::password(24) : 'password';

        if (! app()->isProduction()) {
            User::query()->firstOrCreate(['email' => 'admin@homesteadrealty.example'], [
                'name' => 'Office Admin',
                'password' => $password,
            ])->forceFill(['role' => UserRole::Admin, 'email_verified_at' => now()])->save();
        }

        $cities = collect(self::CITIES)->map(function (array $data, string $slug) {
            return City::query()->create([
                'name' => $data['name'],
                'slug' => $slug,
                'state' => $data['state'],
                'state_code' => $data['code'],
                'tagline' => $data['tagline'],
                'description' => $data['description'],
                'image_path' => $this->copyPhoto("cities/{$slug}", 'cities')['path'],
                'latitude' => $data['lat'],
                'longitude' => $data['lng'],
                'is_featured' => true,
                'sort_order' => array_search($slug, array_keys(self::CITIES), true),
            ]);
        });

        $agents = collect(self::AGENTS)->map(function (array $data) use ($password, $cities) {
            $email = Str::of(Str::ascii($data['name']))->lower()->replace(' ', '.')->append('@homesteadrealty.example')->toString();

            $user = User::query()->create(['name' => $data['name'], 'email' => $email, 'password' => $password]);
            $user->forceFill(['role' => UserRole::Agent, 'email_verified_at' => now()])->save();

            $agent = Agent::query()->create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'slug' => Str::slug(Str::ascii($data['name'])),
                'title' => $data['title'],
                'phone' => $data['phone'],
                'email' => $email,
                'license_number' => strtoupper(substr($data['cities'][0], 0, 2)).' #'.random_int(1000000, 9999999),
                'years_experience' => $data['years'],
                'languages' => $data['languages'],
                'bio' => $data['bio'],
                'is_active' => true,
            ]);

            $agent->forceFill(['homes_sold' => $data['sold']])->save();
            $agent->cities()->sync($cities->only($data['cities'])->pluck('id')->all());

            return ['agent' => $agent, 'cities' => $data['cities']];
        });

        $listings = collect();
        $counter = 0;

        foreach ($cities as $slug => $city) {
            $data = self::CITIES[$slug];
            $cityAgents = $agents->filter(fn (array $row) => in_array($slug, $row['cities'], true))->pluck('agent')->values();
            $mix = $slug === 'austin' || $slug === 'miami' ? self::MIX : array_slice(self::MIX, 0, 6 + ($counter % 2));

            foreach ($mix as $index => [$status, $type, $beds, $baths, $sqft, $base]) {
                $counter++;
                [$area, $lat, $lng, $zip] = $data['areas'][$index % count($data['areas'])];
                $street = $data['streets'][($index + $counter) % count($data['streets'])];
                $number = (($counter * 379) % 8900) + 100;
                $isUnit = in_array($type, [PropertyType::Condo, PropertyType::Apartment], true);
                $address = $isUnit ? "{$number} {$street} #".(($counter * 7) % 20 + 2).'0'.($counter % 9 + 1) : "{$number} {$street}";
                $wiggle = fn (): float => (mt_rand(-900, 900) / 100000);

                $price = $status->isRental()
                    ? (int) (round($base * $data['factor'] * (0.9 + ($counter % 5) / 20) / 25) * 25)
                    : (int) (round($base * $data['factor'] * (0.92 + ($counter % 7) / 40) / 1000) * 1000);

                $style = $type === PropertyType::House ? self::HOUSE_STYLES[$counter % count(self::HOUSE_STYLES)] : strtolower($type->label());
                $title = Str::ucfirst($style).' in '.$area;
                $soldAt = $status === ListingStatus::Sold ? now()->subDays(10 + $counter % 30) : null;
                $listedDaysAgo = $soldAt !== null ? 60 + $counter : ($counter * 5) % 45;

                $listing = Listing::query()->create([
                    'agent_id' => $cityAgents[$index % $cityAgents->count()]->id,
                    'city_id' => $city->id,
                    'title' => $title,
                    'slug' => Listing::uniqueSlug("{$address} {$data['name']} {$data['code']} {$zip}"),
                    'status' => $status,
                    'property_type' => $type,
                    'price' => $price,
                    'beds' => $beds,
                    'baths' => $baths,
                    'sqft' => (int) round($sqft * (0.9 + ($counter % 4) / 15)),
                    'lot_acres' => $isUnit ? null : round(0.12 + ($counter % 6) * 0.07, 2),
                    'year_built' => $isUnit ? 2008 + ($counter % 16) : 1938 + (($counter * 11) % 86),
                    'garage_spaces' => $isUnit ? ($counter % 2) : 1 + ($counter % 3),
                    'address' => $address,
                    'neighborhood' => $area,
                    'zip' => $zip,
                    'latitude' => round($lat + $wiggle(), 7),
                    'longitude' => round($lng + $wiggle(), 7),
                    'description' => $this->description($style, $area, $data['name'], $beds, $baths, $status, $isUnit),
                    'amenities' => $this->amenities($counter, $isUnit, $slug),
                    'video_url' => $index === 0 ? 'https://www.youtube.com/results?search_query='.urlencode($data['name'].' home tour') : null,
                    'virtual_tour_url' => in_array($index, [0, 2], true) ? 'https://my.matterport.com/' : null,
                    'hoa_fee' => $isUnit ? 250 + ($counter % 6) * 85 : ($type === PropertyType::Townhouse ? 180 : null),
                    'property_tax' => $status->isRental() ? null : (int) round($price * ($slug === 'austin' ? 0.019 : 0.011) / 10) * 10,
                    'is_featured' => $index === 0 || ($index === 6 && $counter % 3 === 0),
                    'is_published' => true,
                    'listed_at' => now()->subDays($listedDaysAgo),
                    'sold_at' => $soldAt,
                ]);

                $listing->forceFill(['views' => 40 + (($counter * 53) % 900)])->save();
                $this->attachPhotos($listing, $isUnit);
                $listings->push($listing);
            }
        }

        // A demo visitor with saved homes and a saved search.
        $member = User::query()->create(['name' => 'Jordan Taylor', 'email' => 'jordan@example.com', 'password' => $password]);
        $member->forceFill(['role' => UserRole::Member, 'email_verified_at' => now()])->save();
        $member->favorites()->attach($listings->where('status', ListingStatus::ForSale)->take(3)->pluck('id'));
        SavedSearch::query()->create(['user_id' => $member->id, 'name' => 'Homes for sale in Austin', 'filters' => ['q' => 'Austin', 'status' => 'for_sale', 'beds' => 3]]);

        $this->enquiries($listings, $member);

        Storage::disk('public')->deleteDirectory('sample-source');

        $this->command?->info("Added {$cities->count()} cities, {$agents->count()} agents and {$listings->count()} listings.");

        if (! app()->isProduction()) {
            $this->command?->info("Demo logins (password \"{$password}\"): admin@homesteadrealty.example, sarah.mitchell@homesteadrealty.example, jordan@example.com");
        }
    }

    /**
     * Turn every sample photo into WebP once; listings then get their own copies.
     */
    private function preparePhotos(ImageOptimizer $images): void
    {
        $source = database_path('seeders/photos');

        foreach (glob($source.'/*', GLOB_ONLYDIR) ?: [] as $folder) {
            $group = basename($folder);

            foreach (glob($folder.'/*.jpg') ?: [] as $file) {
                $key = $group === 'cities' ? 'cities/'.pathinfo($file, PATHINFO_FILENAME) : $group;
                $this->photoPool[$key][] = $images->store($file, 'sample-source');
            }
        }
    }

    /**
     * Copy a prepared photo (both sizes) into its own files and return the new path and size.
     *
     * @return array{path: string, width: int, height: int}
     */
    private function copyPhoto(string $group, string $directory): array
    {
        $pool = $this->photoPool[$group] ?? [];

        if ($pool === []) {
            return ['path' => '', 'width' => 0, 'height' => 0];
        }

        $photo = $pool[$this->photoCursor++ % count($pool)];
        $path = $directory.'/'.Str::lower((string) Str::ulid()).'.webp';
        $disk = Storage::disk('public');

        $disk->copy($photo['path'], $path);
        $disk->copy(Str::replaceLast('.webp', '-sm.webp', $photo['path']), Str::replaceLast('.webp', '-sm.webp', $path));

        return [...$photo, 'path' => $path];
    }

    /**
     * Give a listing an outside photo and a few rooms.
     */
    private function attachPhotos(Listing $listing, bool $isUnit): void
    {
        $groups = [$isUnit ? 'apartment' : 'exterior', 'living', 'kitchen', 'bedroom', 'bathroom'];

        if (! $isUnit) {
            array_splice($groups, 3, 0, ['living']);
            $groups[] = 'bedroom';
            $groups[] = 'exterior';
        }

        foreach ($groups as $order => $group) {
            $photo = $this->copyPhoto($group, 'listings');

            if ($photo['path'] !== '') {
                $listing->photos()->create([...$photo, 'sort_order' => $order]);
            }
        }
    }

    /**
     * A believable description built from the home's facts.
     */
    private function description(string $style, string $area, string $city, ?int $beds, ?float $baths, ListingStatus $status, bool $isUnit): string
    {
        $opener = sprintf(self::OPENERS[crc32($area.$style) % count(self::OPENERS)], $style, $area);
        $bathLabel = rtrim(rtrim(number_format((float) $baths, 1), '0'), '.');

        $rooms = $isUnit
            ? "This {$beds}-bedroom, {$bathLabel}-bath residence has an open living and dining area with floor-to-ceiling windows, a chef's kitchen with quartz counters and stainless-steel appliances, and a private balcony for morning coffee."
            : "Inside you'll find {$beds} bedrooms and {$bathLabel} baths, an open-plan kitchen with a large island, and a living room that flows out to the backyard. The primary suite has a walk-in closet and a spa-style bathroom with a double vanity.";

        $outside = $isUnit
            ? 'Building amenities include a fitness center, a rooftop terrace, secure parking and a 24-hour concierge.'
            : 'Outside, the fenced yard has plenty of room for a garden, play area or summer barbecues, and the attached garage adds extra storage.';

        $closing = match ($status) {
            ListingStatus::ForRent => "Available now on a 12-month lease. Pets considered with a deposit. Schedule a tour today; homes in {$area} go fast.",
            ListingStatus::Sold => "Sold by our {$city} team. Thinking of selling in {$area}? Ask us what your home could be worth.",
            ListingStatus::Pending => 'Under contract, but backup offers are welcome. Contact the agent for details.',
            default => "Just minutes from parks, shops and restaurants in {$area}, with easy access to downtown {$city}. Book a tour before it's gone.",
        };

        return implode("\n\n", [$opener.' '.$rooms, $outside, $closing]);
    }

    /**
     * A sensible set of amenities.
     *
     * @return list<string>
     */
    private function amenities(int $seed, bool $isUnit, string $citySlug): array
    {
        $common = ['Air conditioning', 'Central heating', 'Hardwood floors', 'Updated kitchen', 'In-unit laundry', 'Walk-in closet'];
        $extra = $isUnit
            ? ['Gym', 'Doorman', 'Elevator', 'Rooftop terrace', 'Pet friendly', 'City view', 'EV charger']
            : ['Fireplace', 'Patio or deck', 'Fenced yard', 'Garage', 'Garden', 'Home office', 'Smart home', 'Solar panels'];

        if (in_array($citySlug, ['miami', 'phoenix', 'austin'], true) && ! $isUnit) {
            $extra[] = 'Swimming pool';
        }

        if ($citySlug === 'miami') {
            $extra[] = 'Waterfront';
        }

        return array_values(array_unique([
            ...array_slice($common, 0, 3 + $seed % 3),
            ...Arr::random($extra, min(count($extra), 3 + $seed % 3)),
        ]));
    }

    /**
     * A few messages and tour requests so the dashboard isn't empty.
     *
     * @param  Collection<int, Listing>  $listings
     */
    private function enquiries($listings, User $member): void
    {
        $available = $listings->filter(fn (Listing $listing) => $listing->status->isAvailable())->values();
        $people = [
            ['Ashley Nguyen', 'ashley.nguyen@example.com', '(512) 555-0110'],
            ['Brian Walker', 'brian.walker@example.com', '(305) 555-0131'],
            ['Priya Patel', 'priya.patel@example.com', '(206) 555-0177'],
            ['Michael Lewis', 'michael.lewis@example.com', '(312) 555-0102'],
            ['Grace Kim', 'grace.kim@example.com', '(720) 555-0155'],
            ['Daniel Moore', 'daniel.moore@example.com', null],
        ];

        foreach ($people as $i => [$name, $email, $phone]) {
            $listing = $available[($i * 5) % $available->count()];
            $isTour = $i % 2 === 0;

            Enquiry::query()->create([
                'type' => $isTour ? EnquiryType::Tour : EnquiryType::Message,
                'status' => $i < 3 ? EnquiryStatus::New : ($i < 5 ? EnquiryStatus::Contacted : EnquiryStatus::Closed),
                'listing_id' => $listing->id,
                'agent_id' => $listing->agent_id,
                'name' => $name,
                'email' => $email,
                'phone' => $phone ?? ($isTour ? '(615) 555-0199' : null),
                'message' => $isTour ? 'Could we see it this weekend? We are pre-approved.' : "Hi, is {$listing->address} still available? What are the HOA fees and is the price negotiable?",
                'tour_date' => $isTour ? now()->addDays(2 + $i)->toDateString() : null,
                'tour_time' => $isTour ? array_keys(Enquiry::TOUR_TIMES)[$i % 3] : null,
                'tour_kind' => $isTour ? ($i === 4 ? 'video' : 'in_person') : null,
                'ip_address' => '203.0.113.'.(10 + $i),
            ])->forceFill(['created_at' => now()->subHours(3 + $i * 9)])->save();
        }

        Enquiry::query()->create([
            'type' => EnquiryType::Message,
            'status' => EnquiryStatus::New,
            'listing_id' => $available->first()->id,
            'agent_id' => $available->first()->agent_id,
            'user_id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'message' => 'Does the house have a home office? I work remotely.',
        ]);
    }
}
