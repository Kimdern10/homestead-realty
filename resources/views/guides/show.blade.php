@php
    $structured = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $post->title,
        'description' => $post->summary(),
        'datePublished' => $post->published_at?->toIso8601String(),
        'dateModified' => $post->updated_at?->toIso8601String(),
        'image' => $post->coverUrl() ?: null,
        'publisher' => ['@type' => 'Organization', 'name' => \App\Models\Setting::get('business_name')],
    ]);
@endphp

<x-layouts::site :title="$post->title" :description="$post->summary()" :image="$post->coverUrl()" type="article">
    <x-slot:head>
        <script type="application/ld+json">{!! json_encode($structured, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    </x-slot:head>

    <article class="article">
        <div class="container container--narrow">
            <nav class="crumbs" aria-label="Breadcrumb" style="margin-top: 24px">
                <a href="{{ route('guides.index') }}">Guides</a>
                @if ($post->city)
                    <span aria-hidden="true">/</span><a href="{{ route('guides.index', ['city' => $post->city->slug]) }}">{{ $post->city->name }}</a>
                @endif
            </nav>
            <h1 class="article__title">{{ $post->title }}</h1>
            <p class="article__meta">{{ $post->published_at?->format('F j, Y') }} · {{ $post->readingMinutes() }} min read</p>
        </div>
        @if ($cover = $post->coverUrl())
            <div class="container container--narrow">
                <img class="article__cover" src="{{ $cover }}" alt="" width="1600" height="1067" fetchpriority="high">
            </div>
        @endif
        <div class="container container--narrow">
            <div class="prose article__body">
                {!! Str::markdown($post->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
            </div>
            @if ($post->city)
                <p style="margin-top: 28px"><a class="btn btn--outline" href="{{ route('cities.show', $post->city) }}">Homes and market numbers for {{ $post->city->name }} <x-icon name="arrow-right" /></a></p>
            @endif
        </div>
    </article>

    @if ($homes->isNotEmpty())
        <section class="section section--soft">
            <div class="container">
                <div class="section-head">
                    <div><h2>Homes in {{ $post->city?->name }}</h2></div>
                    <a class="link" href="{{ route('search', ['q' => $post->city?->name]) }}">See all <x-icon name="arrow-right" /></a>
                </div>
                <div class="scroller">
                    @foreach ($homes as $home)
                        <x-listing-card :listing="$home" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($more->isNotEmpty())
        <section class="section">
            <div class="container">
                <div class="section-head"><div><h2>More guides</h2></div></div>
                <div class="grid grid--guides">
                    @foreach ($more as $other)
                        <x-guide-card :post="$other" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-layouts::site>
