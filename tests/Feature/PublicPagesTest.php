<?php

use App\Models\Agent;
use App\Models\City;
use App\Models\Listing;

test('the main public pages load', function (string $route) {
    Listing::factory()->featured()->create();

    $this->get(route($route))->assertOk();
})->with(['home', 'search', 'cities.index', 'agents.index', 'privacy']);

test('the home page shows featured homes and cities', function () {
    $city = City::factory()->create(['name' => 'Austin']);
    Listing::factory()->featured()->for($city)->create(['address' => '1204 Bluebonnet Ln', 'price' => 685000]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Hand-picked homes')
        ->assertSee('$685,000')
        ->assertSee('1204 Bluebonnet Ln')
        ->assertSee('Popular cities');
});

test('a listing page shows the details, calculator, contact form and structured data', function () {
    $listing = Listing::factory()->create(['address' => '1204 Bluebonnet Ln', 'price' => 685000, 'beds' => 4, 'baths' => 2.5, 'sqft' => 2650]);

    $this->get(route('listings.show', $listing))
        ->assertOk()
        ->assertSee('1204 Bluebonnet Ln')
        ->assertSee('$685,000')
        ->assertSee('2.5')
        ->assertSee('Monthly payment')
        ->assertSee('Schedule a tour')
        ->assertSee('"@type":"RealEstateListing"', escape: false)
        ->assertSee('"priceCurrency":"USD"', escape: false);
});

test('rentals show a monthly price and no mortgage calculator', function () {
    $listing = Listing::factory()->forRent()->create(['price' => 2400]);

    $this->get(route('listings.show', $listing))
        ->assertOk()
        ->assertSee('/month')
        ->assertDontSee('Monthly payment');
});

test('viewing a listing counts a view for visitors only', function () {
    $listing = Listing::factory()->create(['views' => 0]);

    $this->get(route('listings.show', $listing))->assertOk();
    expect($listing->fresh()->views)->toBe(1);

    $this->actingAs($listing->agent->user)->get(route('listings.show', $listing))->assertOk();
    expect($listing->fresh()->views)->toBe(1);
});

test('draft listings and listings of inactive agents are not found', function () {
    $draft = Listing::factory()->draft()->create();
    $hidden = Listing::factory()->for(Agent::factory()->inactive())->create();

    $this->get(route('listings.show', $draft))->assertNotFound();
    $this->get(route('listings.show', $hidden))->assertNotFound();
});

test('a city page shows its stats, neighborhoods and homes', function () {
    $city = City::factory()->create(['name' => 'Austin', 'state_code' => 'TX']);
    Listing::factory()->for($city)->create(['price' => 400000, 'neighborhood' => 'Zilker']);
    Listing::factory()->for($city)->create(['price' => 600000, 'neighborhood' => 'Zilker']);
    Listing::factory()->for($city)->forRent()->create(['price' => 2000]);

    $this->get(route('cities.show', $city))
        ->assertOk()
        ->assertSee('Austin, TX')
        ->assertSee('$500,000')
        ->assertSee('$2,000/mo')
        ->assertSee('Zilker');
});

test('an agent profile lists their homes and hides inactive agents', function () {
    $agent = Agent::factory()->create(['name' => 'Sarah Mitchell']);
    Listing::factory()->for($agent)->create(['address' => '479 Live Oak St']);

    $this->get(route('agents.show', $agent))
        ->assertOk()
        ->assertSee('Sarah Mitchell')
        ->assertSee('479 Live Oak St')
        ->assertSee('"@type":"RealEstateAgent"', escape: false);

    $agent->update(['is_active' => false]);

    $this->get(route('agents.show', $agent))->assertNotFound();
});

test('the sitemap lists published listings, cities and agents only', function () {
    $published = Listing::factory()->create();
    $draft = Listing::factory()->draft()->create();

    $this->get(route('sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee(route('listings.show', $published), escape: false)
        ->assertDontSee(route('listings.show', $draft), escape: false)
        ->assertSee(route('cities.show', $published->city), escape: false)
        ->assertSee(route('agents.show', $published->agent), escape: false);
});

test('robots.txt points to the sitemap and blocks private pages', function () {
    $this->get(route('robots'))
        ->assertOk()
        ->assertSee('Disallow: /dashboard')
        ->assertSee('Sitemap: '.route('sitemap'));
});

test('unknown pages show the branded 404 page', function () {
    $this->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertSee('find that page')
        ->assertSee('City, neighborhood, address or ZIP');
});
