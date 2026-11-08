<?php

use App\Models\Setting;
use App\Models\User;
use App\Notifications\EmailVerificationCode;
use Illuminate\Auth\Notifications\ResetPassword;

beforeEach(function () {
    Setting::put(['business_name' => 'Oak & Key Homes']);
});

test('the password reset email uses the site design, logo and wording', function () {
    $user = User::factory()->create(['name' => 'Jamie Rivera']);

    $mail = (new ResetPassword('reset-token'))->toMail($user);
    $html = (string) $mail->render();

    expect($mail->subject)->toBe('Reset your Oak & Key Homes password')
        ->and($html)->toContain(asset('images/email-logo.png'))
        ->and($html)->toContain('Hi Jamie')
        ->and($html)->toContain('reset-password/reset-token')
        ->and($html)->not->toContain('Laravel');
});

test('the email code uses the site design and logo', function () {
    $html = (string) (new EmailVerificationCode('482913'))->toMail(User::factory()->create())->render();

    expect($html)->toContain(asset('images/email-logo.png'))
        ->and($html)->toContain('Oak &amp; Key Homes')
        ->and($html)->toContain('482913');
});
