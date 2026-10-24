<?php

use App\Enums\ListingStatus;
use App\Enums\PropertyType;
use App\Models\Agent;
use App\Models\City;
use App\Models\Listing;
use App\Models\Setting;
use App\Models\User;
use App\Services\ListingImporter;
use App\Services\RentCast;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    config(['services.rentcast.key' => 'test-key', 'services.rentcast.monthly_limit' => 50]);
});

/**
 * @return array<string, mixed>
 */
function rentCastHome(array $overrides = []): array
{
    return array_merge([
        'id' => '3821-Hargis-St,-Austin,-TX-78723',
        'formattedAddress' => '3821 Hargis St, Austin, TX 78723',
        'addressLine1' => '3821 Hargis St',
        'addressLine2' => null,
        'city' => 'Austin',
        'state' => 'TX',
        'zipCode' => '78723',
        'latitude' => 30.290643,
        'longitude' => -97.701547,
        'propertyType' => 'Single Family',
        'bedrooms' => 4,
        'bathrooms' => 2.5,
        'squareFootage' => 2345,
        'lotSize' => 8712,
        'yearBuilt' => 2008,
        'hoa' => ['fee' => 65],
        'status' => 'Active',
        'price' => 899000,
        'listedDate' => '2026-09-24T00:00:00.000Z',
        'mlsName' => 'UnlockMLS',
        'mlsNumber' => '5519228',
        'listingOffice' => ['name' => 'Gottesman Residential R.E.'],
    ], $overrides);
}

test('homes from RentCast are saved as listings with a new city and the right agent', function () {
    $zipAgent = Agent::factory()->create(['zip_codes' => '78723, 78702']);
    Agent::factory()->create();

    Http::fake([
        'api.rentcast.io/v1/listings/sale*' => Http::response([rentCastHome()]),
        'api.rentcast.io/v1/listings/rental/long-term*' => Http::response([rentCastHome(['id' => 'rent-1', 'addressLine1' => '54 Rainey St', 'addressLine2' => 'Apt 513', 'propertyType' => 'Condo', 'price' => 2450, 'bedrooms' => 2, 'bathrooms' => 2])]),
    ]);

    $result = app(ListingImporter::class)->import(['zipCode' => '78723']);

    expect($result)->toBe(['created' => 2, 'updated' => 0, 'kept' => 0, 'skipped' => 0]);

    $sale = Listing::query()->where('external_id', '3821-Hargis-St,-Austin,-TX-78723')->sole();
    expect($sale->source)->toBe('rentcast')
        ->and($sale->status)->toBe(ListingStatus::ForSale)
        ->and($sale->property_type)->toBe(PropertyType::House)
        ->and($sale->price)->toBe(899000)
        ->and($sale->agent_id)->toBe($zipAgent->id)
        ->and((float) $sale->lot_acres)->toBe(0.2)
        ->and($sale->city->slug)->toBe('austin')
        ->and($sale->city->state)->toBe('Texas')
        ->and($sale->mls_number)->toBe('5519228');

    $rent = Listing::query()->where('external_id', 'rent-1')->sole();
    expect($rent->status)->toBe(ListingStatus::ForRent)
        ->and($rent->address)->toBe('54 Rainey St Apt 513')
        ->and($rent->property_type)->toBe(PropertyType::Condo);

    Http::assertSent(fn ($request) => $request->hasHeader('X-Api-Key', 'test-key') && $request['zipCode'] === '78723' && $request['status'] === 'Active');
    expect(app(RentCast::class)->requestsThisMonth())->toBe(2);
});

test('a city agent gets feed homes when no agent covers the ZIP', function () {
    $city = City::factory()->create(['name' => 'Austin', 'state_code' => 'TX', 'slug' => 'austin']);
    $cityAgent = Agent::factory()->create();
    $cityAgent->cities()->attach($city);
    Agent::factory()->create();

    Http::fake(['api.rentcast.io/*' => Http::response([rentCastHome()])]);

    app(ListingImporter::class)->import(['zipCode' => '78723'], ['sale']);

    expect(Listing::query()->sole()->agent_id)->toBe($cityAgent->id)
        ->and(Listing::query()->sole()->city_id)->toBe($city->id);
});

test('the default agent from the feed settings is used otherwise', function () {
    Agent::factory()->create();
    $default = Agent::factory()->create();
    Setting::put(['default_agent_id' => (string) $default->id]);

    Http::fake(['api.rentcast.io/*' => Http::response([rentCastHome()])]);

    app(ListingImporter::class)->import(['zipCode' => '78723'], ['sale']);

    expect(Listing::query()->sole()->agent_id)->toBe($default->id);
});

test('a home the admin edited is not overwritten by the next import', function () {
    Agent::factory()->create();
    Http::fake(['api.rentcast.io/*' => Http::response([rentCastHome()])]);
    app(ListingImporter::class)->import(['zipCode' => '78723'], ['sale']);

    $listing = Listing::query()->sole();
    Livewire::actingAs(User::factory()->admin()->create())->test('pages::dashboard.listings.index')->call('setStatus', $listing->id, 'sold');

    $record = rentCastHome(['price' => 850000]);
    expect(app(ListingImporter::class)->save($record, ListingStatus::ForSale))->toBe('kept');

    $listing->refresh();
    expect($listing->status)->toBe(ListingStatus::Sold)
        ->and($listing->price)->toBe(899000)
        ->and($listing->edited_by_admin)->toBeTrue();
});

test('an untouched feed home is updated by the next import', function () {
    Agent::factory()->create();

    expect(app(ListingImporter::class)->save(rentCastHome(), ListingStatus::ForSale))->toBe('created')
        ->and(app(ListingImporter::class)->save(rentCastHome(['price' => 850000]), ListingStatus::ForSale))->toBe('updated')
        ->and(Listing::query()->sole()->price)->toBe(850000);
});

test('records with missing details are skipped', function () {
    Agent::factory()->create();

    expect(app(ListingImporter::class)->save(rentCastHome(['price' => null]), ListingStatus::ForSale))->toBe('skipped')
        ->and(app(ListingImporter::class)->save(rentCastHome(['state' => 'XX']), ListingStatus::ForSale))->toBe('skipped')
        ->and(Listing::query()->count())->toBe(0);
});

test('answers are cached so repeating a search uses no request', function () {
    Agent::factory()->create();
    Http::fake(['api.rentcast.io/*' => Http::response([rentCastHome()])]);

    app(ListingImporter::class)->import(['zipCode' => '78723'], ['sale']);
    app(ListingImporter::class)->import(['zipCode' => '78723'], ['sale']);

    Http::assertSentCount(1);
    expect(app(RentCast::class)->requestsThisMonth())->toBe(1);
});

test('no request is sent once the monthly limit is reached', function () {
    config(['services.rentcast.monthly_limit' => 1]);
    Agent::factory()->create();
    Http::fake(['api.rentcast.io/*' => Http::response([rentCastHome()])]);

    app(ListingImporter::class)->import(['zipCode' => '78723'], ['sale']);

    expect(fn () => app(ListingImporter::class)->import(['zipCode' => '78702'], ['sale']))
        ->toThrow(RuntimeException::class, 'requests have been used');

    Http::assertSentCount(1);
});

test('a missing or wrong API key gives a clear message', function () {
    config(['services.rentcast.key' => null]);
    expect(fn () => app(RentCast::class)->listings('sale', ['zipCode' => '78723']))->toThrow(RuntimeException::class, 'RENTCAST_API_KEY');

    config(['services.rentcast.key' => 'bad']);
    Http::fake(['api.rentcast.io/*' => Http::response(['message' => 'Unauthorized'], 401)]);
    expect(fn () => app(RentCast::class)->listings('sale', ['zipCode' => '78723']))->toThrow(RuntimeException::class, 'did not accept the API key');
});

test('the admin can import homes from the dashboard', function () {
    Agent::factory()->create();
    Http::fake(['api.rentcast.io/*' => Http::response([rentCastHome()])]);

    Livewire::actingAs(User::factory()->admin()->create())->test('pages::dashboard.import')
        ->set('zip', '78723')
        ->set('types', ['sale'])
        ->call('import')
        ->assertHasNoErrors()
        ->assertSet('result.created', 1)
        ->assertSee('3821 Hargis St');
});

test('the import page checks the ZIP code and is for admins only', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test('pages::dashboard.import')
        ->set('zip', '7872')
        ->call('import')
        ->assertHasErrors(['zip' => 'regex']);

    $agent = Agent::factory()->create();
    $this->actingAs($agent->user)->get(route('dashboard.import'))->assertForbidden();
});

test('the import command brings in homes for a city', function () {
    Agent::factory()->create();
    Http::fake(['api.rentcast.io/*' => Http::response([rentCastHome()])]);

    $this->artisan('listings:import', ['--city' => 'Austin', '--state' => 'tx', '--type' => 'sale'])->assertSuccessful();

    expect(Listing::query()->count())->toBe(1);
    Http::assertSent(fn ($request) => $request['city'] === 'Austin' && $request['state'] === 'TX');
});

test('feed homes show the source and a drawing until photos are added', function () {
    Agent::factory()->create();
    app(ListingImporter::class)->save(rentCastHome(), ListingStatus::ForSale);
    $listing = Listing::query()->sole();

    $this->get(route('listings.show', $listing))
        ->assertOk()
        ->assertSee('via RentCast')
        ->assertSee('Gottesman Residential R.E.')
        ->assertSee('images/homes/house.svg');
});
