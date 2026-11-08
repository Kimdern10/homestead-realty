<?php

use App\Models\Agent;
use App\Models\Listing;
use App\Models\OpenHouse;
use App\Models\User;
use Livewire\Livewire;

test('an agent can add an open house to their own home', function () {
    $agent = Agent::factory()->create();
    $listing = Listing::factory()->for($agent)->create();
    $saturday = today()->next('Saturday')->toDateString();

    Livewire::actingAs($agent->user)
        ->test('pages::dashboard.open-houses')
        ->set('listing_id', (string) $listing->id)
        ->set('date', $saturday)
        ->set('starts', '13:00')
        ->set('ends', '15:00')
        ->set('note', 'Cookies served')
        ->call('add')
        ->assertHasNoErrors();

    $openHouse = OpenHouse::query()->sole();

    expect($openHouse->listing_id)->toBe($listing->id)
        ->and($openHouse->starts_at->format('Y-m-d H:i'))->toBe($saturday.' 13:00')
        ->and($openHouse->ends_at->format('H:i'))->toBe('15:00');
});

test('an agent cannot add an open house to another agent\'s home', function () {
    $agent = Agent::factory()->create();
    $otherListing = Listing::factory()->create();

    Livewire::actingAs($agent->user)
        ->test('pages::dashboard.open-houses')
        ->set('listing_id', (string) $otherListing->id)
        ->call('add')
        ->assertHasErrors(['listing_id']);

    expect(OpenHouse::query()->count())->toBe(0);
});

test('the end time must be after the start time', function () {
    $admin = User::factory()->admin()->create();
    $listing = Listing::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::dashboard.open-houses')
        ->set('listing_id', (string) $listing->id)
        ->set('starts', '15:00')
        ->set('ends', '14:00')
        ->call('add')
        ->assertHasErrors(['ends']);
});

test('upcoming open houses show on the home, the listing and the open houses page', function () {
    $listing = Listing::factory()->create(['address' => '42 Open Lane']);
    $start = now()->addDays(2)->setTime(13, 0);
    OpenHouse::factory()->for($listing)->create(['starts_at' => $start, 'ends_at' => $start->copy()->addHours(2)]);

    $this->get(route('listings.show', $listing))->assertOk()->assertSee('Open house')->assertSee($start->format('D, M j'));
    $this->get(route('open-houses'))->assertOk()->assertSee('42 Open Lane')->assertSee('data-test="open-house-badge"', false);
    $this->get(route('home'))->assertOk()->assertSee('Open houses')->assertSee('42 Open Lane');
});

test('past open houses are not shown', function () {
    $listing = Listing::factory()->create(['address' => '7 Old Street']);
    OpenHouse::factory()->for($listing)->create(['starts_at' => now()->subDays(2), 'ends_at' => now()->subDays(2)->addHours(2)]);

    $this->get(route('open-houses'))->assertOk()->assertDontSee('7 Old Street')->assertSee('No open houses planned right now');
});
