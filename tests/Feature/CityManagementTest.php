<?php

use App\Models\City;
use App\Models\Listing;
use App\Models\User;
use Livewire\Livewire;

test('the admin can add a city', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test('pages::dashboard.cities.form')
        ->set('name', 'Portland')
        ->set('state', 'Oregon')
        ->set('state_code', 'or')
        ->set('latitude', '45.5152')
        ->set('longitude', '-122.6784')
        ->call('save')
        ->assertHasNoErrors();

    $city = City::query()->sole();
    expect($city->slug)->toBe('portland')
        ->and($city->state_code)->toBe('OR');
});

test('a second city with the same name gets the state in its address', function () {
    City::factory()->create(['name' => 'Portland', 'slug' => 'portland']);

    Livewire::actingAs(User::factory()->admin()->create())->test('pages::dashboard.cities.form')
        ->set('name', 'Portland')
        ->set('state', 'Maine')
        ->set('state_code', 'ME')
        ->set('latitude', '43.66')
        ->set('longitude', '-70.26')
        ->call('save')
        ->assertHasNoErrors();

    expect(City::query()->where('state_code', 'ME')->value('slug'))->toBe('portland-me');
});

test('a city with listings cannot be deleted', function () {
    $listing = Listing::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())->test('pages::dashboard.cities.index')->call('delete', $listing->city_id);

    expect(City::query()->count())->toBe(1);
});

test('an empty city can be deleted', function () {
    $city = City::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())->test('pages::dashboard.cities.index')->call('delete', $city->id);

    expect(City::query()->count())->toBe(0);
});
