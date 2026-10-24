<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Welcome back')" :description="__('Sign in to see your saved homes and searches. Agents and the office team sign in here too.')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('Sign in') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Looking for a home?') }}</span>
            <flux:link :href="route('register')" wire:navigate>{{ __('Create a free account') }}</flux:link>
        </div>

        <div class="flex gap-3 rounded-xl border border-zinc-200 bg-white p-4 text-sm dark:border-zinc-700 dark:bg-zinc-900" data-test="agent-login-note">
            <flux:icon name="briefcase" class="mt-0.5 size-5 shrink-0 text-[#d92228]" />
            <div>
                <p class="font-semibold text-zinc-900 dark:text-white">{{ __('Are you an agent?') }}</p>
                <p class="mt-0.5 text-zinc-600 dark:text-zinc-400">{{ __('Agent accounts are created by the office admin. Sign in with the email and password the office gave you; there is no need to sign up.') }}</p>
            </div>
        </div>
    </div>
</x-layouts::auth>
