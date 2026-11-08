@props(['post'])

<article class="guide-card">
    <div class="guide-card__media">
        @if ($cover = $post->coverUrl(small: true))
            <img src="{{ $cover }}" alt="" width="720" height="480" loading="lazy" decoding="async">
        @else
            <span class="guide-card__blank" aria-hidden="true"><x-icon name="book" /></span>
        @endif
    </div>
    <div class="guide-card__body">
        <p class="guide-card__meta">{{ $post->city?->name ?? 'Guide' }} · {{ $post->readingMinutes() }} min read</p>
        <h3><a href="{{ route('guides.show', $post) }}">{{ $post->title }}</a></h3>
        <p>{{ $post->summary() }}</p>
    </div>
</article>
