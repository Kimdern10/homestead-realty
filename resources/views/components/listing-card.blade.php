@props(['listing', 'lazy' => true])

@php
    $openHouse = $listing->relationLoaded('nextOpenHouse') ? $listing->nextOpenHouse : null;
    $priceCut = $listing->status->isAvailable() ? $listing->priceCut() : null;
@endphp

<article {{ $attributes->class(['card']) }} data-listing="{{ $listing->id }}">
    <div class="card__media">
        <img src="{{ $listing->coverUrl() ?? $listing->placeholderUrl() }}" alt="{{ $listing->address }}" width="720" height="480" @if ($lazy) loading="lazy" decoding="async" @endif>
        <span class="card__shine" aria-hidden="true"></span>
        <div class="card__badges">
            <span class="badge badge--{{ $listing->status->tone() }}">{{ $listing->status->label() }}</span>
            @if ($priceCut)
                <span class="badge badge--drop" data-test="price-drop">↓ ${{ number_format($priceCut) }}</span>
            @elseif ($listing->isNew())
                <span class="badge badge--new">New</span>
            @elseif ($listing->isFeaturedNow() && $listing->status->isAvailable())
                <span class="badge badge--featured">Featured</span>
            @endif
        </div>
        @if ($openHouse)
            <span class="card__open" data-test="open-house-badge"><x-icon name="door" /> {{ $openHouse->shortLabel() }}</span>
        @endif
        <button type="button" class="card__compare" data-compare="{{ $listing->id }}" aria-pressed="false" title="Compare this home with others"><x-icon name="compare" /><span data-compare-text>Compare</span></button>
    </div>
    <div class="card__fav"><x-favorite-button :listing="$listing" /></div>
    <div class="card__body">
        <div class="card__price">{{ $listing->priceLabel() }}</div>
        @if ($facts = $listing->facts())
            <div class="card__facts">
                @foreach ($facts as $fact)
                    @php [$number, $unit] = array_pad(explode(' ', $fact, 2), 2, ''); @endphp
                    <span><b>{{ $number }}</b> {{ $unit }}</span>
                @endforeach
            </div>
        @endif
        <div class="card__address" title="{{ $listing->fullAddress() }}">{{ $listing->address }}, {{ $listing->locality() }}</div>
    </div>
    <a class="card__link" wire:navigate.hover href="{{ route('listings.show', $listing) }}" aria-label="{{ $listing->priceLabel() }}, {{ $listing->fullAddress() }}"></a>
</article>
