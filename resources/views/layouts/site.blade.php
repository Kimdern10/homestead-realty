@props([
    'title' => null,
    'description' => null,
    'image' => null,
    'type' => 'website',
    'current' => null,
    'canonical' => null,
    'noindex' => false,
])

@php
    $site = \App\Models\Setting::values();
    $pageTitle = filled($title) ? $title.' | '.$site['business_name'] : $site['business_name'].' | '.$site['seo_title'];
    $metaDescription = $description ?: $site['seo_description'];
    $user = auth()->user();
    $links = [
        'home' => ['Home', route('home')],
        'buy' => ['Buy', route('search', ['status' => 'for_sale'])],
        'rent' => ['Rent', route('search', ['status' => 'for_rent'])],
        'sold' => ['Sold', route('search', ['status' => 'sold'])],
        'cities' => ['Cities', route('cities.index')],
        'agents' => ['Find an agent', route('agents.index')],
    ];
    $footerCities = \App\Models\City::query()->ordered()->take(6)->get(['name', 'slug']);
    $socials = array_filter(['Facebook' => $site['facebook_url'], 'Instagram' => $site['instagram_url'], 'X' => $site['x_url'], 'LinkedIn' => $site['linkedin_url'], 'YouTube' => $site['youtube_url']]);
@endphp

<!DOCTYPE html>
<html lang="en-US">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <title>{{ $pageTitle }}</title>
        <meta name="description" content="{{ $metaDescription }}">
        <link rel="canonical" href="{{ $canonical ?: url()->current() }}">
        @if ($noindex)
            <meta name="robots" content="noindex, follow">
        @endif
        <meta name="theme-color" content="#d92228">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <meta property="og:type" content="{{ $type }}">
        <meta property="og:site_name" content="{{ $site['business_name'] }}">
        <meta property="og:title" content="{{ $title ?: $pageTitle }}">
        <meta property="og:description" content="{{ $metaDescription }}">
        <meta property="og:url" content="{{ $canonical ?: url()->current() }}">
        <meta property="og:locale" content="en_US">
        @if ($image)
            <meta property="og:image" content="{{ $image }}">
        @endif
        <meta name="twitter:card" content="{{ $image ? 'summary_large_image' : 'summary' }}">

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">

        <script>document.documentElement.classList.add('js'); try { var t = localStorage.getItem('hr-theme'); if (t === 'light' || t === 'dark') { document.documentElement.dataset.theme = t; } } catch (e) {}</script>

        @fonts('manrope')
        @fonts('fraunces')
        @vite(['resources/css/site.css', 'resources/js/site.js'])

        @livewireStyles
        {{ $head ?? '' }}
    </head>
    <body>
        <a class="skip" href="#main">Skip to content</a>

        <header class="site-header">
            <div class="container">
                <a class="brand" wire:navigate.hover href="{{ route('home') }}" aria-label="{{ $site['business_name'] }} home"><x-site-logo /></a>
                <nav class="site-nav" aria-label="Main">
                    @foreach ($links as $key => [$label, $href])
                        <a href="{{ $href }}" wire:navigate.hover @if ($current === $key || ($key === 'home' && request()->routeIs('home'))) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
                <div class="header-tools">
                    <button class="icon-btn theme-toggle" type="button" data-theme-toggle aria-label="Switch light or dark mode">
                        <svg class="icon icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                        <svg class="icon icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                    </button>
                    <a class="icon-btn icon-btn--saved" href="{{ route('saved') }}" aria-label="Saved homes" title="Saved homes" @if ($current === 'saved') aria-current="page" @endif><x-icon name="heart" /></a>
                    @if ($user?->isStaff())
                        <a class="btn btn--dark btn--sm btn--account" href="{{ route('dashboard') }}">Dashboard</a>
                    @elseif ($user)
                        <a class="btn btn--outline btn--sm btn--account" href="{{ route('profile.edit') }}"><x-icon name="user" /> {{ Str::before($user->name, ' ') }}</a>
                    @else
                        <a class="btn btn--outline btn--sm btn--account" href="{{ route('login') }}">Sign in</a>
                        <a class="btn btn--primary btn--sm btn--account" href="{{ route('register') }}">Join</a>
                    @endif
                    <button class="icon-btn menu-toggle" type="button" data-menu-toggle aria-label="Open menu" aria-expanded="false" aria-controls="mobile-menu"><span></span><span></span></button>
                </div>
            </div>
        </header>

        <nav class="mobile-menu" id="mobile-menu" aria-label="Main (mobile)">
            @foreach ($links as [$label, $href])
                <a href="{{ $href }}">{{ $label }}</a>
            @endforeach
            <a href="{{ route('saved') }}">Saved homes</a>
            @if ($user?->isStaff())
                <a class="btn btn--dark" href="{{ route('dashboard') }}">Go to your dashboard</a>
            @elseif ($user)
                <a class="btn btn--outline" href="{{ route('profile.edit') }}">Account settings</a>
            @else
                <a class="btn btn--primary" href="{{ route('register') }}">Create a free account</a>
                <a class="btn btn--outline" href="{{ route('login') }}">Sign in</a>
            @endif
        </nav>

        @if (session('status') && is_string(session('status')))
            <div class="flash" role="status">{{ session('status') }}</div>
        @endif

        <main id="main" class="page-enter">
            {{ $slot }}
        </main>

        <nav class="tabbar" aria-label="Quick links">
            <a wire:navigate.hover href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif><x-icon name="home" /><span>Home</span></a>
            <a wire:navigate.hover href="{{ route('search', ['status' => 'for_sale']) }}" @if (in_array($current, ['buy', 'rent', 'sold'], true)) aria-current="page" @endif><x-icon name="search" /><span>Search</span></a>
            <a wire:navigate.hover href="{{ route('search', ['view' => 'map']) }}"><x-icon name="map" /><span>Map</span></a>
            <a wire:navigate.hover href="{{ route('saved') }}" @if ($current === 'saved') aria-current="page" @endif><x-icon name="heart" /><span>Saved</span></a>
            <a wire:navigate.hover href="{{ $user?->isStaff() ? route('dashboard') : ($user ? route('profile.edit') : route('login')) }}"><x-icon name="user" /><span>{{ $user?->isStaff() ? 'Dashboard' : ($user ? 'Account' : 'Sign in') }}</span></a>
        </nav>

        <footer class="site-footer">
            <div class="container">
                <div class="footer__grid">
                    <div class="footer__brand">
                        <a class="brand" wire:navigate.hover href="{{ route('home') }}" aria-label="{{ $site['business_name'] }} home"><x-site-logo /></a>
                        <p>{{ $site['tagline'] }}</p>
                    </div>
                    <div>
                        <h4>Search</h4>
                        <ul>
                            <li><a href="{{ route('search', ['status' => 'for_sale']) }}">Homes for sale</a></li>
                            <li><a href="{{ route('search', ['status' => 'for_rent']) }}">Homes for rent</a></li>
                            <li><a href="{{ route('search', ['status' => 'sold']) }}">Recently sold</a></li>
                            <li><a href="{{ route('search', ['view' => 'map']) }}">Search on a map</a></li>
                            <li><a href="{{ route('agents.index') }}">Find an agent</a></li>
                        </ul>
                    </div>
                    @if ($footerCities->isNotEmpty())
                        <div>
                            <h4>Popular cities</h4>
                            <ul>
                                @foreach ($footerCities as $footerCity)
                                    <li><a href="{{ route('cities.show', $footerCity) }}">{{ $footerCity->name }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <div>
                        <h4>Contact us</h4>
                        <ul class="footer__contact">
                            @if (filled($site['phone']))
                                <li><x-icon name="phone" /><a href="tel:{{ preg_replace('/[^0-9+]/', '', $site['phone']) }}">{{ $site['phone'] }}</a></li>
                            @endif
                            @if (filled($site['email']))
                                <li><x-icon name="mail" /><a href="mailto:{{ $site['email'] }}">{{ $site['email'] }}</a></li>
                            @endif
                            @if (filled($site['address']))
                                <li><x-icon name="pin" /><span>{{ $site['address'] }}</span></li>
                            @endif
                            @if (filled($site['office_hours']))
                                <li><x-icon name="clock" /><span>{{ $site['office_hours'] }}</span></li>
                            @endif
                        </ul>
                        @if ($socials)
                            <ul style="margin-top: 12px">
                                @foreach ($socials as $network => $url)
                                    <li><a href="{{ $url }}" target="_blank" rel="noopener">{{ $network }}</a></li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
                <div class="footer__legal">
                    <p class="eho">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="m2 11 10-8 10 8"/><path d="M4.5 9.5V21h15V9.5"/><path d="M8.5 13h7M8.5 16.5h7"/></svg>
                        <span>Equal Housing Opportunity. We are committed to the Fair Housing Act and do not discriminate on the basis of race, color, religion, sex, disability, familial status or national origin.@if (filled($site['broker_license'])) Broker license {{ $site['broker_license'] }}.@endif</span>
                    </p>
                    <p>© {{ now()->year }} {{ $site['business_name'] }} · <a href="{{ route('privacy') }}">Privacy policy</a> · <a href="{{ route('sitemap') }}">Sitemap</a></p>
                </div>
            </div>
        </footer>
        @livewireScripts
    </body>
</html>
