@props(['rating' => 0])

@php($rating = max(0, min(5, (float) $rating)))

<span {{ $attributes->class(['stars']) }} role="img" aria-label="{{ rtrim(rtrim(number_format($rating, 1), '0'), '.') }} out of 5 stars" style="--stars: {{ $rating / 5 * 100 }}%">★★★★★</span>
