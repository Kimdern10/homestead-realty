<?php

use App\Models\Agent;
use App\Models\Listing;
use App\Models\User;

test('/admin opens the dashboard', function () {
    $this->get('/admin')->assertRedirect('/dashboard');
    $this->actingAs(User::factory()->admin()->create())->followingRedirects()->get('/admin')->assertOk()->assertSee('Add a listing');
});

test('members are sent to their saved homes instead of the dashboard', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertRedirect(route('saved'));
});

test('agents and admins can open the dashboard', function () {
    $agent = Agent::factory()->create();

    $this->actingAs($agent->user)->get(route('dashboard'))->assertOk()->assertSee('Welcome back');
    $this->actingAs(User::factory()->admin()->create())->get(route('dashboard'))->assertOk();
});

test('office pages are for admins only', function (string $route) {
    $agent = Agent::factory()->create();

    $this->actingAs($agent->user)->get(route($route))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get(route($route))->assertOk();
})->with(['dashboard.agents.index', 'dashboard.agents.create', 'dashboard.cities.index', 'dashboard.cities.create', 'dashboard.settings']);

test('an inactive agent cannot use the dashboard', function () {
    $agent = Agent::factory()->inactive()->create();

    $this->actingAs($agent->user)->get(route('dashboard'))->assertForbidden();
});

test('agents see only their own listings', function () {
    $agent = Agent::factory()->create();
    Listing::factory()->for($agent)->create(['address' => '1 Mine St']);
    Listing::factory()->create(['address' => '2 Someone Else St']);

    $this->actingAs($agent->user)->get(route('dashboard.listings.index'))
        ->assertOk()
        ->assertSee('1 Mine St')
        ->assertDontSee('2 Someone Else St');
});

test('an agent cannot edit another agent’s listing', function () {
    $agent = Agent::factory()->create();
    $other = Listing::factory()->create();

    $this->actingAs($agent->user)->get(route('dashboard.listings.edit', $other))->assertForbidden();
});

test('the admin sees every listing', function () {
    Listing::factory()->create(['address' => '1 First St']);
    Listing::factory()->create(['address' => '2 Second St']);

    $this->actingAs(User::factory()->admin()->create())->get(route('dashboard.listings.index'))
        ->assertOk()
        ->assertSee('1 First St')
        ->assertSee('2 Second St');
});
