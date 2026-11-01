<?php

use App\Enums\ApplicationStatus;
use App\Enums\ListingStatus;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Mail\PaymentAlert;
use App\Mail\PaymentReceipt;
use App\Models\Agent;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\RentalApplication;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    config([
        'services.stripe.secret' => 'sk_test_123',
        'services.stripe.webhook_secret' => 'whsec_test',
        'services.paystack.secret' => 'sk_test_paystack',
    ]);
    Setting::put(['payment_gateway' => 'stripe', 'payment_currency' => 'USD', 'application_fee' => '45', 'holding_deposit' => '500', 'featured_price' => '49', 'featured_days' => '30', 'email' => 'office@example.com']);
    RateLimiter::clear('rental-application:127.0.0.1');
    RateLimiter::clear('holding-deposit:127.0.0.1');
    RateLimiter::clear('service-booking:127.0.0.1');
});

function fakeStripeCheckout(): void
{
    Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1'])]);
}

/**
 * Post a webhook body to the site signed the way Stripe signs it.
 */
function postStripeWebhook($test, array $event, string $secret = 'whsec_test')
{
    $payload = json_encode($event);
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

    return $test->call('POST', route('payments.webhook', 'stripe'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}", 'CONTENT_TYPE' => 'application/json'], $payload);
}

function stripeCompleted(Payment $payment, ?int $amount = null): array
{
    return ['type' => 'checkout.session.completed', 'data' => ['object' => [
        'client_reference_id' => $payment->reference, 'payment_status' => 'paid',
        'amount_total' => $amount ?? $payment->amount, 'currency' => 'usd', 'payment_intent' => 'pi_123',
    ]]];
}

function fillApplication(Listing $listing)
{
    return Livewire::test('pages::apply', ['listing' => $listing])
        ->set('name', 'Ashley Nguyen')
        ->set('email', 'ashley@example.com')
        ->set('phone', '(512) 555-0110')
        ->set('monthly_income', '$7,500')
        ->set('employer', 'Dell')
        ->set('agree', true);
}

test('a renter applies, is sent to Stripe, and the webhook confirms the fee', function () {
    fakeStripeCheckout();
    $listing = Listing::factory()->forRent()->create(['price' => 2400]);

    fillApplication($listing)->call('submit')->assertHasNoErrors()->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');

    $application = RentalApplication::query()->sole();
    $payment = Payment::query()->sole();

    expect($application->status)->toBe(ApplicationStatus::AwaitingPayment)
        ->and($application->monthly_income)->toBe(7500)
        ->and($payment->purpose)->toBe(PaymentPurpose::ApplicationFee)
        ->and($payment->amount)->toBe(4500)
        ->and($payment->payable->is($application))->toBeTrue()
        ->and($payment->gateway_reference)->toBe('cs_test_1');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
        && $request['client_reference_id'] === $payment->reference
        && (int) $request['line_items'][0]['price_data']['unit_amount'] === 4500);

    postStripeWebhook($this, stripeCompleted($payment))->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($application->fresh()->status)->toBe(ApplicationStatus::New);

    Mail::assertSent(PaymentReceipt::class, fn (PaymentReceipt $mail) => $mail->hasTo('ashley@example.com'));
    Mail::assertSent(PaymentAlert::class, fn (PaymentAlert $mail) => $mail->hasTo($listing->agent->contactEmail()) && $mail->hasTo('office@example.com'));
});

test('a webhook with a wrong signature changes nothing', function () {
    $payment = Payment::factory()->create(['purpose' => PaymentPurpose::HoldingDeposit, 'listing_id' => Listing::factory()->forRent()]);

    postStripeWebhook($this, stripeCompleted($payment), 'whsec_wrong')->assertStatus(400);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

test('a repeated webhook is applied once and a wrong amount is refused', function () {
    $listing = Listing::factory()->forRent()->create();
    $payment = Payment::factory()->create(['listing_id' => $listing->id]);

    postStripeWebhook($this, stripeCompleted($payment, 100))->assertOk();
    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);

    postStripeWebhook($this, stripeCompleted($payment))->assertOk();
    postStripeWebhook($this, stripeCompleted($payment))->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);
    Mail::assertSent(PaymentReceipt::class, 1);
});

test('a paid holding deposit through Paystack marks the rental pending', function () {
    Setting::put(['payment_gateway' => 'paystack']);
    Http::fake(['api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/abc', 'access_code' => 'abc']])]);
    $listing = Listing::factory()->forRent()->create();

    Livewire::test('pages::hold', ['listing' => $listing])
        ->set('name', 'Brian Walker')->set('email', 'brian@example.com')->set('phone', '(305) 555-0131')->set('agree', true)
        ->call('pay')
        ->assertRedirect('https://checkout.paystack.com/abc');

    $payment = Payment::query()->sole();
    expect($payment->gateway)->toBe('paystack')->and($payment->amount)->toBe(50000);

    $payload = json_encode(['event' => 'charge.success', 'data' => ['reference' => $payment->reference, 'status' => 'success', 'amount' => 50000, 'currency' => 'USD', 'id' => 99]]);
    $this->call('POST', route('payments.webhook', 'paystack'), [], [], [], [
        'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $payload, 'sk_test_paystack'), 'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($listing->fresh()->status)->toBe(ListingStatus::Pending);
});

test('coming back from the payment page confirms the payment with the provider', function () {
    Setting::put(['payment_gateway' => 'paystack']);
    $listing = Listing::factory()->forRent()->create();
    $payment = Payment::factory()->create(['gateway' => 'paystack', 'listing_id' => $listing->id]);
    Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => ['status' => 'success', 'amount' => 50000, 'currency' => 'USD', 'id' => 7]])]);

    $this->get(route('payments.show', $payment))->assertOk()->assertSee('Payment received');

    expect($payment->fresh()->isPaid())->toBeTrue();
});

test('a cancelled payment says no money was taken', function () {
    $payment = Payment::factory()->create();

    $this->get(route('payments.show', $payment).'?cancelled=1')->assertOk()->assertSee('Payment cancelled');
});

test('an agent pays to feature a listing for the set number of days', function () {
    fakeStripeCheckout();
    $agent = Agent::factory()->create();
    $listing = Listing::factory()->for($agent)->create(['is_featured' => false]);

    Livewire::actingAs($agent->user)->test('pages::dashboard.listings.index')
        ->call('buyFeature', $listing->id)
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');

    $payment = Payment::query()->sole();
    expect($payment->purpose)->toBe(PaymentPurpose::FeaturedListing)->and($payment->amount)->toBe(4900);

    postStripeWebhook($this, stripeCompleted($payment))->assertOk();

    $listing->refresh();
    expect($listing->isFeaturedNow())->toBeTrue()
        ->and((int) round(now()->diffInDays($listing->featured_until)))->toBe(30);

    $this->travel(31)->days();
    expect($listing->fresh()->isFeaturedNow())->toBeFalse()
        ->and(Listing::query()->featuredNow()->count())->toBe(0);
});

test('a visitor books and pays for a service', function () {
    fakeStripeCheckout();
    $service = Service::factory()->create(['name' => 'Home valuation', 'price' => 150]);

    Livewire::test('pages::services')
        ->assertSee('Home valuation')
        ->call('choose', $service->id)
        ->set('name', 'Grace Kim')->set('email', 'grace@example.com')->set('phone', '(720) 555-0155')
        ->call('book')
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');

    $payment = Payment::query()->sole();
    expect($payment->amount)->toBe(15000)->and($payment->payable)->toBeInstanceOf(ServiceBooking::class);

    postStripeWebhook($this, stripeCompleted($payment))->assertOk();
    expect(ServiceBooking::query()->sole()->status)->toBe(ApplicationStatus::New);
});

test('with payments off, applications are free and pay buttons are hidden', function () {
    Setting::put(['payment_gateway' => 'none']);
    Http::fake();
    $listing = Listing::factory()->forRent()->create();

    $this->get(route('listings.show', $listing))->assertOk()->assertSee('Apply to rent')->assertDontSee('deposit');
    $this->get(route('listings.hold', $listing))->assertNotFound();

    fillApplication($listing)->call('submit')->assertHasNoErrors()->assertSee('Application sent');

    expect(RentalApplication::query()->sole()->status)->toBe(ApplicationStatus::New)
        ->and(Payment::query()->count())->toBe(0);
    Http::assertNothingSent();
});

test('only homes for rent take applications', function () {
    $this->get(route('listings.apply', Listing::factory()->create()))->assertNotFound();
});

test('an agent sees only applications and payments for their own homes', function () {
    $agent = Agent::factory()->create();
    $mine = RentalApplication::factory()->for(Listing::factory()->forRent()->for($agent))->create(['name' => 'Mine Applicant']);
    $other = RentalApplication::factory()->create(['name' => 'Other Applicant']);

    $this->actingAs($agent->user)->get(route('dashboard.applications.index'))->assertOk()->assertSee('Mine Applicant')->assertDontSee('Other Applicant');
    $this->actingAs($agent->user)->get(route('dashboard.applications.show', $other))->assertForbidden();

    Livewire::actingAs($agent->user)->test('pages::dashboard.applications.show', ['application' => $mine])->call('decide', 'approved');
    expect($mine->fresh()->status)->toBe(ApplicationStatus::Approved);
});

test('the admin sets the provider and prices; agents cannot', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)->test('pages::dashboard.payment-setup')
        ->set('payment_gateway', 'paystack')
        ->set('payment_currency', 'NGN')
        ->set('application_fee', '20000')
        ->call('save')
        ->assertHasNoErrors();

    expect(Setting::get('payment_gateway'))->toBe('paystack')->and(Setting::get('payment_currency'))->toBe('NGN');

    Livewire::actingAs($admin)->test('pages::dashboard.payment-setup')
        ->set('payment_gateway', 'stripe')
        ->set('payment_currency', 'NGN')
        ->call('save')
        ->assertHasErrors('payment_currency');

    $this->actingAs(Agent::factory()->create()->user)->get(route('dashboard.payment-setup'))->assertForbidden();
});
