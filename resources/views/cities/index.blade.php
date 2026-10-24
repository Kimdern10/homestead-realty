<x-layouts::site title="Cities" description="Homes for sale and rent by city. Explore neighborhoods, prices and local agents." current="cities">
    <div class="page-head">
        <div class="container">
            <h1>Explore cities</h1>
            <p>Pick a city to see homes for sale and rent, typical prices, neighborhoods and the agents who know it best.</p>
        </div>
    </div>

    <section class="section">
        <div class="container">
            @if ($cities->isEmpty())
                <div class="empty"><h2>No cities yet</h2><p>Cities will show here once listings are added.</p></div>
            @else
                <div class="grid grid--cities">
                    @foreach ($cities as $city)
                        <div class="reveal" style="display: grid; gap: 8px">
                            <x-city-card :city="$city" :count="$city->for_sale_count + $city->for_rent_count" />
                            <div class="muted" style="font-size: 14px; display: flex; gap: 12px; flex-wrap: wrap; padding: 0 4px">
                                <a class="link" href="{{ route('search', ['q' => $city->name, 'status' => 'for_sale']) }}">{{ $city->for_sale_count }} for sale</a>
                                <a class="link" href="{{ route('search', ['q' => $city->name, 'status' => 'for_rent']) }}">{{ $city->for_rent_count }} for rent</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</x-layouts::site>
