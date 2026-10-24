<?php

use App\Enums\ListingStatus;
use App\Models\Agent;
use App\Models\City;
use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
});

function fillListingForm(Testable $component, City $city): Testable
{
    return $component
        ->set('title', 'Renovated bungalow in Zilker')
        ->set('status', 'for_sale')
        ->set('property_type', 'house')
        ->set('price', '$685,000')
        ->set('beds', '3')
        ->set('baths', '2.5')
        ->set('sqft', '1,850')
        ->set('address', '1204 Bluebonnet Ln')
        ->set('city_id', (string) $city->id)
        ->set('zip', '78704')
        ->set('latitude', '30.25')
        ->set('longitude', '-97.76')
        ->set('amenities', ['Fireplace', 'Garage'])
        ->set('description', 'A light-filled bungalow with an updated kitchen and a big shady backyard.');
}

test('an agent can add a listing with photos, which are saved as WebP', function () {
    $agent = Agent::factory()->create();
    $city = City::factory()->create(['name' => 'Austin', 'state_code' => 'TX']);

    fillListingForm(Livewire::actingAs($agent->user)->test('pages::dashboard.listings.form'), $city)
        ->set('newPhotos', [UploadedFile::fake()->image('front.jpg', 2400, 1600), UploadedFile::fake()->image('kitchen.jpg', 1200, 800)])
        ->call('save')
        ->assertHasNoErrors();

    $listing = Listing::query()->sole();

    expect($listing->agent_id)->toBe($agent->id)
        ->and($listing->price)->toBe(685000)
        ->and($listing->sqft)->toBe(1850)
        ->and($listing->slug)->toBe('1204-bluebonnet-ln-austin-tx-78704')
        ->and($listing->amenities)->toBe(['Fireplace', 'Garage'])
        ->and($listing->photos)->toHaveCount(2);

    $cover = $listing->photos->first();
    expect($cover->width)->toBe(1600)
        ->and($cover->sort_order)->toBe(0);
    Storage::disk('public')->assertExists($cover->paths());
});

test('agents cannot assign a listing to someone else or feature it', function () {
    $agent = Agent::factory()->create();
    $other = Agent::factory()->create();

    fillListingForm(Livewire::actingAs($agent->user)->test('pages::dashboard.listings.form'), City::factory()->create())
        ->set('agent_id', (string) $other->id)
        ->set('is_featured', true)
        ->call('save')
        ->assertHasNoErrors();

    $listing = Listing::query()->sole();
    expect($listing->agent_id)->toBe($agent->id)
        ->and($listing->is_featured)->toBeFalse();
});

test('the admin can assign the agent and feature the listing', function () {
    $agent = Agent::factory()->create();

    fillListingForm(Livewire::actingAs(User::factory()->admin()->create())->test('pages::dashboard.listings.form'), City::factory()->create())
        ->set('agent_id', (string) $agent->id)
        ->set('is_featured', true)
        ->call('save')
        ->assertHasNoErrors();

    $listing = Listing::query()->sole();
    expect($listing->agent_id)->toBe($agent->id)
        ->and($listing->is_featured)->toBeTrue();
});

test('the listing form checks the important fields', function () {
    $agent = Agent::factory()->create();

    Livewire::actingAs($agent->user)->test('pages::dashboard.listings.form')
        ->set('zip', '7870')
        ->set('baths', '2.3')
        ->set('newPhotos', [UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')])
        ->call('save')
        ->assertHasErrors(['title', 'price', 'address', 'city_id', 'description', 'zip' => 'regex', 'baths' => 'multiple_of', 'newPhotos.0' => 'image']);

    expect(Listing::query()->count())->toBe(0);
});

test('marking a listing sold sets the sold date', function () {
    $listing = Listing::factory()->create();

    Livewire::actingAs($listing->agent->user)->test('pages::dashboard.listings.form', ['listing' => $listing])
        ->set('status', 'sold')
        ->call('save')
        ->assertHasNoErrors();

    expect($listing->fresh()->status)->toBe(ListingStatus::Sold)
        ->and($listing->fresh()->sold_at->isToday())->toBeTrue();
});

test('removed photos are deleted and the order is saved', function () {
    $listing = Listing::factory()->create();
    $photos = collect(['a', 'b', 'c'])->map(function (string $name, int $order) use ($listing) {
        $path = "listings/{$name}.webp";
        Storage::disk('public')->put($path, 'x');
        Storage::disk('public')->put("listings/{$name}-sm.webp", 'x');

        return ListingPhoto::factory()->for($listing)->create(['path' => $path, 'sort_order' => $order]);
    });

    Livewire::actingAs($listing->agent->user)->test('pages::dashboard.listings.form', ['listing' => $listing])
        ->call('removePhoto', 0)
        ->call('makeCover', 1)
        ->call('save')
        ->assertHasNoErrors();

    expect($listing->photos()->pluck('id')->all())->toBe([$photos[2]->id, $photos[1]->id]);
    Storage::disk('public')->assertMissing(['listings/a.webp', 'listings/a-sm.webp']);
    Storage::disk('public')->assertExists('listings/b.webp');
});

test('deleting a listing removes its photos', function () {
    $listing = Listing::factory()->create();
    Storage::disk('public')->put('listings/a.webp', 'x');
    Storage::disk('public')->put('listings/a-sm.webp', 'x');
    ListingPhoto::factory()->for($listing)->create(['path' => 'listings/a.webp']);

    Livewire::actingAs($listing->agent->user)->test('pages::dashboard.listings.index')->call('delete', $listing->id);

    expect(Listing::query()->count())->toBe(0);
    Storage::disk('public')->assertMissing(['listings/a.webp', 'listings/a-sm.webp']);
});

test('an agent cannot delete or change another agent’s listing', function () {
    $agent = Agent::factory()->create();
    $other = Listing::factory()->create();

    Livewire::actingAs($agent->user)->test('pages::dashboard.listings.index')->call('delete', $other->id)->assertForbidden();
    Livewire::actingAs($agent->user)->test('pages::dashboard.listings.index')->call('setStatus', $other->id, 'sold')->assertForbidden();

    expect($other->fresh()->status)->toBe(ListingStatus::ForSale);
});

test('a listing can be hidden and marked as sold from the list', function () {
    $listing = Listing::factory()->create();

    Livewire::actingAs($listing->agent->user)->test('pages::dashboard.listings.index')
        ->call('togglePublished', $listing->id)
        ->call('setStatus', $listing->id, 'sold');

    $listing->refresh();
    expect($listing->is_published)->toBeFalse()
        ->and($listing->status)->toBe(ListingStatus::Sold)
        ->and($listing->sold_at)->not->toBeNull();
});
