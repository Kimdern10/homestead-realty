<?php

use App\Enums\ListingStatus;
use App\Enums\PropertyType;
use App\Models\City;
use App\Models\Listing;
use App\Models\SavedSearch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    /**
     * The most homes drawn on the map at once.
     */
    public const int MAP_LIMIT = 500;

    /**
     * Price choices for homes for sale.
     *
     * @var list<int>
     */
    public const array SALE_PRICES = [100000, 200000, 300000, 400000, 500000, 600000, 750000, 1000000, 1500000, 2000000, 3000000, 5000000];

    /**
     * Price choices for rentals (per month).
     *
     * @var list<int>
     */
    public const array RENT_PRICES = [1000, 1500, 2000, 2500, 3000, 3500, 4000, 5000, 6000, 8000, 10000];

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: '')]
    public string $status = '';

    /** @var list<string> */
    #[Url(except: [])]
    public array $types = [];

    #[Url(except: 0)]
    public int $min_price = 0;

    #[Url(except: 0)]
    public int $max_price = 0;

    #[Url(except: 0)]
    public int $beds = 0;

    #[Url(except: 0)]
    public float $baths = 0;

    #[Url(except: 'newest')]
    public string $sort = 'newest';

    #[Url(except: 'list')]
    public string $view = 'list';

    public bool $searchSaved = false;

    /**
     * Ignore anything in the address bar we don't understand.
     */
    public function mount(): void
    {
        $this->sanitize();
    }

    /**
     * Go back to page one and redraw the map whenever a filter changes.
     */
    public function updated(string $property): void
    {
        $this->sanitize();

        if (in_array(explode('.', $property)[0], ['q', 'status', 'types', 'min_price', 'max_price', 'beds', 'baths'], true)) {
            $this->resetPage();
            $this->searchSaved = false;
            $this->dispatch('map-points', points: $this->mapPoints());
        }

        if ($property === 'sort') {
            $this->resetPage();
        }
    }

    /**
     * Show homes the ZIP panel just brought in from the live feed.
     */
    #[\Livewire\Attributes\On('listings-imported')]
    public function refreshAfterImport(): void
    {
        unset($this->listings);
        $this->dispatch('map-points', points: $this->mapPoints());
    }

    /**
     * Switch between list and map view.
     */
    public function setView(string $view): void
    {
        $this->view = $view === 'map' ? 'map' : 'list';
    }

    /**
     * Clear every filter except the search words.
     */
    public function clearFilters(): void
    {
        $this->reset('types', 'min_price', 'max_price', 'beds', 'baths');
        $this->resetPage();
        $this->dispatch('map-points', points: $this->mapPoints());
    }

    /**
     * Keep the current filters in the visitor's account.
     */
    public function saveSearch(): void
    {
        $user = auth()->user();

        if ($user === null) {
            $this->redirectRoute('login');

            return;
        }

        if (! $user->hasVerifiedEmail()) {
            $this->redirectRoute('verification.notice');

            return;
        }

        $filters = $this->filters();

        $alreadySaved = $user->savedSearches()->get()->contains(fn (SavedSearch $saved): bool => $saved->filters == $filters);

        if (! $alreadySaved) {
            $user->savedSearches()->create([
                'name' => Str::limit($this->heading, 120),
                'filters' => $filters,
            ]);
        }

        $this->searchSaved = true;
    }

    /**
     * The chosen filters, without empty values.
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return array_filter([
            'q' => trim($this->q),
            'status' => $this->status,
            'types' => array_values($this->types),
            'min_price' => $this->min_price,
            'max_price' => $this->max_price,
            'beds' => $this->beds,
            'baths' => $this->baths,
        ], fn ($value) => filled($value) && $value !== 0 && $value !== 0.0);
    }

    /**
     * @return Builder<Listing>
     */
    private function query(): Builder
    {
        return Listing::query()->published()->filter($this->filters());
    }

    /**
     * The homes on this page of results.
     *
     * @return LengthAwarePaginator<int, Listing>
     */
    #[Computed]
    public function listings(): LengthAwarePaginator
    {
        return $this->query()->forCards()->sortBy($this->sort)->paginate(Listing::PER_PAGE);
    }

    /**
     * Every matching home with a location, for the map.
     *
     * @return list<array<string, mixed>>
     */
    public function mapPoints(): array
    {
        return $this->query()
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->with('coverPhoto')
            ->sortBy($this->sort)
            ->take(self::MAP_LIMIT)
            ->get()
            ->map(fn (Listing $listing): array => $listing->mapPoint())
            ->all();
    }

    /**
     * Price choices for the chosen status.
     *
     * @return list<int>
     */
    #[Computed]
    public function priceOptions(): array
    {
        return $this->status === ListingStatus::ForRent->value ? self::RENT_PRICES : self::SALE_PRICES;
    }

    /**
     * Cities to suggest when nothing matches.
     *
     * @return \Illuminate\Support\Collection<int, City>
     */
    #[Computed]
    public function cities()
    {
        return City::query()->ordered()->take(8)->get();
    }

    /**
     * The page heading, e.g. "Homes for sale in Austin".
     */
    #[Computed]
    public function heading(): string
    {
        $what = match ($this->status) {
            'for_sale' => 'Homes for sale',
            'for_rent' => 'Homes for rent',
            'sold' => 'Recently sold homes',
            'pending' => 'Pending homes',
            default => 'Homes',
        };

        if (count($this->types) === 1 && ($type = PropertyType::tryFrom($this->types[0]))) {
            $what = str_replace('Homes', $type->label().'s', $what);
            $what = str_replace('Single-family homes', 'Houses', $what);
        }

        return trim($this->q) !== '' ? $what.' in '.trim($this->q) : $what;
    }

    /**
     * Number of filters chosen, shown on the phone "Filters" button.
     */
    #[Computed]
    public function activeFilterCount(): int
    {
        return count(array_filter([$this->types, $this->min_price, $this->max_price, $this->beds, $this->baths]));
    }

    public function render()
    {
        $current = match ($this->status) {
            'for_sale' => 'buy',
            'for_rent' => 'rent',
            'sold' => 'sold',
            default => null,
        };

        return $this->view()
            ->layout('layouts::site', [
                'current' => $current,
                'description' => $this->heading.'. Browse photos, prices and maps, and contact a local agent.',
                'canonical' => route('search', array_filter(['status' => $this->status, 'q' => trim($this->q)])),
                'noindex' => $this->activeFilterCount > 0 || $this->sort !== 'newest' || $this->getPage() > 1,
            ])
            ->title($this->heading);
    }

    /**
     * Drop values that are not real options.
     */
    private function sanitize(): void
    {
        $this->q = Str::limit(trim($this->q), 100, '');
        $this->status = ListingStatus::tryFrom($this->status)?->value ?? '';
        $this->types = array_values(array_filter($this->types, fn ($type) => is_string($type) && PropertyType::tryFrom($type) !== null));
        $this->min_price = max(0, $this->min_price);
        $this->max_price = max(0, $this->max_price);
        $this->beds = min(6, max(0, $this->beds));
        $this->baths = min(5, max(0, $this->baths));
        $this->sort = array_key_exists($this->sort, Listing::SORTS) ? $this->sort : 'newest';
        $this->view = $this->view === 'map' ? 'map' : 'list';

        if ($this->min_price > 0 && $this->max_price > 0 && $this->min_price > $this->max_price) {
            [$this->min_price, $this->max_price] = [$this->max_price, $this->min_price];
        }
    }
}; ?>

<div>
    <div class="search-bar" wire:key="search-bar" x-data="{ open: false }" :class="{ 'is-open': open }">
        <div class="container">
            <form class="search-bar__row" wire:submit="$refresh" role="search">
                <div class="search-q">
                    <x-icon name="search" />
                    <label class="sr-only" for="search-q">City, neighborhood, address or ZIP</label>
                    <input class="input" id="search-q" type="search" wire:model.live.debounce.400ms="q" placeholder="City, neighborhood, address or ZIP" autocomplete="off">
                </div>
                <label class="sr-only" for="search-status">Status</label>
                <select class="select" id="search-status" wire:model.live="status">
                    <option value="">Any status</option>
                    @foreach (\App\Enums\ListingStatus::options() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <button class="btn btn--outline btn--sm filters-toggle" type="button" x-on:click="open = ! open" :aria-expanded="open" style="min-height: 44px">
                    <x-icon name="filter" /> Filters @if ($this->activeFilterCount) ({{ $this->activeFilterCount }}) @endif
                </button>
                <div class="filters-more">
                    <label class="sr-only" for="search-min">Minimum price</label>
                    <select class="select" id="search-min" wire:model.live="min_price">
                        <option value="0">Min price</option>
                        @foreach ($this->priceOptions as $price)
                            <option value="{{ $price }}">${{ number_format($price) }}</option>
                        @endforeach
                    </select>
                    <label class="sr-only" for="search-max">Maximum price</label>
                    <select class="select" id="search-max" wire:model.live="max_price">
                        <option value="0">Max price</option>
                        @foreach ($this->priceOptions as $price)
                            <option value="{{ $price }}">${{ number_format($price) }}</option>
                        @endforeach
                    </select>
                    <label class="sr-only" for="search-beds">Bedrooms</label>
                    <select class="select" id="search-beds" wire:model.live="beds">
                        <option value="0">Beds</option>
                        @foreach ([1, 2, 3, 4, 5] as $count)
                            <option value="{{ $count }}">{{ $count }}+ beds</option>
                        @endforeach
                    </select>
                    <label class="sr-only" for="search-baths">Bathrooms</label>
                    <select class="select" id="search-baths" wire:model.live="baths">
                        <option value="0">Baths</option>
                        @foreach ([1, 1.5, 2, 3, 4] as $count)
                            <option value="{{ $count }}">{{ $count }}+ baths</option>
                        @endforeach
                    </select>
                    <div class="type-picker" x-data="{ types: false }" x-on:click.outside="types = false" x-on:keydown.escape="types = false">
                        <button type="button" class="select" style="display: flex; align-items: center; border-radius: 999px; text-align: left" x-on:click="types = ! types" :aria-expanded="types" aria-controls="type-panel">
                            {{ $types ? count($types).' home '.Str::plural('type', count($types)) : 'Home type' }}
                        </button>
                        <div class="type-picker__panel" id="type-panel" x-show="types" x-cloak>
                            @foreach (\App\Enums\PropertyType::options() as $value => $label)
                                <label class="check" wire:key="type-{{ $value }}"><input type="checkbox" value="{{ $value }}" wire:model.live="types"> {{ $label }}</label>
                            @endforeach
                        </div>
                    </div>
                    @if ($this->activeFilterCount)
                        <button class="btn btn--ghost btn--sm" type="button" wire:click="clearFilters" style="min-height: 44px">Clear filters</button>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="container">
        @if (\App\Services\ZipLookup::isZip($q))
            <livewire:zip-insights :zip="trim($q)" :status="$status" wire:key="zip-{{ trim($q) }}-{{ $status }}" />
        @endif

        <div class="results-head">
            <div>
                <h1>{{ $this->heading }}</h1>
                <p>{{ number_format($this->listings->total()) }} {{ Str::plural('home', $this->listings->total()) }} found</p>
            </div>
            <div class="results-tools">
                <label class="sr-only" for="search-sort">Sort by</label>
                <select class="select" id="search-sort" wire:model.live="sort" style="width: auto; border-radius: 999px; min-height: 40px">
                    @foreach (\App\Models\Listing::SORTS as $value => $label)
                        <option value="{{ $value }}">Sort: {{ $label }}</option>
                    @endforeach
                </select>
                <div class="view-switch" role="group" aria-label="Show results as">
                    <button type="button" wire:click="setView('list')" aria-pressed="{{ $view === 'list' ? 'true' : 'false' }}"><x-icon name="list" /> List</button>
                    <button type="button" wire:click="setView('map')" aria-pressed="{{ $view === 'map' ? 'true' : 'false' }}"><x-icon name="map" /> Map</button>
                </div>
                @if ($searchSaved)
                    <span class="btn btn--sm btn--ghost" role="status"><x-icon name="check" /> Search saved</span>
                @else
                    <button class="btn btn--sm btn--dark" type="button" wire:click="saveSearch"><x-icon name="bell" /> Save search</button>
                @endif
            </div>
        </div>

        <div @class(['results-layout', 'is-map' => $view === 'map'])>
            <div class="results-map" wire:ignore>
                <div class="map" data-map data-listen="map-points" data-scroll-zoom="true" data-points='@json($this->mapPoints())'>
                    <span class="map-note">Loading map…</span>
                </div>
            </div>

            <div wire:loading.class="results-loading" wire:target="q, status, types, min_price, max_price, beds, baths, sort, gotoPage, nextPage, previousPage, clearFilters">
                @if ($this->listings->isEmpty())
                    <div class="empty">
                        <h2>No homes match your search</h2>
                        <p>Try a different place, widen your price range or remove a filter.</p>
                        @if ($this->activeFilterCount)
                            <button class="btn btn--primary" type="button" wire:click="clearFilters">Clear filters</button>
                        @endif
                        @if ($this->cities->isNotEmpty())
                            <div class="hero__chips" style="justify-content: center; margin-top: 20px">
                                @foreach ($this->cities as $city)
                                    <a class="chip" href="{{ route('cities.show', $city) }}" wire:key="suggest-{{ $city->id }}">{{ $city->label() }}</a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @else
                    <div class="grid grid--cards">
                        @foreach ($this->listings as $listing)
                            <x-listing-card :listing="$listing" :lazy="$loop->index > 2" wire:key="listing-{{ $listing->id }}" />
                        @endforeach
                    </div>

                    {{ $this->listings->onEachSide(1)->links('partials.pager') }}
                @endif
            </div>
        </div>
    </div>
</div>
