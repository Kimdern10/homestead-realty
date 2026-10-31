<?php

use App\Models\Agent;
use App\Models\City;
use App\Models\Listing;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function fakeZipAndSchools(array $rentCast = []): void
{
    Http::fake([
        'api.zippopotam.us/us/78704' => Http::response(['places' => [['place name' => 'Austin', 'state' => 'Texas', 'state abbreviation' => 'TX', 'latitude' => '30.2428', 'longitude' => '-97.7658']]]),
        'api.zippopotam.us/us/00000' => Http::response([], 404),
        'services1.arcgis.com/*/Public_School_Locations_Current/*' => Http::response(['features' => [
            ['attributes' => ['NAME' => 'O HENRY MS', 'LAT' => 30.2600, 'LON' => -97.7600]],
            ['attributes' => ['NAME' => 'ZILKER EL', 'LAT' => 30.2450, 'LON' => -97.7650]],
            ['attributes' => ['NAME' => '', 'LAT' => 30.2440, 'LON' => -97.7660]],
        ]]),
        'services1.arcgis.com/*/Private_School_Locations_Current/*' => Http::response(['features' => [
            ['attributes' => ['NAME' => 'ST AUSTIN CATHOLIC SCHOOL', 'LAT' => 30.2700, 'LON' => -97.7500]],
        ]]),
        'api.rentcast.io/*' => Http::response($rentCast),
    ]);
}

test('searching a real ZIP shows the town, its homes and nearby schools', function () {
    fakeZipAndSchools();
    Listing::factory()->create(['zip' => '78704', 'address' => '1 In Zip St']);
    Listing::factory()->create(['zip' => '78702', 'address' => '2 Elsewhere St']);

    Livewire::withQueryParams(['q' => '78704'])->test('pages::search')
        ->assertSee('1 In Zip St')
        ->assertDontSee('2 Elsewhere St');

    Livewire::withoutLazyLoading()->test('zip-insights', ['zip' => '78704'])
        ->assertSee('Austin, TX')
        ->assertSee('1 for sale')
        ->assertDispatched('map-center');

    Livewire::withoutLazyLoading()->test('nearby-schools', ['lat' => 30.2428, 'lng' => -97.7658])
        ->assertSeeInOrder(['Zilker Elementary', 'O Henry Middle School', 'St Austin Catholic School'])
        ->assertSee('Elementary · Public')
        ->assertSee('Private')
        ->assertSee('Department of Education');
});

test('an unknown ZIP says so', function () {
    fakeZipAndSchools();

    Livewire::withoutLazyLoading()->test('zip-insights', ['zip' => '00000'])->assertSee('find ZIP code 00000');
});

test('a ZIP search fetches live homes once when the feed is on', function () {
    fakeZipAndSchools([[
        'id' => 'live-1', 'formattedAddress' => '9 Live Oak St, Austin, TX 78704', 'addressLine1' => '9 Live Oak St', 'city' => 'Austin', 'state' => 'TX',
        'zipCode' => '78704', 'propertyType' => 'Single Family', 'bedrooms' => 3, 'bathrooms' => 2, 'price' => 650000, 'status' => 'Active',
    ]]);
    config(['services.rentcast.key' => 'test-key']);
    Setting::put(['live_feed_enabled' => '1']);
    Agent::factory()->create();

    Livewire::withoutLazyLoading()->test('zip-insights', ['zip' => '78704'])
        ->assertSet('added', 1)
        ->assertDispatched('listings-imported')
        ->assertSee('1 new home just added');

    Livewire::withoutLazyLoading()->test('zip-insights', ['zip' => '78704'])->assertSet('added', 0);

    expect(Http::recorded(fn ($request) => str_contains($request->url(), 'rentcast')))->toHaveCount(1);
});

test('ZIP searches do not use the feed when the admin switched it off', function () {
    fakeZipAndSchools();
    config(['services.rentcast.key' => 'test-key']);
    Setting::put(['live_feed_enabled' => '1']);
    Setting::put(['auto_fetch_zip' => '0']);

    Livewire::withoutLazyLoading()->test('zip-insights', ['zip' => '78704'])->assertSet('added', 0);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'rentcast'));
});

test('listing pages show nearby schools', function () {
    $listing = Listing::factory()->create();

    $this->get(route('listings.show', $listing))->assertOk()->assertSee('Nearby schools');
});

test('the admin can give an agent areas and a homes-sold number', function () {
    $agent = Agent::factory()->create();
    $city = City::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())->test('pages::dashboard.agents.form', ['agent' => $agent])
        ->set('cityIds', [(string) $city->id])
        ->set('zip_codes', '78704 78702, 78704')
        ->set('homes_sold', '312')
        ->call('save')
        ->assertHasNoErrors();

    $agent->refresh();
    expect($agent->cities()->pluck('cities.id')->all())->toBe([$city->id])
        ->and($agent->zipCodeList())->toBe(['78704', '78702'])
        ->and($agent->soldCount())->toBe(312);

    $this->get(route('agents.show', $agent))->assertSee('312 homes sold');
});

test('without a number the agent shows their sold listings', function () {
    $agent = Agent::factory()->create();
    Listing::factory()->for($agent)->sold()->count(2)->create();

    expect($agent->soldCount())->toBe(2);
});

test('ZIP codes for an agent must be numbers', function () {
    $agent = Agent::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())->test('pages::dashboard.agents.form', ['agent' => $agent])
        ->set('zip_codes', 'Austin')
        ->call('save')
        ->assertHasErrors(['zip_codes' => 'regex']);
});
