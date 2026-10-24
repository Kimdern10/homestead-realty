@php
    $description = $city->tagline ?: "Homes for sale and rent in {$city->label()}.";
    $mapListings = $forSale->concat($forRent)->filter->hasLocation();
@endphp

<x-layouts::site :title="'Homes for sale and rent in '.$city->label()" :description="$description.' See prices, neighborhoods and local agents.'" :image="$city->imageUrl()" current="cities">
    <x-slot:head>
        <script type="application/ld+json">{!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Place',
            'name' => $city->label(),
            'url' => route('cities.show', $city),
            'description' => $description,
            'geo' => ['@type' => 'GeoCoordinates', 'latitude' => (float) $city->latitude, 'longitude' => (float) $city->longitude],
            'address' => ['@type' => 'PostalAddress', 'addressLocality' => $city->name, 'addressRegion' => $city->state_code, 'addressCountry' => 'US'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    </x-slot:head>

    <section class="city-hero">
        @if ($image = $city->imageUrl())
            <img src="{{ $image }}" alt="" fetchpriority="high" width="1600" height="900">
        @endif
        <div class="container">
            <nav class="crumbs" aria-label="Breadcrumb"><a href="{{ route('cities.index') }}">Cities</a><span aria-hidden="true">/</span><span>{{ $city->state }}</span></nav>
            <h1>{{ $city->name }}, {{ $city->state_code }}</h1>
            @if ($city->tagline)<p>{{ $city->tagline }}</p>@endif
            <a class="btn btn--primary" href="{{ route('search', ['q' => $city->name, 'status' => 'for_sale']) }}"><x-icon name="search" /> Search {{ $city->name }} homes</a>
        </div>
    </section>

    <section class="section section--tight section--soft">
        <div class="container">
            <div class="stats">
                <div class="stat"><b>{{ number_format($stats['for_sale']) }}</b><span>Homes for sale</span></div>
                <div class="stat"><b>{{ $stats['median_price'] ? '$'.number_format($stats['median_price']) : '—' }}</b><span>Median list price</span></div>
                <div class="stat"><b>{{ number_format($stats['for_rent']) }}</b><span>Homes for rent</span></div>
                <div class="stat"><b>{{ $stats['median_rent'] ? '$'.number_format($stats['median_rent']).'/mo' : '—' }}</b><span>Median rent</span></div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container two-col">
            <div>
                <h2 style="font-size: 26px; margin-bottom: 12px">Living in {{ $city->name }}</h2>
                <div class="prose">
                    @foreach (preg_split('/\R\s*\R/', trim((string) $city->description)) as $paragraph)
                        @if (filled($paragraph))<p>{{ $paragraph }}</p>@endif
                    @endforeach
                </div>
                @if ($neighborhoods->isNotEmpty())
                    <h3 style="font-size: 18px; margin: 24px 0 12px">Neighborhoods</h3>
                    <div class="hero__chips" style="margin: 0">
                        @foreach ($neighborhoods as $name => $total)
                            <a class="chip" href="{{ route('search', ['q' => $name]) }}">{{ $name }} <span class="muted">({{ $total }})</span></a>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="listing-map" style="height: 380px" data-map data-lat="{{ $city->latitude }}" data-lng="{{ $city->longitude }}" data-zoom="11" data-points='@json($mapListings->map->mapPoint()->values())'>
                <span class="map-note">Loading map…</span>
            </div>
        </div>
    </section>

    @if ($forSale->isNotEmpty())
        <section class="section section--soft">
            <div class="container">
                <div class="section-head">
                    <h2>Homes for sale in {{ $city->name }}</h2>
                    <a class="link" href="{{ route('search', ['q' => $city->name, 'status' => 'for_sale']) }}">See all {{ $stats['for_sale'] }} <x-icon name="arrow-right" /></a>
                </div>
                <div class="grid grid--cards">
                    @foreach ($forSale as $listing)
                        <x-listing-card :listing="$listing" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($forRent->isNotEmpty())
        <section class="section">
            <div class="container">
                <div class="section-head">
                    <h2>Rentals in {{ $city->name }}</h2>
                    <a class="link" href="{{ route('search', ['q' => $city->name, 'status' => 'for_rent']) }}">See all {{ $stats['for_rent'] }} <x-icon name="arrow-right" /></a>
                </div>
                <div class="scroller">
                    @foreach ($forRent as $listing)
                        <x-listing-card :listing="$listing" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($sold->isNotEmpty())
        <section class="section section--soft">
            <div class="container">
                <div class="section-head">
                    <h2>Recently sold</h2>
                    <a class="link" href="{{ route('search', ['q' => $city->name, 'status' => 'sold']) }}">More sold homes <x-icon name="arrow-right" /></a>
                </div>
                <div class="scroller">
                    @foreach ($sold as $listing)
                        <x-listing-card :listing="$listing" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($agents->isNotEmpty())
        <section class="section">
            <div class="container">
                <div class="section-head"><h2>{{ $city->name }} agents</h2></div>
                <div class="grid grid--agents">
                    @foreach ($agents as $agent)
                        <x-agent-card :agent="$agent" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-layouts::site>
