<x-layouts::site title="Find an agent" description="Meet our licensed real estate agents. See their listings, recent sales and get in touch." current="agents">
    <div class="page-head">
        <div class="container">
            <h1>Find an agent</h1>
            <p>Local, licensed agents who'll help you buy, sell or rent. Open a profile to see their homes and send a message.</p>
        </div>
    </div>

    <section class="section">
        <div class="container">
            @if ($agents->isEmpty())
                <div class="empty"><h2>No agents yet</h2></div>
            @else
                <div class="grid grid--agents">
                    @foreach ($agents as $agent)
                        <a class="agent-card reveal" href="{{ route('agents.show', $agent) }}" style="flex-direction: column; text-align: center; padding: 24px">
                            <x-agent-avatar :agent="$agent" size="lg" />
                            <div>
                                <h3 style="font-size: 19px">{{ $agent->name }}</h3>
                                <p>{{ $agent->title ?: 'Real estate agent' }}</p>
                                <p style="margin-top: 8px; color: var(--ink)"><b>{{ $agent->active_count }}</b> active · <b>{{ $agent->sold_count }}</b> sold</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</x-layouts::site>
