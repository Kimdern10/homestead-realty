<x-layouts::site title="Page not found" :noindex="true">
    <section class="not-found">
        <div class="container">
            <div class="not-found__code">404</div>
            <h1>We couldn't find that page</h1>
            <p>The home may have been sold or taken off the market, or the link is wrong. Try a new search instead.</p>
            <form class="hero__search" action="{{ route('search') }}" method="get" role="search" style="margin: 28px auto 0">
                <div class="search-box" style="border: 1px solid var(--line)">
                    <x-icon name="search" />
                    <label class="sr-only" for="nf-q">City, neighborhood, address or ZIP</label>
                    <input id="nf-q" name="q" type="search" placeholder="City, neighborhood, address or ZIP">
                    <button class="btn btn--primary" type="submit"><x-icon name="search" /><span>Search</span></button>
                </div>
            </form>
            <a class="btn btn--outline" href="{{ route('home') }}">Go to the home page</a>
        </div>
    </section>
</x-layouts::site>
