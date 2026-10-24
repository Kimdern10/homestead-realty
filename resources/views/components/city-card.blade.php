@props(['city', 'count' => null])

<a {{ $attributes->class(['city-card']) }} href="{{ route('cities.show', $city) }}">
    @if ($image = $city->imageUrl(small: true))
        <img src="{{ $image }}" alt="" loading="lazy" decoding="async" width="720" height="540">
    @endif
    <div class="city-card__body">
        <h3>{{ $city->name }}</h3>
        <p>{{ $city->state }}@if ($count !== null) · {{ number_format($count) }} {{ Str::plural('home', $count) }}@endif</p>
    </div>
</a>
