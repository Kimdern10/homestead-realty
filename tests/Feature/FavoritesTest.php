<?php

use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use Livewire\Livewire;

test('guests must sign in to save a home', function () {
    $listing = Listing::factory()->create();

    $this->postJson(route('favorites.toggle', $listing))->assertUnauthorized();
    $this->post(route('favorites.toggle', $listing))->assertRedirect(route('login'));
});

test('a visitor can save and unsave a home', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->create();

    $this->actingAs($user)->postJson(route('favorites.toggle', $listing))->assertOk()->assertJson(['saved' => true]);
    expect($user->favorites()->pluck('listings.id')->all())->toBe([$listing->id]);

    $this->actingAs($user)->postJson(route('favorites.toggle', $listing))->assertOk()->assertJson(['saved' => false]);
    expect($user->favorites()->count())->toBe(0);
});

test('draft homes cannot be saved', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->draft()->create();

    $this->actingAs($user)->postJson(route('favorites.toggle', $listing))->assertNotFound();
});

test('saved homes show as saved on cards', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->featured()->create();
    $user->favorites()->attach($listing);

    $this->actingAs($user)->get(route('home'))->assertSee('aria-pressed="true"', escape: false);
});

test('the saved page lists saved homes and searches, and they can be removed', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->create(['address' => '12 Saved Ave']);
    $user->favorites()->attach($listing);
    $search = SavedSearch::factory()->for($user)->create(['name' => 'Austin family homes']);

    $this->actingAs($user)->get(route('saved'))->assertOk()->assertSee('12 Saved Ave')->assertSee('Austin family homes');

    Livewire::actingAs($user)->test('pages::saved')
        ->call('removeHome', $listing->id)
        ->call('deleteSearch', $search->id)
        ->assertSee('No saved homes yet');

    expect($user->favorites()->count())->toBe(0)
        ->and($user->savedSearches()->count())->toBe(0);
});

test('a visitor cannot delete someone else’s saved search', function () {
    $search = SavedSearch::factory()->create();

    Livewire::actingAs(User::factory()->create())->test('pages::saved')->call('deleteSearch', $search->id);

    expect($search->fresh())->not->toBeNull();
});

test('guests are sent to log in from the saved page', function () {
    $this->get(route('saved'))->assertRedirect(route('login'));
});
