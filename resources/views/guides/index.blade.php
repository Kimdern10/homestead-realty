<x-layouts::site :title="$city ? $city->name.' neighborhood guides' : 'Guides'" :description="$city ? 'Guides to living in '.$city->label().': neighborhoods, schools, prices and tips.' : 'Neighborhood guides and tips for buying, selling and renting a home.'">
    <div class="page-head">
        <div class="container">
            <h1>{{ $city ? $city->name.' guides' : 'Guides and tips' }}</h1>
            <p>Neighborhood guides, market notes and plain-English advice for buying, selling and renting.</p>
            @if ($cities->isNotEmpty())
                <div class="listing-meta">
                    <a class="chip @if (! $city) chip--strong @endif" href="{{ route('guides.index') }}">All</a>
                    @foreach ($cities as $option)
                        <a class="chip @if ($city?->is($option)) chip--strong @endif" href="{{ route('guides.index', ['city' => $option->slug]) }}">{{ $option->name }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <section class="section">
        <div class="container">
            @if ($posts->isEmpty())
                <div class="empty">
                    <span class="empty__icon"><x-icon name="book" /></span>
                    <h2>No guides yet</h2>
                    <p>Check back soon.</p>
                </div>
            @else
                <div class="grid grid--guides">
                    @foreach ($posts as $post)
                        <x-guide-card :post="$post" />
                    @endforeach
                </div>
                {{ $posts->onEachSide(1)->links('partials.pager') }}
            @endif
        </div>
    </section>
</x-layouts::site>
