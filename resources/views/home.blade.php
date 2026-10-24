@php
    $site = \App\Models\Setting::values();
    $heroListing = $featured->first(fn ($listing) => $listing->coverPhoto !== null);
@endphp

<x-layouts::site :image="$heroListing?->coverUrl(false)">
    <x-slot:head>
        <script type="application/ld+json">{!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'RealEstateAgent',
            'name' => $site['business_name'],
            'url' => route('home'),
            'telephone' => $site['phone'] ?: null,
            'email' => $site['email'] ?: null,
            'address' => $site['address'] ?: null,
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => route('search').'?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    </x-slot:head>

    <section class="hero">
        @if ($heroListing)
            <img class="hero__img" src="{{ $heroListing->coverUrl(false) }}" alt="" fetchpriority="high" width="1600" height="1067">
        @endif
        <div class="container">
            <h1>{{ $site['hero_title'] }}</h1>
            <p>{{ $site['hero_text'] }}</p>

            <form class="hero__search" action="{{ route('search') }}" method="get" role="search">
                <div class="search-tabs" role="radiogroup" aria-label="What are you looking for?">
                    <label><input type="radio" name="status" value="for_sale" checked><span>Buy</span></label>
                    <label><input type="radio" name="status" value="for_rent"><span>Rent</span></label>
                    <label><input type="radio" name="status" value="sold"><span>Sold</span></label>
                </div>
                <div class="search-box">
                    <x-icon name="search" />
                    <label class="sr-only" for="hero-q">City, neighborhood, address or ZIP</label>
                    <input id="hero-q" name="q" type="search" placeholder="City, neighborhood, address or ZIP" autocomplete="off">
                    <button class="btn btn--primary" type="submit"><x-icon name="search" /><span>Search</span></button>
                </div>
            </form>

            @if ($cities->isNotEmpty())
                <div class="hero__chips" aria-label="Popular cities">
                    @foreach ($cities->take(5) as $city)
                        <a href="{{ route('cities.show', $city) }}">{{ $city->name }}, {{ $city->state_code }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    @if ($featured->isNotEmpty())
        <section class="section">
            <div class="container">
                <div class="section-head reveal">
                    <div>
                        <span class="eyebrow">Featured</span>
                        <h2>Hand-picked homes</h2>
                        <p>Standout homes our agents think you'll love.</p>
                    </div>
                    <a class="link" href="{{ route('search') }}">See all homes <x-icon name="arrow-right" /></a>
                </div>
                <div class="grid grid--cards">
                    @foreach ($featured as $listing)
                        <x-listing-card :listing="$listing" :lazy="! $loop->first" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="section section--soft">
        <div class="container">
            <div class="section-head reveal">
                <div>
                    <span class="eyebrow">How we help</span>
                    <h2>Everything you need to move</h2>
                </div>
            </div>
            <div class="how">
                <div class="how__item reveal">
                    <div class="how__icon"><x-icon name="map" /></div>
                    <h3>Search on a map</h3>
                    <p>Filter by price, beds, baths and home type, then see exactly where every home sits.</p>
                </div>
                <div class="how__item reveal">
                    <div class="how__icon"><x-icon name="heart" /></div>
                    <h3>Save what you love</h3>
                    <p>Create a free account to keep your favorite homes and searches in one place.</p>
                </div>
                <div class="how__item reveal">
                    <div class="how__icon"><x-icon name="calendar" /></div>
                    <h3>Tour on your schedule</h3>
                    <p>Pick a day and time on any listing, in person or by video chat. The agent confirms with you.</p>
                </div>
                <div class="how__item reveal">
                    <div class="how__icon"><x-icon name="calculator" /></div>
                    <h3>Know your payment</h3>
                    <p>Every home for sale has a payment calculator with taxes, insurance and HOA included.</p>
                </div>
            </div>
        </div>
    </section>

    @if ($newest->isNotEmpty())
        <section class="section">
            <div class="container">
                <div class="section-head reveal">
                    <div>
                        <span class="eyebrow">Just listed</span>
                        <h2>New homes for sale</h2>
                    </div>
                    <a class="link" href="{{ route('search', ['status' => 'for_sale']) }}">All homes for sale <x-icon name="arrow-right" /></a>
                </div>
                <div class="scroller">
                    @foreach ($newest as $listing)
                        <x-listing-card :listing="$listing" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($cities->isNotEmpty())
        <section class="section section--soft">
            <div class="container">
                <div class="section-head reveal">
                    <div>
                        <span class="eyebrow">Explore</span>
                        <h2>Popular cities</h2>
                        <p>Market numbers, neighborhoods and homes in the places people are moving to.</p>
                    </div>
                    <a class="link" href="{{ route('cities.index') }}">All cities <x-icon name="arrow-right" /></a>
                </div>
                <div class="grid grid--cities">
                    @foreach ($cities as $city)
                        <x-city-card :city="$city" :count="$city->available_count" class="reveal" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($rentals->isNotEmpty())
        <section class="section">
            <div class="container">
                <div class="section-head reveal">
                    <div>
                        <span class="eyebrow">For rent</span>
                        <h2>Places to rent now</h2>
                    </div>
                    <a class="link" href="{{ route('search', ['status' => 'for_rent']) }}">All rentals <x-icon name="arrow-right" /></a>
                </div>
                <div class="scroller">
                    @foreach ($rentals as $listing)
                        <x-listing-card :listing="$listing" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($agents->isNotEmpty())
        <section class="section section--soft">
            <div class="container">
                <div class="section-head reveal">
                    <div>
                        <span class="eyebrow">Local experts</span>
                        <h2>Meet our agents</h2>
                    </div>
                    <a class="link" href="{{ route('agents.index') }}">All agents <x-icon name="arrow-right" /></a>
                </div>
                <div class="grid grid--agents">
                    @foreach ($agents as $agent)
                        <x-agent-card :agent="$agent" class="reveal" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="section">
        <div class="container">
            <div class="cta reveal">
                <div>
                    <h2>Thinking of selling?</h2>
                    <p>Talk to a local agent about what your home could sell for. No pressure, no obligation.</p>
                </div>
                <div class="cta__actions">
                    <a class="btn btn--primary" href="{{ route('agents.index') }}">Find an agent <x-icon name="arrow-right" /></a>
                    @if (filled($site['phone']))
                        <a class="btn btn--outline" href="tel:{{ preg_replace('/[^0-9+]/', '', $site['phone']) }}"><x-icon name="phone" /> {{ $site['phone'] }}</a>
                    @endif
                </div>
            </div>
        </div>
    </section>
</x-layouts::site>
