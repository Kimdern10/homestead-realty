@php
    $rows = [
        'Price' => fn ($home) => $home->priceLabel(),
        'Status' => fn ($home) => $home->status->label(),
        'Bedrooms' => fn ($home) => $home->beds === null ? '—' : ($home->beds === 0 ? 'Studio' : $home->beds),
        'Bathrooms' => fn ($home) => $home->baths === null ? '—' : $home->bathsLabel(),
        'Size' => fn ($home) => $home->sqft ? number_format($home->sqft).' sqft' : '—',
        'Price per sqft' => fn ($home) => $home->pricePerSqft() ? '$'.number_format($home->pricePerSqft()) : '—',
        'Home type' => fn ($home) => $home->typeLabel(),
        'Year built' => fn ($home) => $home->year_built ?: '—',
        'Lot size' => fn ($home) => $home->lot_acres ? rtrim(rtrim($home->lot_acres, '0'), '.').' acres' : '—',
        'Garage' => fn ($home) => $home->garage_spaces ? $home->garage_spaces.' '.Str::plural('space', $home->garage_spaces) : '—',
        'HOA fee' => fn ($home) => $home->hoa_fee ? '$'.number_format($home->hoa_fee).'/mo' : '—',
        'Property tax' => fn ($home) => $home->property_tax ? '$'.number_format($home->property_tax).'/yr' : '—',
        'Neighborhood' => fn ($home) => $home->neighborhood ?: '—',
        'Days on site' => fn ($home) => $home->daysOnSite(),
        'Agent' => fn ($home) => $home->agent->name,
    ];

    // Highlight the best value in rows where lower or higher is clearly better.
    $best = [];
    if ($homes->count() > 1) {
        $best['Price'] = $homes->min('price');
        $best['Bedrooms'] = $homes->max('beds');
        $best['Bathrooms'] = $homes->max('baths');
        $best['Size'] = $homes->max('sqft');
        $best['Price per sqft'] = $homes->map(fn ($home) => $home->pricePerSqft())->filter()->min();
    }
    $isBest = fn (string $label, $home) => match ($label) {
        'Price' => isset($best['Price']) && $home->price === $best['Price'],
        'Bedrooms' => isset($best['Bedrooms']) && $home->beds !== null && $home->beds === $best['Bedrooms'],
        'Bathrooms' => isset($best['Bathrooms']) && $home->baths !== null && (float) $home->baths === (float) $best['Bathrooms'],
        'Size' => isset($best['Size']) && $home->sqft && $home->sqft === $best['Size'],
        'Price per sqft' => isset($best['Price per sqft']) && $home->pricePerSqft() === $best['Price per sqft'],
        default => false,
    };
    $amenities = $homes->flatMap(fn ($home) => $home->amenities ?? [])->unique()->sort()->values();
@endphp

<x-layouts::site title="Compare homes" description="Compare homes side by side: price, size, rooms and features." :noindex="true">
    <section class="page-head">
        <div class="container">
            <h1>Compare homes</h1>
            <p class="muted">Up to {{ \App\Http\Controllers\ListingController::COMPARE_LIMIT }} homes side by side. Tap <x-icon name="compare" style="width: 16px; height: 16px; vertical-align: -3px" /> on any home to add it.</p>
        </div>
    </section>

    <div class="container" style="padding-bottom: 64px">
        @if ($homes->isEmpty())
            <div class="empty">
                <span class="empty__icon"><x-icon name="compare" /></span>
                <h2>Nothing to compare yet</h2>
                <p>Tap the compare button on two or more homes, then come back here.</p>
                <a class="btn btn--primary" href="{{ route('search', ['status' => 'for_sale']) }}">Browse homes</a>
            </div>
        @else
            <div class="compare-wrap">
                <table class="compare">
                    <thead>
                        <tr>
                            <th scope="col"><span class="sr-only">Detail</span></th>
                            @foreach ($homes as $home)
                                <th scope="col">
                                    <a class="compare__home" href="{{ route('listings.show', $home) }}">
                                        <img src="{{ $home->coverUrl(small: true) ?? $home->placeholderUrl() }}" alt="" width="360" height="240" loading="lazy">
                                        <span>{{ $home->address }}</span>
                                        <small class="muted">{{ $home->locality() }}</small>
                                    </a>
                                    <button type="button" class="link compare__remove" data-compare-remove="{{ $home->id }}">Remove</button>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $label => $value)
                            <tr>
                                <th scope="row">{{ $label }}</th>
                                @foreach ($homes as $home)
                                    <td @class(['is-best' => $isBest($label, $home)])>{{ $value($home) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                        @foreach ($amenities as $amenity)
                            <tr>
                                <th scope="row">{{ $amenity }}</th>
                                @foreach ($homes as $home)
                                    <td>{!! in_array($amenity, $home->amenities ?? [], true) ? '<span class="yes" aria-label="Yes">✓</span>' : '<span class="muted" aria-label="No">—</span>' !!}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="muted" style="margin-top: 12px; font-size: 14px">Green marks the best value in a row.</p>
        @endif
    </div>
</x-layouts::site>
