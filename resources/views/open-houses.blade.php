<x-layouts::site title="Open houses" description="Upcoming open houses near you. Walk in during the listed hours, no appointment needed.">
    <div class="page-head">
        <div class="container">
            <h1>Open houses</h1>
            <p>Walk in during the listed hours. No appointment needed.</p>
        </div>
    </div>

    <section class="section">
        <div class="container">
            @forelse ($days as $day => $openHouses)
                <div class="day-group">
                    <h2>{{ \Illuminate\Support\Carbon::parse($day)->isToday() ? 'Today' : (\Illuminate\Support\Carbon::parse($day)->isTomorrow() ? 'Tomorrow' : \Illuminate\Support\Carbon::parse($day)->format('l, F j')) }}</h2>
                    <div class="grid grid--cards">
                        @foreach ($openHouses as $openHouse)
                            @php($openHouse->listing->setRelation('nextOpenHouse', $openHouse))
                            <x-listing-card :listing="$openHouse->listing" />
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="empty">
                    <span class="empty__icon"><x-icon name="door" /></span>
                    <h2>No open houses planned right now</h2>
                    <p>Agents add them most weeks. You can still book a private tour on any home.</p>
                    <a class="btn btn--primary" href="{{ route('search', ['status' => 'for_sale']) }}">Browse homes</a>
                </div>
            @endforelse
        </div>
    </section>
</x-layouts::site>
