<?php

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;
use Livewire\Livewire;

test('a rented home stays on the website marked rented and takes no applications', function () {
    $listing = Listing::factory()->rented()->create(['price' => 2400]);

    $this->get(route('listings.show', $listing))
        ->assertOk()
        ->assertSee('Rented')
        ->assertSee('This home has been rented.')
        ->assertSee('$2,400')
        ->assertDontSee('Apply to rent');

    $this->get(route('listings.apply', $listing))->assertNotFound();
});

test('visitors can search for recently rented homes', function () {
    Listing::factory()->rented()->create(['address' => '5 Taken Street']);
    Listing::factory()->forRent()->create(['address' => '6 Free Street']);

    Livewire::withQueryParams(['status' => 'rented'])->test('pages::search')
        ->assertSee('Recently rented homes')
        ->assertSee('5 Taken Street')
        ->assertDontSee('6 Free Street');
});

test('marking a home as rented records the date', function () {
    $admin = User::factory()->admin()->create();
    $listing = Listing::factory()->forRent()->create();

    Livewire::actingAs($admin)->test('pages::dashboard.listings.index')->call('setStatus', $listing->id, 'rented');

    $listing->refresh();

    expect($listing->status)->toBe(ListingStatus::Rented)
        ->and($listing->sold_at?->toDateString())->toBe(today()->toDateString());
});

test('reports count rented homes', function () {
    Listing::factory()->rented()->create(['sold_at' => today()]);

    Livewire::actingAs(User::factory()->admin()->create())->test('pages::dashboard.reports')
        ->assertSeeHtml('data-test="total-rented">1<');
});
