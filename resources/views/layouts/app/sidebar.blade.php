<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ auth()->user()->isStaff() ? route('dashboard') : route('home') }}" />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            @php($user = auth()->user())
            <flux:sidebar.nav>
                @unless ($user->isStaff())
                    {{-- Visitors only see this layout on their account settings. --}}
                    <flux:sidebar.group :heading="__('My account')" class="grid">
                        <flux:sidebar.item icon="heart" :href="route('saved')">{{ __('Saved homes & searches') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="magnifying-glass" :href="route('search')">{{ __('Search homes') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="user" :href="route('profile.edit')" :current="request()->routeIs('profile.edit', 'security.edit', 'appearance.edit')" wire:navigate>{{ __('Account settings') }}</flux:sidebar.item>
                    </flux:sidebar.group>
                @else
                <flux:sidebar.group :heading="__('Work')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Overview') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="building-office-2" :href="route('dashboard.listings.index')" :current="request()->routeIs('dashboard.listings.*')" wire:navigate>
                        {{ $user->isAdmin() ? __('Listings') : __('My listings') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="inbox" :href="route('dashboard.enquiries.index')" :current="request()->routeIs('dashboard.enquiries.*')" :badge="\App\Models\Enquiry::query()->visibleTo($user)->where('status', \App\Enums\EnquiryStatus::New)->count() ?: null" wire:navigate>
                        {{ __('Enquiries & tours') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="document-text" :href="route('dashboard.applications.index')" :current="request()->routeIs('dashboard.applications.*')" :badge="\App\Models\RentalApplication::query()->visibleTo($user)->where('status', \App\Enums\ApplicationStatus::New)->count() ?: null" wire:navigate>
                        {{ __('Rental applications') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="credit-card" :href="route('dashboard.payments')" :current="request()->routeIs('dashboard.payments')" wire:navigate>
                        {{ __('Payments') }}
                    </flux:sidebar.item>
                    @if ($user->isAgent())
                        <flux:sidebar.item icon="user-circle" :href="route('dashboard.profile')" :current="request()->routeIs('dashboard.profile')" wire:navigate>
                            {{ __('My agent profile') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>

                @if ($user->isAdmin())
                    <flux:sidebar.group :heading="__('Office')" class="grid">
                        <flux:sidebar.item icon="users" :href="route('dashboard.agents.index')" :current="request()->routeIs('dashboard.agents.*')" wire:navigate>
                            {{ __('Agents') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="map" :href="route('dashboard.cities.index')" :current="request()->routeIs('dashboard.cities.*')" wire:navigate>
                            {{ __('Cities') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="calendar-days" :href="route('dashboard.bookings')" :current="request()->routeIs('dashboard.bookings')" :badge="\App\Models\ServiceBooking::query()->where('status', \App\Enums\ApplicationStatus::New)->count() ?: null" wire:navigate>
                            {{ __('Service bookings') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>

                    <flux:sidebar.group :heading="__('Setup')" class="grid">
                        <flux:sidebar.item icon="home-modern" :href="route('dashboard.home-types')" :current="request()->routeIs('dashboard.home-types')" wire:navigate>
                            {{ __('Home types') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="sparkles" :href="route('dashboard.amenities')" :current="request()->routeIs('dashboard.amenities')" wire:navigate>
                            {{ __('Amenities') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="briefcase" :href="route('dashboard.services')" :current="request()->routeIs('dashboard.services')" wire:navigate>
                            {{ __('Services') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="banknotes" :href="route('dashboard.payment-setup')" :current="request()->routeIs('dashboard.payment-setup')" :badge="\App\Models\Setting::get('payment_gateway') === 'none' ? __('Off') : ucfirst(\App\Models\Setting::get('payment_gateway'))" wire:navigate>
                            {{ __('Payments setup') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="arrow-down-tray" :href="route('dashboard.import')" :current="request()->routeIs('dashboard.import')" :badge="app(\App\Services\RentCast::class)->isEnabled() ? __('On') : __('Off')" wire:navigate>
                            {{ __('Live homes') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="cog-6-tooth" :href="route('dashboard.settings')" :current="request()->routeIs('dashboard.settings')" wire:navigate>
                            {{ __('Business details') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
                @endunless
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="arrow-top-right-on-square" :href="route('home')" target="_blank">
                    {{ __('View website') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
