<?php

use App\Enums\ListingStatus;
use App\Mail\SubmissionApproved;
use App\Mail\SubmissionReceived;
use App\Models\Agent;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    Storage::fake('public');
    RateLimiter::clear('sell:127.0.0.1');
    Http::fake([
        'api.zippopotam.us/us/78704' => Http::response(['places' => [['place name' => 'Austin', 'state' => 'Texas', 'state abbreviation' => 'TX', 'latitude' => '30.2428', 'longitude' => '-97.7658']]]),
        'api.zippopotam.us/*' => Http::response([], 404),
    ]);
});

function fillSellForm(): Testable
{
    return Livewire::test('pages::sell')
        ->set('address', '501 Owner Lane')
        ->set('zip', '78704')
        ->set('price', '525000')
        ->set('beds', '3')
        ->set('baths', '2')
        ->set('description', 'Bright three bedroom home with a big yard, new roof and a quiet street.')
        ->set('owner_name', 'Olivia Owner')
        ->set('owner_email', 'olivia@example.com')
        ->set('owner_phone', '(512) 555-0199')
        ->set('agree', true);
}

test('an owner can send in a home with photos and it stays hidden', function () {
    $agent = Agent::factory()->create(['zip_codes' => '78704']);

    fillSellForm()
        ->set('photos', [UploadedFile::fake()->image('front.jpg', 1200, 800)])
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('sent', true);

    $listing = Listing::query()->sole();

    expect($listing->source)->toBe('owner')
        ->and($listing->is_published)->toBeFalse()
        ->and($listing->status)->toBe(ListingStatus::ForSale)
        ->and($listing->agent_id)->toBe($agent->id)
        ->and($listing->city->name)->toBe('Austin')
        ->and($listing->owner_email)->toBe('olivia@example.com')
        ->and($listing->photos()->count())->toBe(1);

    Mail::assertSent(SubmissionReceived::class, fn (SubmissionReceived $mail) => $mail->hasTo($agent->contactEmail()));
    $this->get(route('listings.show', $listing))->assertNotFound();
});

test('an unknown ZIP code is refused', function () {
    Agent::factory()->create();

    fillSellForm()->set('zip', '00000')->call('submit')->assertHasErrors(['zip']);

    expect(Listing::query()->count())->toBe(0);
});

test('the owner must confirm they may list the home', function () {
    Agent::factory()->create();

    fillSellForm()->set('agree', false)->call('submit')->assertHasErrors(['agree']);
});

test('the admin can approve a submission and the owner is emailed', function () {
    $admin = User::factory()->admin()->create();
    $listing = Listing::factory()->draft()->create(['source' => 'owner', 'owner_name' => 'Olivia Owner', 'owner_email' => 'olivia@example.com', 'owner_phone' => '555']);

    Livewire::actingAs($admin)->test('pages::dashboard.submissions')
        ->assertSee('Olivia Owner')
        ->call('approve', $listing->id);

    expect($listing->fresh()->is_published)->toBeTrue();
    Mail::assertSent(SubmissionApproved::class, fn (SubmissionApproved $mail) => $mail->hasTo('olivia@example.com'));
});

test('the admin can decline a submission', function () {
    $admin = User::factory()->admin()->create();
    $listing = Listing::factory()->draft()->create(['source' => 'owner']);

    Livewire::actingAs($admin)->test('pages::dashboard.submissions')->call('decline', $listing->id);

    expect(Listing::query()->count())->toBe(0);
});
