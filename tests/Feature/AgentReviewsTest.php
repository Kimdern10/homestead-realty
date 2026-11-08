<?php

use App\Models\Agent;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    RateLimiter::clear('review:127.0.0.1');
});

test('a visitor can review an agent and it waits for approval', function () {
    $agent = Agent::factory()->create();

    Livewire::test('agent-reviews', ['agent' => $agent])
        ->set('writing', true)
        ->set('rating', 4)
        ->set('title', 'Great help')
        ->set('body', 'Found us a lovely first home and explained every step.')
        ->set('name', 'Maria Lopez')
        ->set('email', 'maria@example.com')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('sent', true);

    $review = Review::query()->sole();

    expect($review->status)->toBe(Review::PENDING)->and($review->rating)->toBe(4);

    $this->get(route('agents.show', $agent))->assertOk()->assertDontSee('Found us a lovely first home');
});

test('a review needs a rating between 1 and 5 and some text', function () {
    $agent = Agent::factory()->create();

    Livewire::test('agent-reviews', ['agent' => $agent])
        ->set('rating', 9)
        ->set('body', 'Too short')
        ->call('submit')
        ->assertHasErrors(['rating', 'body', 'name', 'email']);
});

test('approved reviews show with the average rating and only a short name', function () {
    $agent = Agent::factory()->create();
    Review::factory()->for($agent)->approved()->create(['rating' => 5, 'name' => 'Jordan Smith', 'body' => 'Fantastic agent, sold in a week.']);
    Review::factory()->for($agent)->approved()->create(['rating' => 4]);
    Review::factory()->for($agent)->create(['rating' => 1, 'body' => 'Pending complaint text']);

    $this->get(route('agents.show', $agent))
        ->assertOk()
        ->assertSee('Fantastic agent, sold in a week.')
        ->assertSee('Jordan S.')
        ->assertDontSee('Jordan Smith')
        ->assertSee('4.5')
        ->assertDontSee('Pending complaint text')
        ->assertSee('"aggregateRating"', false);
});

test('the admin can approve and hide reviews', function () {
    $admin = User::factory()->admin()->create();
    $review = Review::factory()->create();

    Livewire::actingAs($admin)->test('pages::dashboard.reviews')
        ->assertSee($review->body)
        ->call('approve', $review->id);

    expect($review->fresh()->status)->toBe(Review::APPROVED);

    Livewire::actingAs($admin)->test('pages::dashboard.reviews')->call('reject', $review->id);

    expect($review->fresh()->status)->toBe(Review::REJECTED);
});

test('agents cannot open the reviews page', function () {
    $agent = Agent::factory()->create();

    $this->actingAs($agent->user)->get(route('dashboard.reviews'))->assertForbidden();
});
