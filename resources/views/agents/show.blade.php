@php
    $site = \App\Models\Setting::values();
@endphp

<x-layouts::site :title="$agent->name.', '.($agent->title ?: 'Real estate agent')" :description="Str::limit($agent->bio ?: $agent->name.' is a real estate agent with '.$site['business_name'].'.', 155)" :image="$agent->photoUrl()" current="agents" type="profile">
    <x-slot:head>
        <script type="application/ld+json">{!! json_encode(array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'RealEstateAgent',
            'name' => $agent->name,
            'url' => route('agents.show', $agent),
            'image' => $agent->photoUrl(),
            'telephone' => $agent->phone,
            'email' => $agent->email,
            'description' => $agent->bio,
            'worksFor' => ['@type' => 'RealEstateAgent', 'name' => $site['business_name'], 'url' => route('home')],
            'areaServed' => $cities->map(fn ($city) => ['@type' => 'City', 'name' => $city->label()])->values()->all() ?: null,
        ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    </x-slot:head>

    <div class="page-head">
        <div class="container">
            <nav class="crumbs" aria-label="Breadcrumb"><a href="{{ route('agents.index') }}">Agents</a><span aria-hidden="true">/</span><span>{{ $agent->name }}</span></nav>
            <div class="profile">
                <x-agent-avatar :agent="$agent" size="lg" />
                <div>
                    <h1>{{ $agent->name }}</h1>
                    <p class="profile__role">{{ $agent->title ?: 'Real estate agent' }} · {{ $site['business_name'] }}</p>
                    <div class="listing-meta">
                        @if ($agent->years_experience)<span class="chip"><x-icon name="clock" /> {{ $agent->years_experience }} years' experience</span>@endif
                        @if ($agent->languages)<span class="chip"><x-icon name="user" /> Speaks {{ $agent->languages }}</span>@endif
                        @if ($agent->license_number)<span class="chip"><x-icon name="shield" /> License {{ $agent->license_number }}</span>@endif
                        @foreach ($cities as $city)
                            <a class="chip" href="{{ route('cities.show', $city) }}"><x-icon name="pin" /> {{ $city->label() }}</a>
                        @endforeach
                    </div>
                    <div class="profile__contact">
                        @if ($phone = $agent->phoneLink())<a class="btn btn--primary btn--sm" href="{{ $phone }}"><x-icon name="phone" /> {{ $agent->phone }}</a>@endif
                        <a class="btn btn--outline btn--sm" href="#contact"><x-icon name="mail" /> Send a message</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="container listing-layout" style="padding: 0">
            <div>
                @if ($agent->bio)
                    <div class="block" style="padding-top: 0">
                        <h2>About {{ Str::before($agent->name, ' ') }}</h2>
                        <div class="prose">
                            @foreach (preg_split('/\R\s*\R/', trim($agent->bio)) as $paragraph)
                                <p>{{ $paragraph }}</p>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="block">
                    <h2>Active listings ({{ $listings->count() }})</h2>
                    @if ($listings->isEmpty())
                        <p class="muted">No active listings right now.</p>
                    @else
                        <div class="grid grid--cards">
                            @foreach ($listings as $listing)
                                <x-listing-card :listing="$listing" />
                            @endforeach
                        </div>
                    @endif
                </div>

                @if ($sold->isNotEmpty())
                    <div class="block">
                        <h2>Recently sold</h2>
                        <div class="grid grid--cards">
                            @foreach ($sold as $listing)
                                <x-listing-card :listing="$listing" />
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <aside class="sticky-side" id="contact">
                <div class="panel">
                    <h2 style="font-size: 19px; margin-bottom: 12px">Contact {{ Str::before($agent->name, ' ') }}</h2>
                    <livewire:contact-agent :agent="$agent" :allow-tours="false" />
                </div>
            </aside>
        </div>
    </section>
</x-layouts::site>
