<?php

use App\Models\Listing;
use App\Models\User;
use App\Notifications\EmailVerificationCode;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

/**
 * Send a code to the user and return the code from the captured email.
 */
function emailedCode(User $user): string
{
    $user->sendEmailVerificationNotification();

    $code = '';
    Notification::assertSentTo($user, EmailVerificationCode::class, function (EmailVerificationCode $notification) use (&$code) {
        $code = $notification->code;

        return true;
    });

    return $code;
}

beforeEach(function () {
    Notification::fake();
});

test('signing up emails a 6-digit code', function () {
    $this->post(route('register.store'), [
        'name' => 'Jordan Taylor',
        'email' => 'jordan@example.com',
        'password' => 'a-strong-password',
        'password_confirmation' => 'a-strong-password',
    ]);

    $user = User::query()->where('email', 'jordan@example.com')->sole();

    expect($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, EmailVerificationCode::class, fn (EmailVerificationCode $notification) => preg_match('/^\d{6}$/', $notification->code) === 1);
});

test('the right code confirms the email and goes on to saved homes', function () {
    Event::fake([Verified::class]);
    $user = User::factory()->unverified()->create();
    $code = emailedCode($user);

    Livewire::actingAs($user)->test('verify-email-code')
        ->set('code', $code)
        ->call('verify')
        ->assertHasNoErrors()
        ->assertRedirect(route('saved'));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertDispatched(Verified::class);
});

test('a wrong code is refused and a code only works once', function () {
    $user = User::factory()->unverified()->create();
    $code = emailedCode($user);
    $wrong = $code === '000000' ? '111111' : '000000';

    Livewire::actingAs($user)->test('verify-email-code')
        ->set('code', $wrong)
        ->call('verify')
        ->assertHasErrors('code');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();

    Livewire::actingAs($user)->test('verify-email-code')->set('code', $code)->call('verify')->assertHasNoErrors();

    $user->forceFill(['email_verified_at' => null])->save();

    Livewire::actingAs($user->fresh())->test('verify-email-code')->set('code', $code)->call('verify')->assertHasErrors('code');
});

test('an expired code is refused', function () {
    $user = User::factory()->unverified()->create();
    $code = emailedCode($user);

    $this->travel(16)->minutes();

    Livewire::actingAs($user)->test('verify-email-code')->set('code', $code)->call('verify')->assertHasErrors('code');
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('guessing is stopped after five wrong codes', function () {
    $user = User::factory()->unverified()->create();
    RateLimiter::clear('verify-email-code:'.$user->id);
    $code = emailedCode($user);
    $wrong = $code === '000000' ? '111111' : '000000';

    $component = Livewire::actingAs($user)->test('verify-email-code');

    foreach (range(1, 5) as $attempt) {
        $component->set('code', $wrong)->call('verify');
    }

    $component->set('code', $code)->call('verify')->assertHasErrors('code')->assertSee('Too many wrong codes');
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('a new code can be sent once a minute', function () {
    $user = User::factory()->unverified()->create();
    RateLimiter::clear('resend-email-code:'.$user->id);

    Livewire::actingAs($user)->test('verify-email-code')
        ->call('resend')
        ->assertSet('resent', true)
        ->call('resend')
        ->assertHasErrors('resend');

    Notification::assertSentToTimes($user, EmailVerificationCode::class, 1);
});

test('the code page shows where the code was sent', function () {
    $user = User::factory()->unverified()->create(['email' => 'jordan@example.com']);

    $this->actingAs($user)->get(route('verification.notice'))
        ->assertOk()
        ->assertSee('Check your email')
        ->assertSee('jordan@example.com');
});

test('unconfirmed visitors must enter the code before saving homes', function () {
    $user = User::factory()->unverified()->create();
    $listing = Listing::factory()->create();

    $this->actingAs($user)->get(route('saved'))->assertRedirect(route('verification.notice'));
    $this->actingAs($user)->postJson(route('favorites.toggle', $listing))->assertForbidden();

    Livewire::actingAs($user)->test('pages::search')->call('saveSearch')->assertRedirect(route('verification.notice'));

    expect($user->favorites()->count())->toBe(0);
});
