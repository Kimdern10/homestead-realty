<?php

namespace App\Providers;

use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Pages move without full reloads (wire:navigate). When the next page needs a different
        // stylesheet (website ↔ dashboard), Livewire sees the change and reloads it fully instead.
        Vite::useScriptTagAttributes(['data-navigate-track' => 'reload']);
        Vite::useStyleTagAttributes(['data-navigate-track' => 'reload']);

        // The password reset email uses the site's own design and wording.
        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $business = Setting::get('business_name');

            return (new MailMessage)
                ->subject("Reset your {$business} password")
                ->markdown('mail.reset-password', [
                    'name' => $notifiable->name ?? null,
                    'url' => route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()]),
                    'minutes' => (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60),
                    'business' => $business,
                ]);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
