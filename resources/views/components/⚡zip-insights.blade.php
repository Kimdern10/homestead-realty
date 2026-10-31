<?php

use App\Models\Listing;
use App\Models\Setting;
use App\Services\ListingImporter;
use App\Services\RentCast;
use App\Services\ZipLookup;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Lazy] class extends Component {
    #[Locked]
    public string $zip;

    #[Locked]
    public string $status = '';

    /** @var array{zip: string, place: string, state: string, state_code: string, lat: float, lng: float}|null */
    public ?array $place = null;

    public int $added = 0;

    public ?string $notice = null;

    /**
     * Look the ZIP up and, when allowed, bring in live homes for it.
     */
    public function mount(ZipLookup $zips, RentCast $rentCast, ListingImporter $importer): void
    {
        $this->place = $zips->find($this->zip);

        if ($this->place === null) {
            return;
        }

        $this->dispatch('map-center', lat: $this->place['lat'], lng: $this->place['lng']);

        $type = $this->status === 'for_rent' ? 'rent' : 'sale';

        if ($this->status === 'sold' || ! $rentCast->isActive() || Setting::get('auto_fetch_zip') === '0' || $rentCast->isCached($type, ['zipCode' => $this->zip])) {
            return;
        }

        try {
            $result = $importer->import(['zipCode' => $this->zip], [$type]);
            $this->added = $result['created'];

            if ($result['created'] + $result['updated'] > 0) {
                $this->dispatch('listings-imported');
            }
        } catch (RuntimeException $exception) {
            report($exception);
            $this->notice = 'Live listings are taking a break right now. You are seeing the homes we already have.';
        }
    }

    public function placeholder(): string
    {
        return <<<'HTML'
            <div class="zip-panel zip-panel--loading" aria-busy="true"><div class="skeleton skeleton--title"></div><div class="skeleton"></div></div>
            HTML;
    }
}; ?>

<div>
    @if ($place)
        <section class="zip-panel reveal is-visible" aria-label="About ZIP code {{ $zip }}">
            <div class="zip-panel__head">
                <div>
                    <span class="eyebrow">ZIP code {{ $zip }}</span>
                    <h2>{{ $place['place'] }}, {{ $place['state_code'] }}</h2>
                    <p>
                        {{ number_format(Listing::query()->published()->where('zip', $zip)->where('status', 'for_sale')->count()) }} for sale ·
                        {{ number_format(Listing::query()->published()->where('zip', $zip)->where('status', 'for_rent')->count()) }} for rent ·
                        {{ number_format(Listing::query()->published()->where('zip', $zip)->where('status', 'sold')->count()) }} sold
                        @if ($added > 0)<span class="pill-new">{{ $added }} new {{ Str::plural('home', $added) }} just added</span>@endif
                    </p>
                    @if ($notice)<p class="muted">{{ $notice }}</p>@endif
                </div>
            </div>
            <h3 class="zip-panel__sub"><x-icon name="building" /> Schools near {{ $zip }}</h3>
            <livewire:nearby-schools :lat="$place['lat']" :lng="$place['lng']" :limit="6" wire:key="schools-{{ $zip }}" />
        </section>
    @else
        <p class="muted" style="margin-bottom: 16px">We couldn't find ZIP code {{ $zip }}. Check the number and try again.</p>
    @endif
</div>
