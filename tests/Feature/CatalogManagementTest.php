<?php

use App\Enums\PropertyType;
use App\Models\Agent;
use App\Models\Amenity;
use App\Models\City;
use App\Models\HomeType;
use App\Models\Listing;
use App\Models\User;
use Livewire\Livewire;

test('the usual home types and amenities are there from the start', function () {
    expect(HomeType::query()->pluck('slug')->all())->toContain('house', 'condo', 'land')
        ->and(Amenity::query()->pluck('name')->all())->toContain('Swimming pool', 'Garage');
});

test('the admin adds a home type that agents can then use', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)->test('pages::dashboard.home-types')
        ->set('name', 'Duplex')
        ->set('kind', 'multi_family')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Duplex');

    $type = HomeType::query()->where('name', 'Duplex')->sole();
    expect($type->slug)->toBe('duplex')->and($type->kind)->toBe(PropertyType::MultiFamily);

    $agent = Agent::factory()->create();
    Livewire::actingAs($agent->user)->test('pages::dashboard.listings.form')
        ->set('title', 'Big duplex')->set('status', 'for_sale')->set('property_type', 'duplex')
        ->set('price', '450000')->set('beds', '4')->set('baths', '2')
        ->set('address', '1 Duplex Way')->set('city_id', (string) City::factory()->create()->id)->set('zip', '78704')
        ->set('description', 'A roomy duplex with two separate units and a shared yard.')
        ->call('save')
        ->assertHasNoErrors();

    $listing = Listing::query()->sole();
    expect($listing->property_type)->toBe('duplex')->and($listing->typeLabel())->toBe('Duplex');

    $this->get(route('listings.show', $listing))->assertSee('Duplex');
});

test('a home type in use cannot be deleted, but can be renamed', function () {
    $admin = User::factory()->admin()->create();
    Listing::factory()->create(['property_type' => 'condo']);
    $condo = HomeType::query()->where('slug', 'condo')->sole();

    Livewire::actingAs($admin)->test('pages::dashboard.home-types')->call('delete', $condo->id);
    expect($condo->fresh())->not->toBeNull();

    Livewire::actingAs($admin)->test('pages::dashboard.home-types')
        ->call('edit', $condo->id)
        ->set('name', 'Condominium')
        ->call('save');

    expect($condo->fresh()->name)->toBe('Condominium')->and($condo->fresh()->slug)->toBe('condo');
});

test('a listing type that is not in the list is refused', function () {
    $agent = Agent::factory()->create();

    Livewire::actingAs($agent->user)->test('pages::dashboard.listings.form')
        ->set('property_type', 'castle')
        ->set('amenities', ['Moat'])
        ->call('save')
        ->assertHasErrors(['property_type', 'amenities.0']);
});

test('the admin adds several amenities at once', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)->test('pages::dashboard.amenities')
        ->set('name', 'Wine cellar, Sauna, swimming pool')
        ->call('add');

    expect(Amenity::query()->whereIn('name', ['Wine cellar', 'Sauna'])->count())->toBe(2)
        ->and(Amenity::query()->where('name', 'like', 'swimming pool')->count())->toBe(1);
});

test('renaming or deleting an amenity updates the listings that have it', function () {
    $admin = User::factory()->admin()->create();
    $listing = Listing::factory()->create(['amenities' => ['Garage', 'Fireplace']]);
    $garage = Amenity::query()->where('name', 'Garage')->sole();
    $fireplace = Amenity::query()->where('name', 'Fireplace')->sole();

    Livewire::actingAs($admin)->test('pages::dashboard.amenities')
        ->call('edit', $garage->id)
        ->set('editingName', 'Two-car garage')
        ->call('rename')
        ->call('delete', $fireplace->id);

    expect($listing->fresh()->amenities)->toBe(['Two-car garage']);
});

test('setup pages are for the admin only', function (string $route) {
    $this->actingAs(Agent::factory()->create()->user)->get(route($route))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get(route($route))->assertOk();
})->with(['dashboard.home-types', 'dashboard.amenities', 'dashboard.services', 'dashboard.bookings', 'dashboard.payment-setup']);

test('visitors see their own account menu, not dashboard links', function () {
    $member = User::factory()->create();

    $this->actingAs($member)->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Saved homes &amp; searches', escape: false)
        ->assertDontSee('My listings')
        ->assertDontSee('Enquiries &amp; tours', escape: false);
});
