@php
    use App\Enums\ListingStatus;
    use App\Enums\PropertyType;
    use App\Models\Setting;

    $photos = $listing->photos;
    $photoData = $photos->map(fn ($photo) => ['large' => $photo->url(), 'small' => $photo->url(small: true)])->values();
    $isRental = $listing->status->isRental();
    $showCalculator = in_array($listing->status, [ListingStatus::ForSale, ListingStatus::Pending], true) && $listing->price > 0;
    $description = Str::limit(trim(($listing->facts() ? implode(', ', $listing->facts()).'. ' : '').$listing->description), 155);
    $pageTitle = $listing->fullAddress().' | '.$listing->status->label();

    $structured = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'RealEstateListing',
        'name' => $listing->title,
        'url' => route('listings.show', $listing),
        'description' => Str::limit($listing->description, 500),
        'datePosted' => $listing->listed_at->toDateString(),
        'image' => $photos->take(8)->map(fn ($photo) => $photo->url())->values()->all() ?: null,
        'offers' => [
            '@type' => 'Offer',
            'price' => $listing->price,
            'priceCurrency' => 'USD',
            'availability' => $listing->status->isAvailable() ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
            'businessFunction' => $isRental ? 'http://purl.org/goodrelations/v1#LeaseOut' : 'http://purl.org/goodrelations/v1#Sell',
        ],
        'mainEntity' => array_filter([
            '@type' => $listing->property_type->schemaType(),
            'name' => $listing->address,
            'numberOfRooms' => $listing->beds,
            'numberOfBedrooms' => $listing->beds,
            'numberOfBathroomsTotal' => $listing->baths !== null ? (float) $listing->baths : null,
            'yearBuilt' => $listing->year_built,
            'floorSize' => $listing->sqft ? ['@type' => 'QuantitativeValue', 'value' => $listing->sqft, 'unitCode' => 'FTK'] : null,
            'amenityFeature' => collect($listing->amenities ?? [])->map(fn ($amenity) => ['@type' => 'LocationFeatureSpecification', 'name' => $amenity, 'value' => true])->all() ?: null,
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $listing->address,
                'addressLocality' => $listing->city->name,
                'addressRegion' => $listing->city->state_code,
                'postalCode' => $listing->zip,
                'addressCountry' => 'US',
            ],
            'geo' => $listing->hasLocation() ? ['@type' => 'GeoCoordinates', 'latitude' => (float) $listing->latitude, 'longitude' => (float) $listing->longitude] : null,
        ]),
    ]);

    $breadcrumbs = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $listing->city->label(), 'item' => route('cities.show', $listing->city)],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $listing->address, 'item' => route('listings.show', $listing)],
        ],
    ];

    $facts = array_filter([
        'Property type' => $listing->property_type->label(),
        'Status' => $listing->status->label(),
        'Year built' => $listing->year_built,
        'Lot size' => $listing->lot_acres ? rtrim(rtrim($listing->lot_acres, '0'), '.').' acres' : null,
        'Garage' => $listing->garage_spaces ? $listing->garage_spaces.' '.Str::plural('space', $listing->garage_spaces) : null,
        'Price per sqft' => $listing->pricePerSqft() ? '$'.number_format($listing->pricePerSqft()) : null,
        'HOA fee' => $listing->hoa_fee ? '$'.number_format($listing->hoa_fee).'/mo' : null,
        'Property tax' => $listing->property_tax ? '$'.number_format($listing->property_tax).'/yr' : null,
        'Neighborhood' => $listing->neighborhood,
        'Listed' => $listing->listed_at->format('M j, Y'),
        'Sold' => $listing->sold_at?->format('M j, Y'),
    ]);

    $galleryClass = match (true) {
        $photos->count() <= 1 => 'gallery--single',
        $photos->count() < 5 => 'gallery--few',
        default => '',
    };
@endphp

<x-layouts::site :title="$pageTitle" :description="$description" :image="$photos->first()?->url()" type="article">
    <x-slot:head>
        <script type="application/ld+json">{!! json_encode($structured, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
        <script type="application/ld+json">{!! json_encode($breadcrumbs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    </x-slot:head>

    <div class="container">
        <nav class="crumbs" aria-label="Breadcrumb" style="margin-top: 16px">
            <a href="{{ route('search', ['status' => $listing->status->value]) }}">{{ $listing->status->label() }}</a><span aria-hidden="true">/</span>
            <a href="{{ route('cities.show', $listing->city) }}">{{ $listing->city->label() }}</a>
            @if ($listing->neighborhood)
                <span aria-hidden="true">/</span><a href="{{ route('search', ['q' => $listing->neighborhood, 'status' => $listing->status->value]) }}">{{ $listing->neighborhood }}</a>
            @endif
        </nav>

        @if ($photos->isEmpty())
            <div class="gallery__empty"><span><x-icon name="camera" /> Photos coming soon</span></div>
        @else
            <div class="gallery {{ $galleryClass }}">
                @foreach ($photos->take($photos->count() < 5 ? 3 : 5) as $photo)
                    <button type="button" class="{{ $loop->first ? 'gallery__main' : 'gallery__thumb' }}" data-open-photo="{{ $loop->index }}" aria-label="Open photo {{ $loop->iteration }} of {{ $photos->count() }}">
                        <img src="{{ $photo->url(small: ! $loop->first) }}" alt="{{ $loop->first ? $listing->address : '' }}" @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif width="{{ $photo->width ?: 1600 }}" height="{{ $photo->height ?: 1067 }}">
                    </button>
                @endforeach
                <div class="gallery__all">
                    <button type="button" class="btn btn--sm" data-open-photo="0"><x-icon name="camera" /> {{ $photos->count() }} {{ Str::plural('photo', $photos->count()) }}</button>
                </div>
            </div>

            <dialog class="lightbox" data-lightbox data-photos='@json($photoData)' data-title="{{ $listing->address }}" aria-label="Photos of {{ $listing->address }}">
                <div class="lightbox__inner">
                    <div class="lightbox__top">
                        <span data-lightbox-counter>1 / {{ $photos->count() }}</span>
                        <button type="button" class="icon-btn" data-lightbox-close aria-label="Close photos"><x-icon name="x" /></button>
                    </div>
                    <div class="lightbox__stage">
                        <img data-lightbox-image src="" alt="">
                        @if ($photos->count() > 1)
                            <button type="button" class="icon-btn lightbox__nav lightbox__nav--prev" data-lightbox-prev aria-label="Previous photo"><x-icon name="chevron-left" /></button>
                            <button type="button" class="icon-btn lightbox__nav lightbox__nav--next" data-lightbox-next aria-label="Next photo"><x-icon name="chevron-right" /></button>
                        @else
                            <span hidden data-lightbox-prev></span><span hidden data-lightbox-next></span>
                        @endif
                    </div>
                    <div class="lightbox__strip" data-lightbox-strip></div>
                </div>
            </dialog>
        @endif

        <div class="listing-layout">
            <div>
                <section class="block">
                    <div class="listing-top">
                        <div>
                            <div class="listing-meta" style="margin: 0 0 10px">
                                <span class="badge badge--{{ $listing->status->tone() }}">{{ $listing->status->label() }}</span>
                                @if ($listing->isNew())<span class="badge badge--new">New</span>@endif
                            </div>
                            <div class="listing-price">
                                ${{ number_format($listing->price) }}@if ($isRental)<small>/month</small>@endif
                            </div>
                            <div class="listing-facts">
                                @if ($listing->beds !== null)
                                    <span><x-icon name="bed" /><b>{{ $listing->beds === 0 ? 'Studio' : $listing->beds }}</b>{{ $listing->beds === 0 ? '' : ' '.Str::plural('bed', $listing->beds) }}</span>
                                @endif
                                @if ($listing->baths !== null)
                                    <span><x-icon name="bath" /><b>{{ $listing->bathsLabel() }}</b> {{ Str::plural('bath', (float) $listing->baths === 1.0 ? 1 : 2) }}</span>
                                @endif
                                @if ($listing->sqft)
                                    <span><x-icon name="ruler" /><b>{{ number_format($listing->sqft) }}</b> sqft</span>
                                @endif
                                @if ($listing->property_type === PropertyType::Land && $listing->lot_acres)
                                    <span><x-icon name="map" /><b>{{ rtrim(rtrim($listing->lot_acres, '0'), '.') }}</b> acres</span>
                                @endif
                            </div>
                            <h1 class="listing-address" style="font-weight: 600; letter-spacing: 0">{{ $listing->address }}, <span class="muted">{{ $listing->locality() }}</span></h1>
                        </div>
                        <div class="listing-actions">
                            <x-favorite-button :listing="$listing" :large="true" />
                            <button type="button" class="fav-btn fav-btn--lg" data-share><x-icon name="share" /><span>Share</span></button>
                        </div>
                    </div>
                    <div class="listing-meta">
                        <span class="chip"><x-icon name="home" /> {{ $listing->property_type->label() }}</span>
                        @if ($listing->year_built)<span class="chip"><x-icon name="building" /> Built {{ $listing->year_built }}</span>@endif
                        @if ($listing->status->isAvailable())<span class="chip"><x-icon name="clock" /> {{ $listing->daysOnSite() === 0 ? 'Listed today' : $listing->daysOnSite().' '.Str::plural('day', $listing->daysOnSite()).' on site' }}</span>@endif
                        @if ($listing->neighborhood)<a class="chip" href="{{ route('search', ['q' => $listing->neighborhood]) }}"><x-icon name="pin" /> {{ $listing->neighborhood }}</a>@endif
                    </div>
                </section>

                @if ($listing->video_url || $listing->virtual_tour_url)
                    <section class="block">
                        <h2>Tour it online</h2>
                        <div class="tour-links">
                            @if ($listing->video_url)
                                <a class="btn btn--outline" href="{{ $listing->video_url }}" target="_blank" rel="noopener"><x-icon name="play" /> Watch the video</a>
                            @endif
                            @if ($listing->virtual_tour_url)
                                <a class="btn btn--outline" href="{{ $listing->virtual_tour_url }}" target="_blank" rel="noopener"><x-icon name="cube" /> 3D virtual tour</a>
                            @endif
                        </div>
                    </section>
                @endif

                <section class="block">
                    <h2>About this home</h2>
                    <div class="prose">
                        @foreach (preg_split('/\R\s*\R/', trim($listing->description)) as $paragraph)
                            <p>{!! nl2br(e($paragraph)) !!}</p>
                        @endforeach
                    </div>
                </section>

                <section class="block">
                    <h2>Facts and features</h2>
                    <dl class="facts-grid">
                        @foreach ($facts as $label => $value)
                            <div class="fact"><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                </section>

                @if (filled($listing->amenities))
                    <section class="block">
                        <h2>Amenities</h2>
                        <ul class="amenities">
                            @foreach ($listing->amenities as $amenity)
                                <li><x-icon name="check" /> {{ $amenity }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($showCalculator)
                    @php
                        $rate = (float) Setting::get('mortgage_rate') ?: 6.5;
                        $taxYearly = $listing->property_tax ?: (int) round($listing->price * 0.011);
                        $insuranceYearly = (int) round(max(1200, $listing->price * 0.0035) / 10) * 10;
                    @endphp
                    <section class="block" id="payment">
                        <h2>Monthly payment</h2>
                        <form class="calc" data-calculator onsubmit="return false">
                            <div class="calc__fields">
                                <label class="field field--wide">Home price
                                    <span class="calc__money"><span>$</span><input class="input" name="price" inputmode="numeric" value="{{ number_format($listing->price) }}"></span>
                                </label>
                                <label class="field">Down payment
                                    <span class="calc__money"><span>$</span><input class="input" name="down" inputmode="numeric" value=""></span>
                                </label>
                                <label class="field">Down payment %
                                    <span class="calc__pct"><input class="input" name="down_percent" inputmode="decimal" value="20"><span>%</span></span>
                                </label>
                                <label class="field">Interest rate
                                    <span class="calc__pct"><input class="input" name="rate" inputmode="decimal" value="{{ $rate }}"><span>%</span></span>
                                </label>
                                <label class="field">Loan length
                                    <select class="select" name="term">
                                        <option value="30" selected>30-year fixed</option>
                                        <option value="20">20-year fixed</option>
                                        <option value="15">15-year fixed</option>
                                        <option value="10">10-year fixed</option>
                                    </select>
                                </label>
                                <label class="field">Property tax (yearly)
                                    <span class="calc__money"><span>$</span><input class="input" name="tax" inputmode="numeric" value="{{ number_format($taxYearly) }}"></span>
                                </label>
                                <label class="field">Home insurance (yearly)
                                    <span class="calc__money"><span>$</span><input class="input" name="insurance" inputmode="numeric" value="{{ number_format($insuranceYearly) }}"></span>
                                </label>
                                <label class="field field--wide">HOA fees (monthly)
                                    <span class="calc__money"><span>$</span><input class="input" name="hoa" inputmode="numeric" value="{{ $listing->hoa_fee ?: 0 }}"></span>
                                </label>
                            </div>
                            <div>
                                <div class="calc__result">
                                    <div class="donut" data-donut>
                                        <div class="donut__hole"><div><strong data-out="total">$0</strong><small>per month</small></div></div>
                                    </div>
                                    <ul class="legend">
                                        <li><span><i style="background: var(--brand)"></i> Principal & interest</span><b data-out="principal">$0</b></li>
                                        <li><span><i style="background: var(--rent)"></i> Property tax</span><b data-out="tax">$0</b></li>
                                        <li><span><i style="background: var(--pending)"></i> Home insurance</span><b data-out="insurance">$0</b></li>
                                        <li><span><i style="background: var(--sold)"></i> HOA fees</span><b data-out="hoa">$0</b></li>
                                        <li><span>Loan amount</span><b data-out="loan">$0</b></li>
                                    </ul>
                                </div>
                                <p class="calc__note">An estimate only, not a loan offer. Your real rate depends on your credit, the lender and the loan type. Tax and insurance are estimates; edit them to match your quotes.</p>
                            </div>
                        </form>
                    </section>
                @endif

                @if ($listing->hasLocation())
                    <section class="block">
                        <h2>Location</h2>
                        <div class="listing-map" data-map data-zoom="15" data-points='@json([array_merge($listing->mapPoint(), ['url' => null])])'>
                            <span class="map-note">Loading map…</span>
                        </div>
                        <p class="muted" style="margin-top: 10px; font-size: 14px">{{ $listing->fullAddress() }}</p>
                    </section>
                @endif
            </div>

            <aside class="sticky-side">
                <div class="panel">
                    <div class="agent-mini">
                        <x-agent-avatar :agent="$listing->agent" size="sm" />
                        <div>
                            <p>Listed by</p>
                            <h3><a href="{{ route('agents.show', $listing->agent) }}">{{ $listing->agent->name }}</a></h3>
                            <p>{{ $listing->agent->title ?: Setting::get('business_name') }}</p>
                        </div>
                    </div>
                    @if ($listing->status->isAvailable() || $listing->status === ListingStatus::Pending)
                        <livewire:contact-agent :agent="$listing->agent" :listing="$listing" />
                    @else
                        <p class="muted" style="margin-bottom: 12px">This home has sold. Ask {{ Str::before($listing->agent->name, ' ') }} about similar homes nearby.</p>
                        <livewire:contact-agent :agent="$listing->agent" :listing="$listing" :allow-tours="false" />
                    @endif
                    @if ($phone = $listing->agent->phoneLink())
                        <div class="panel-links">
                            <a class="btn btn--outline btn--block" href="{{ $phone }}"><x-icon name="phone" /> {{ $listing->agent->phone }}</a>
                        </div>
                    @endif
                </div>
            </aside>
        </div>
    </div>

    @if ($similar->isNotEmpty())
        <section class="section section--soft">
            <div class="container">
                <div class="section-head">
                    <div>
                        <h2>Similar homes nearby</h2>
                        <p>{{ $listing->status->label() }} in {{ $listing->city->label() }}, close in price.</p>
                    </div>
                    <a class="link" href="{{ route('search', ['q' => $listing->city->name, 'status' => $listing->status->value]) }}">See more <x-icon name="arrow-right" /></a>
                </div>
                <div class="scroller">
                    @foreach ($similar as $home)
                        <x-listing-card :listing="$home" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-layouts::site>
