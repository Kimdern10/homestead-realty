<?php

use App\Support\EmailVerificationCodes;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

new class extends Component {
    /**
     * Wrong codes allowed before a pause.
     */
    public const int MAX_ATTEMPTS = 5;

    public string $code = '';

    public bool $resent = false;

    /**
     * Check the code and, when it matches, confirm the email.
     */
    public function verify(EmailVerificationCodes $codes): void
    {
        $user = auth()->user();
        $this->resetErrorBag();
        $this->resent = false;

        if ($user->hasVerifiedEmail()) {
            $this->finish();

            return;
        }

        $limiterKey = 'verify-email-code:'.$user->id;

        if (RateLimiter::tooManyAttempts($limiterKey, self::MAX_ATTEMPTS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($limiterKey) / 60);
            $this->addError('code', "Too many wrong codes. Please wait {$minutes} minutes, or send a new code.");

            return;
        }

        $this->validate(['code' => ['required', 'digits:6']], [
            'code.required' => 'Enter the 6-digit code from the email.',
            'code.digits' => 'The code has 6 numbers.',
        ]);

        if (! $codes->check($user, $this->code)) {
            RateLimiter::hit($limiterKey, 600);
            $this->reset('code');
            $this->addError('code', 'That code is not right or has expired. Check the email or send a new code.');

            return;
        }

        RateLimiter::clear($limiterKey);

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        $this->finish();
    }

    /**
     * Email a fresh code (at most once a minute).
     */
    public function resend(): void
    {
        $user = auth()->user();
        $limiterKey = 'resend-email-code:'.$user->id;

        if (RateLimiter::tooManyAttempts($limiterKey, 1)) {
            $this->addError('resend', 'Please wait '.RateLimiter::availableIn($limiterKey).' seconds before asking for another code.');

            return;
        }

        RateLimiter::hit($limiterKey, 60);
        RateLimiter::clear('verify-email-code:'.$user->id);
        $user->sendEmailVerificationNotification();

        $this->reset('code');
        $this->resetErrorBag();
        $this->resent = true;
    }

    /**
     * Go on to where the visitor was heading: their saved homes, or the dashboard for staff.
     */
    private function finish(): void
    {
        $this->redirectIntended(auth()->user()->isStaff() ? route('dashboard') : route('saved'));
    }
}; ?>

<div class="flex flex-col gap-6">
    <form wire:submit="verify" class="flex flex-col gap-5">
        <div class="flex flex-col items-center" x-data x-init="$nextTick(() => $el.querySelector('input')?.focus())">
            <flux:otp
                name="code"
                wire:model="code"
                length="6"
                :label="__('Verification code')"
                label:sr-only
                class="mx-auto"
                data-test="email-code"
            />
        </div>

        @error('code')
            <flux:text class="text-center text-sm text-red-600! dark:text-red-400!" role="alert">{{ $message }}</flux:text>
        @enderror

        @if ($resent)
            <flux:text class="text-center text-sm text-green-700! dark:text-green-400!" role="status">{{ __('A new code is on its way. Check your inbox and spam folder.') }}</flux:text>
        @endif

        <flux:button type="submit" variant="primary" class="w-full" data-test="verify-email-button">
            <span wire:loading.remove wire:target="verify">{{ __('Confirm email') }}</span>
            <span wire:loading wire:target="verify">{{ __('Checking…') }}</span>
        </flux:button>
    </form>

    <div class="flex flex-col items-center gap-2 text-sm">
        <div class="text-zinc-600 dark:text-zinc-400">
            {{ __('Didn’t get it?') }}
            <flux:link as="button" class="cursor-pointer" wire:click="resend" data-test="resend-code">{{ __('Send a new code') }}</flux:link>
        </div>
        @error('resend')
            <flux:text class="text-center text-sm text-red-600! dark:text-red-400!">{{ $message }}</flux:text>
        @enderror
    </div>
</div>
