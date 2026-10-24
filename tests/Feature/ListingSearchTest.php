<?php

use App\Enums\PropertyType;
use App\Models\City;
use App\Models\Listing;
use App\Models\User;
use Livewire\Livewire;

test('search filters by place, status, price, beds and home type', function () {
    $austin = City::factory()->create(['name' => 'Austin']);
    $miami = City::factory()->create(['name' => 'Miami', 'state_code' => 'FL']);

    $match = Listing::factory()->for($austin)->create(['address' => '100 Match St', 'price' => 500000, 'beds' => 3]);
    Listing::factory()->for($miami)->create(['address' => '200 Wrong City St', 'price' => 500000, 'beds' => 3]);
    Listing::factory()->for($austin)->create(['address' => '300 Too Pricey St', 'price' => 900000, 'beds' => 3]);
    Listing::factory()->for($austin)->create(['address' => '400 Too Small St', 'price' => 500000, 'beds' => 1]);
    Listing::factory()->for($austin)->forRent()->create(['address' => '500 Rental St']);
    Listing::factory()->for($austin)->create(['address' => '600 Condo St', 'price' => 500000, 'beds' => 3, 'property_type' => PropertyType::Condo]);

    Livewire::withQueryParams(['q' => 'Austin', 'status' => 'for_sale', 'max_price' => 600000, 'beds' => 2, 'types' => ['house']])
        ->test('pages::search')
        ->assertSee($match->address)
        ->assertDontSee('200 Wrong City St')
        ->assertDontSee('300 Too Pricey St')
        ->assertDontSee('400 Too Small St')
        ->assertDontSee('500 Rental St')
        ->assertDontSee('600 Condo St')
        ->assertSee('Houses for sale in Austin');
});

test('search matches a ZIP code and a neighborhood', function () {
    Listing::factory()->create(['address' => '1 Zip Match Rd', 'zip' => '78704', 'neighborhood' => 'Zilker']);
    Listing::factory()->create(['address' => '2 Other Rd', 'zip' => '78702', 'neighborhood' => 'East Austin']);

    Livewire::test('pages::search')
        ->set('q', '78704')
        ->assertSee('1 Zip Match Rd')
        ->assertDontSee('2 Other Rd')
        ->set('q', 'East Austin')
        ->assertSee('2 Other Rd')
        ->assertDontSee('1 Zip Match Rd');
});

test('search hides drafts', function () {
    Listing::factory()->draft()->create(['address' => '9 Secret Ln']);

    Livewire::test('pages::search')->assertDontSee('9 Secret Ln')->assertSee('No homes match your search');
});

test('results can be sorted by price', function () {
    Listing::factory()->create(['address' => '1 Cheap St', 'price' => 200000, 'listed_at' => now()->subDays(1)]);
    Listing::factory()->create(['address' => '2 Dear St', 'price' => 900000, 'listed_at' => now()->subDays(9)]);

    Livewire::test('pages::search')
        ->set('sort', 'price_desc')
        ->assertSeeInOrder(['2 Dear St', '1 Cheap St'])
        ->set('sort', 'price_asc')
        ->assertSeeInOrder(['1 Cheap St', '2 Dear St']);
});

test('changing a filter sends the new map points', function () {
    $listing = Listing::factory()->create(['price' => 450000]);

    Livewire::test('pages::search')
        ->set('max_price', 500000)
        ->assertDispatched('map-points', fn (string $name, array $params) => $params['points'][0]['id'] === $listing->id && $params['points'][0]['price'] === '$450K');
});

test('unknown filter values from the address bar are ignored', function () {
    Livewire::withQueryParams(['status' => 'haunted', 'sort' => 'random', 'types' => ['castle'], 'beds' => 99])
        ->test('pages::search')
        ->assertSet('status', '')
        ->assertSet('sort', 'newest')
        ->assertSet('types', [])
        ->assertSet('beds', 6)
        ->assertOk();
});

test('guests are asked to sign in to save a search', function () {
    Livewire::test('pages::search')->call('saveSearch')->assertRedirect(route('login'));
});

test('a signed-in visitor can save a search once', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->withQueryParams(['q' => 'Austin', 'status' => 'for_sale', 'beds' => 3])
        ->test('pages::search')
        ->call('saveSearch')
        ->assertSet('searchSaved', true)
        ->call('saveSearch');

    expect($user->savedSearches)->toHaveCount(1)
        ->and($user->savedSearches->first()->filters)->toBe(['q' => 'Austin', 'status' => 'for_sale', 'beds' => 3]);
});
