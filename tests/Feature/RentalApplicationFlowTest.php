<?php

use App\Enums\ApplicationStatus;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Mail\ApplicationDecided;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\RentalApplication;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    config(['services.paystack.secret' => 'sk_test_paystack']);
    Setting::put(['payment_gateway' => 'paystack', 'payment_currency' => 'NGN', 'holding_deposit' => '500']);
});

test('approving an application emails the applicant a link to pay the deposit', function () {
    $listing = Listing::factory()->forRent()->create();
    $application = RentalApplication::factory()->for($listing)->create(['email' => 'renter@example.com']);

    Livewire::actingAs($listing->agent->user)
        ->test('pages::dashboard.applications.show', ['application' => $application])
        ->call('decide', 'approved');

    expect($application->fresh()->status)->toBe(ApplicationStatus::Approved);

    Mail::assertSent(ApplicationDecided::class, function (ApplicationDecided $mail) {
        $html = $mail->render();

        return $mail->hasTo('renter@example.com') && str_contains($html, 'Pay the holding deposit') && str_contains($html, 'signature=');
    });
});

test('declining an application tells the applicant, without a pay link', function () {
    $listing = Listing::factory()->forRent()->create();
    $application = RentalApplication::factory()->for($listing)->create(['email' => 'renter@example.com']);

    Livewire::actingAs($listing->agent->user)
        ->test('pages::dashboard.applications.show', ['application' => $application])
        ->call('decide', 'declined');

    Mail::assertSent(ApplicationDecided::class, fn (ApplicationDecided $mail) => $mail->hasTo('renter@example.com') && ! str_contains($mail->render(), 'Pay the holding deposit'));
});

test('the deposit page opens from the signed email link, but not without approval', function () {
    $listing = Listing::factory()->forRent()->create();
    $approved = RentalApplication::factory()->for($listing)->create(['status' => ApplicationStatus::Approved]);
    $pending = RentalApplication::factory()->for($listing)->create(['status' => ApplicationStatus::New]);

    $this->get(URL::signedRoute('listings.hold', ['listing' => $listing, 'application' => $approved->id]))->assertOk()->assertSee('Pay the holding deposit');
    $this->get(URL::signedRoute('listings.hold', ['listing' => $listing, 'application' => $pending->id]))->assertNotFound();
    $this->get(route('listings.hold', ['listing' => $listing, 'application' => $approved->id]))->assertNotFound();
    $this->get(route('listings.hold', $listing))->assertNotFound();
});

test('the listing shows the deposit button only to the approved applicant', function () {
    $listing = Listing::factory()->forRent()->create();
    $renter = User::factory()->create();
    RentalApplication::factory()->for($listing)->create(['user_id' => $renter->id, 'status' => ApplicationStatus::Approved]);

    $this->get(route('listings.show', $listing))->assertSee('Apply to rent')->assertDontSee('data-test="hold-button"', false);
    $this->actingAs($renter)->get(route('listings.show', $listing))->assertSee('Your application is approved')->assertSee('data-test="hold-button"', false);
});

test('my applications shows each status and the pay button when approved', function () {
    $renter = User::factory()->create(['email' => 'renter@example.com']);
    $approved = RentalApplication::factory()->create(['user_id' => $renter->id, 'status' => ApplicationStatus::Approved]);
    $waiting = RentalApplication::factory()->create(['email' => 'renter@example.com', 'status' => ApplicationStatus::New]);
    RentalApplication::factory()->create(['status' => ApplicationStatus::Approved]);

    $this->actingAs($renter)->get(route('my-applications'))
        ->assertOk()
        ->assertSee($approved->listing->address)
        ->assertSee($waiting->listing->address)
        ->assertSee('Waiting for review')
        ->assertSee('data-test="pay-deposit"', false);

    expect(Livewire::actingAs($renter)->test('pages::my-applications')->get('applications'))->toHaveCount(2);
});

test('once the deposit is paid it is not asked for again', function () {
    $renter = User::factory()->create();
    $application = RentalApplication::factory()->create(['user_id' => $renter->id, 'status' => ApplicationStatus::Approved]);
    Payment::factory()->create(['purpose' => PaymentPurpose::HoldingDeposit, 'status' => PaymentStatus::Paid, 'payable_type' => $application->getMorphClass(), 'payable_id' => $application->id, 'listing_id' => $application->listing_id]);

    expect($application->fresh()->canPayDeposit())->toBeFalse();

    $this->actingAs($renter)->get(route('my-applications'))->assertSee('Holding deposit of')->assertDontSee('data-test="pay-deposit"', false);
    $this->actingAs($renter)->get(route('listings.hold', $application->listing))->assertNotFound();
});
