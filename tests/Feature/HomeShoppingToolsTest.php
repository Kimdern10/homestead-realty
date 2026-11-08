<?php

use App\Models\Agent;
use App\Models\Listing;

test('the compare page shows the chosen homes side by side and marks the best price', function () {
    $cheap = Listing::factory()->create(['price' => 300000, 'address' => '1 Cheap Street']);
    $pricey = Listing::factory()->create(['price' => 500000, 'address' => '2 Pricey Road']);
    $hidden = Listing::factory()->draft()->create(['address' => '3 Hidden Lane']);

    $response = $this->get(route('compare', ['ids' => "{$pricey->id},{$cheap->id},{$hidden->id}"]));

    $response->assertOk()
        ->assertSeeInOrder(['2 Pricey Road', '1 Cheap Street'])
        ->assertDontSee('3 Hidden Lane')
        ->assertSee('class="is-best"', false);
});

test('the compare page asks for homes when none are chosen', function () {
    $this->get(route('compare'))->assertOk()->assertSee('Nothing to compare yet');
});

test('compare only takes the first four homes', function () {
    $homes = Listing::factory()->count(5)->create();

    $response = $this->get(route('compare', ['ids' => $homes->pluck('id')->implode(',')]));

    $response->assertOk()->assertSee($homes[3]->address)->assertDontSee($homes[4]->address);
});

test('recently viewed cards come back for published homes only', function () {
    $seen = Listing::factory()->create(['address' => '10 Seen Avenue']);
    $draft = Listing::factory()->draft()->create(['address' => '11 Draft Avenue']);

    $this->get(route('listings.cards', ['ids' => "{$seen->id},{$draft->id},abc"]))
        ->assertOk()
        ->assertSee('10 Seen Avenue')
        ->assertDontSee('11 Draft Avenue');
});

test('a price cut is remembered and shown as a badge', function () {
    $listing = Listing::factory()->create(['price' => 450000]);

    $listing->update(['price' => 425000]);

    expect($listing->fresh()->previous_price)->toBe(450000)
        ->and($listing->fresh()->priceCut())->toBe(25000);

    $this->get(route('listings.show', $listing))->assertOk()->assertSee('Price cut $25,000');
    $this->get(route('listings.cards', ['ids' => $listing->id]))->assertSee('↓ $25,000');
});

test('a price rise clears the price cut', function () {
    $listing = Listing::factory()->create(['price' => 450000]);
    $listing->update(['price' => 425000]);
    $listing->update(['price' => 460000]);

    expect($listing->fresh()->previous_price)->toBeNull()
        ->and($listing->fresh()->priceCut())->toBeNull();
});

test('the listing shows a WhatsApp button when the agent has a WhatsApp number', function () {
    $agent = Agent::factory()->create(['whatsapp' => '(512) 555-0142']);
    $listing = Listing::factory()->for($agent)->create();

    expect($agent->whatsappLink())->toBe('https://wa.me/15125550142');

    $this->get(route('listings.show', $listing))->assertOk()->assertSee('https://wa.me/15125550142?text=', false)->assertSee('Chat on WhatsApp');
});

test('no WhatsApp button without a number', function () {
    $listing = Listing::factory()->create();

    $this->get(route('listings.show', $listing))->assertOk()->assertDontSee('Chat on WhatsApp');
});

test('the printable flyer has the price, the agent and a QR code link', function () {
    $listing = Listing::factory()->create(['price' => 615000]);

    $this->get(route('listings.flyer', $listing))
        ->assertOk()
        ->assertSee('$615,000')
        ->assertSee($listing->agent->name)
        ->assertSee('data-url="'.route('listings.show', $listing).'"', false)
        ->assertSee('qrcode.min.js');
});

test('a hidden home has no flyer', function () {
    $listing = Listing::factory()->draft()->create();

    $this->get(route('listings.flyer', $listing))->assertNotFound();
});

test('the calculator pages load', function (string $route) {
    $this->get(route($route))->assertOk();
})->with(['calculators.affordability', 'calculators.rent-vs-buy']);
