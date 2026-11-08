<?php

use App\Enums\EnquiryType;
use App\Enums\ListingStatus;
use App\Mail\EnquiryReceived;
use App\Models\Agent;
use App\Models\Enquiry;
use App\Models\Listing;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    Http::preventStrayRequests();
    RateLimiter::clear('home-value:127.0.0.1');
});

test('a home owner gets an estimate from nearby homes and the agent gets the lead', function () {
    $agent = Agent::factory()->create(['zip_codes' => '78704']);

    // Three similar homes at $300 per sqft in the same ZIP.
    foreach ([[600000, 2000], [450000, 1500], [540000, 1800]] as [$price, $sqft]) {
        Listing::factory()->sold()->create(['zip' => '78704', 'price' => $price, 'sqft' => $sqft, 'beds' => 3]);
    }

    Livewire::test('pages::home-value')
        ->set('address', '12 Elm Street')
        ->set('zip', '78704')
        ->set('beds', '3')
        ->set('sqft', '1700')
        ->set('name', 'Pat Seller')
        ->set('email', 'pat@example.com')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('done', true)
        ->assertSee('$510,000');

    $enquiry = Enquiry::query()->sole();

    expect($enquiry->type)->toBe(EnquiryType::Valuation)
        ->and($enquiry->agent_id)->toBe($agent->id)
        ->and($enquiry->message)->toContain('12 Elm Street');

    Mail::assertSent(EnquiryReceived::class, fn (EnquiryReceived $mail) => $mail->hasTo($agent->contactEmail()));
});

test('without enough nearby homes the owner is told an agent will work it out', function () {
    Agent::factory()->create();

    Livewire::test('pages::home-value')
        ->set('address', '1 Lonely Road')
        ->set('zip', '10001')
        ->set('name', 'Sam')
        ->set('email', 'sam@example.com')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('result', null)
        ->assertSee('an agent will work it out by hand');

    expect(Enquiry::query()->sole()->type)->toBe(EnquiryType::Valuation);
});

test('the estimate uses RentCast when live homes are switched on', function () {
    config(['services.rentcast.key' => 'test-key', 'services.rentcast.url' => 'https://api.rentcast.io/v1']);
    Setting::put(['live_feed_enabled' => '1']);
    Agent::factory()->create();
    Http::fake(['api.rentcast.io/v1/avm/value*' => Http::response(['price' => 487000, 'priceRangeLow' => 450000, 'priceRangeHigh' => 520000])]);

    Livewire::test('pages::home-value')
        ->set('address', '5 Feed Street')
        ->set('zip', '78704')
        ->set('name', 'Lee')
        ->set('email', 'lee@example.com')
        ->call('submit')
        ->assertSee('$487,000')
        ->assertSee('$450,000');

    Http::assertSentCount(1);
});

test('the home value form checks the ZIP and email', function () {
    Livewire::test('pages::home-value')
        ->set('address', '5 Feed Street')
        ->set('zip', '123')
        ->set('email', 'not-an-email')
        ->call('submit')
        ->assertHasErrors(['zip', 'email', 'name']);

    expect(Enquiry::query()->count())->toBe(0);
});

test('sold homes in another ZIP of the same city are used when the ZIP has too few', function () {
    Agent::factory()->create();
    $listing = Listing::factory()->create(['zip' => '78701', 'status' => ListingStatus::ForSale, 'beds' => 1]);

    foreach ([400000, 420000, 440000] as $price) {
        Listing::factory()->sold()->create(['zip' => '78702', 'city_id' => $listing->city_id, 'price' => $price, 'beds' => 3, 'sqft' => null]);
    }

    Livewire::test('pages::home-value')
        ->set('address', '3 Near Street')
        ->set('zip', '78701')
        ->set('beds', '3')
        ->set('name', 'Kim')
        ->set('email', 'kim@example.com')
        ->call('submit')
        ->assertSee('$420,000');
});
