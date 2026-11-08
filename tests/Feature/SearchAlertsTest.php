<?php

use App\Enums\EnquiryStatus;
use App\Mail\FollowUpReminder;
use App\Mail\PriceDropAlert;
use App\Mail\SavedSearchAlert;
use App\Models\Enquiry;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
});

test('a saved search emails new matching homes once', function () {
    $user = User::factory()->create();
    $search = $user->savedSearches()->create(['name' => 'Big homes', 'filters' => ['beds' => 4]]);
    $search->forceFill(['created_at' => now()->subDay(), 'last_alerted_at' => null])->save();

    Listing::factory()->create(['beds' => 5, 'address' => '9 Match Street']);
    Listing::factory()->create(['beds' => 2, 'address' => '9 Small Street']);

    $this->artisan('alerts:send')->assertSuccessful();

    Mail::assertSent(SavedSearchAlert::class, fn (SavedSearchAlert $mail) => $mail->hasTo($user->email)
        && $mail->homes->pluck('address')->all() === ['9 Match Street']);

    // Nothing new since the last email: no second email.
    $this->artisan('alerts:send')->assertSuccessful();
    Mail::assertSentCount(1);
});

test('saved searches with alerts turned off get no email', function () {
    $user = User::factory()->create();
    $user->savedSearches()->create(['name' => 'All', 'filters' => [], 'alerts_enabled' => false]);
    Listing::factory()->create();

    $this->artisan('alerts:send')->assertSuccessful();

    Mail::assertNothingSent();
});

test('a visitor can switch alerts off for a saved search', function () {
    $user = User::factory()->create();
    $search = $user->savedSearches()->create(['name' => 'All', 'filters' => []]);

    Livewire\Livewire::actingAs($user)->test('pages::saved')->call('toggleAlerts', $search->id);

    expect($search->fresh()->alerts_enabled)->toBeFalse();
});

test('a price drop on a saved home is emailed once', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->create(['price' => 400000]);

    $this->actingAs($user)->postJson(route('favorites.toggle', $listing))->assertJson(['saved' => true]);

    $listing->update(['price' => 380000]);

    $this->artisan('alerts:send')->assertSuccessful();

    Mail::assertSent(PriceDropAlert::class, fn (PriceDropAlert $mail) => $mail->hasTo($user->email) && $mail->oldPrices[$listing->id] === 400000);

    $this->artisan('alerts:send')->assertSuccessful();
    Mail::assertSent(PriceDropAlert::class, 1);
});

test('a price rise does not send an email', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->create(['price' => 400000]);
    $this->actingAs($user)->postJson(route('favorites.toggle', $listing));

    $listing->update(['price' => 420000]);
    $this->artisan('alerts:send')->assertSuccessful();

    Mail::assertNotSent(PriceDropAlert::class);
});

test('agents are reminded about leads due for a follow-up', function () {
    $due = Enquiry::factory()->create(['follow_up_on' => today(), 'status' => EnquiryStatus::Contacted]);
    Enquiry::factory()->create(['follow_up_on' => today()->addDays(3)]);
    Enquiry::factory()->create(['follow_up_on' => today(), 'status' => EnquiryStatus::Lost]);

    $this->artisan('leads:remind')->assertSuccessful();

    Mail::assertSent(FollowUpReminder::class, 1);
    Mail::assertSent(FollowUpReminder::class, fn (FollowUpReminder $mail) => $mail->hasTo($due->agent->contactEmail()) && $mail->enquiries->modelKeys() === [$due->id]);
});

test('the daily emails are scheduled', function () {
    $this->artisan('schedule:list')->assertSuccessful()->expectsOutputToContain('alerts:send')->expectsOutputToContain('leads:remind');
});
