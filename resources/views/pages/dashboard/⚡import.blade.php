<?php

use App\Models\Agent;
use App\Models\Listing;
use App\Models\Setting;
use App\Services\ListingImporter;
use App\Services\RentCast;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Live homes')] class extends Component {
    public string $by = 'zip';

    public string $zip = '';

    public string $city = '';

    public string $state = 'TX';

    /** @var list<string> */
    public array $types = ['sale', 'rent'];

    public string $default_agent_id = '';

    public bool $auto_fetch = true;

    /** @var array{created: int, updated: int, kept: int, skipped: int}|null */
    public ?array $result = null;

    /**
     * Load the feed settings.
     */
    public function mount(): void
    {
        $this->default_agent_id = Setting::get('default_agent_id');
        $this->auto_fetch = Setting::get('auto_fetch_zip') !== '0';
    }

    #[Computed]
    public function rentCast(): RentCast
    {
        return app(RentCast::class);
    }

    /**
     * @return Collection<int, Agent>
     */
    #[Computed]
    public function agents(): Collection
    {
        return Agent::query()->active()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * The latest homes from the feed.
     *
     * @return Collection<int, Listing>
     */
    #[Computed]
    public function recent(): Collection
    {
        return Listing::query()->where('source', ListingImporter::SOURCE)->with(['city', 'agent'])->latest('synced_at')->take(12)->get();
    }

    /**
     * Fetch homes from RentCast and save them.
     */
    public function import(ListingImporter $importer): void
    {
        $this->validate([
            'by' => ['required', Rule::in(['zip', 'city'])],
            'zip' => [Rule::requiredIf($this->by === 'zip'), 'nullable', 'regex:/^\d{5}$/'],
            'city' => [Rule::requiredIf($this->by === 'city'), 'nullable', 'string', 'max:80'],
            'state' => [Rule::requiredIf($this->by === 'city'), Rule::in(array_keys(ListingImporter::STATES))],
            'types' => ['required', 'array', 'min:1'],
            'types.*' => [Rule::in(['sale', 'rent'])],
        ], ['zip.regex' => 'Enter a 5-digit ZIP code, e.g. 78704.', 'types.required' => 'Choose for sale, for rent or both.']);

        $where = $this->by === 'zip' ? ['zipCode' => $this->zip] : ['city' => trim($this->city), 'state' => $this->state];

        try {
            $this->result = $importer->import($where, array_values($this->types));
        } catch (RuntimeException $exception) {
            $this->result = null;
            $this->addError('import', $exception->getMessage());

            return;
        }

        unset($this->recent, $this->rentCast);
        Flux::toast(__(':count homes added and :updated updated.', ['count' => $this->result['created'], 'updated' => $this->result['updated']]), variant: 'success');
    }

    /**
     * Turn live homes from RentCast on or off. While off, nothing is fetched or charged.
     */
    public function toggleFeed(): void
    {
        $enabled = ! $this->rentCast->isEnabled();
        $this->rentCast->setEnabled($enabled);
        unset($this->rentCast);

        Flux::toast(
            $enabled ? __('Live homes switched on.') : __('Live homes switched off. Add houses yourself from Listings.'),
            variant: 'success',
        );

        // Reload the page so the On/Off badge in the sidebar updates too.
        $this->redirectRoute('dashboard.import', navigate: true);
    }

    /**
     * Save who gets new feed homes and whether visitor ZIP searches fetch live homes.
     */
    public function saveSettings(): void
    {
        $this->validate(['default_agent_id' => ['nullable', Rule::exists('agents', 'id')]]);

        Setting::put(['default_agent_id' => $this->default_agent_id, 'auto_fetch_zip' => $this->auto_fetch ? '1' : '0']);
        Flux::toast(__('Feed settings saved.'), variant: 'success');
    }
}; ?>

<div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Live homes (RentCast)') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Optional: bring in real homes for sale and rent anywhere in the USA from RentCast, a paid service. Imported homes become normal listings that you can edit, add photos to and mark as sold or rented.') }}</flux:text>
    </div>

    <div @class([
        'flex flex-wrap items-center justify-between gap-4 rounded-xl border p-5',
        'border-green-300 bg-green-50 dark:border-green-800 dark:bg-green-950/40' => $this->rentCast->isEnabled(),
        'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900' => ! $this->rentCast->isEnabled(),
    ]) data-test="feed-status">
        <div class="flex items-start gap-3">
            <span @class(['mt-1.5 size-3 shrink-0 rounded-full', 'bg-green-500' => $this->rentCast->isEnabled(), 'bg-zinc-400' => ! $this->rentCast->isEnabled()])></span>
            <div>
                <div class="font-semibold">{{ $this->rentCast->isEnabled() ? __('Live homes are ON') : __('Live homes are OFF') }}</div>
                <flux:text class="text-sm">
                    {{ $this->rentCast->isEnabled()
                        ? __('Homes can be imported from RentCast, and visitor ZIP searches can fetch new homes.')
                        : __('Nothing is fetched from RentCast and nothing is charged. You and your agents add houses yourselves from Listings › Add a listing.') }}
                </flux:text>
            </div>
        </div>
        <flux:button wire:click="toggleFeed" :variant="$this->rentCast->isEnabled() ? 'filled' : 'primary'" :icon="$this->rentCast->isEnabled() ? 'pause' : 'play'" data-test="toggle-feed">
            {{ $this->rentCast->isEnabled() ? __('Switch off') : __('Switch on') }}
        </flux:button>
    </div>

    @unless ($this->rentCast->isEnabled())
        <flux:button variant="primary" icon="plus" :href="route('dashboard.listings.create')" wire:navigate class="self-start">{{ __('Add a house yourself') }}</flux:button>
    @endunless

    @if ($this->rentCast->isEnabled() && ! $this->rentCast->isConfigured())
        <flux:callout icon="key" color="amber">
            <flux:callout.heading>{{ __('Add your RentCast API key') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Sign up free at rentcast.io, copy your API key, then add this line to the .env file and save:') }} <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-800">RENTCAST_API_KEY=your-key</code></flux:callout.text>
        </flux:callout>
    @endif

    @if ($this->rentCast->isEnabled())
    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text class="text-sm">{{ __('Requests used this month') }}</flux:text>
            <div class="mt-1 text-2xl font-bold">{{ $this->rentCast->requestsThisMonth() }} <span class="text-base font-normal text-zinc-500">/ {{ $this->rentCast->monthlyLimit() }}</span></div>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800"><div class="h-full rounded-full bg-[#d92228]" style="width: {{ min(100, $this->rentCast->requestsThisMonth() / max(1, $this->rentCast->monthlyLimit()) * 100) }}%"></div></div>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text class="text-sm">{{ __('Homes from the feed') }}</flux:text>
            <div class="mt-1 text-2xl font-bold">{{ number_format(\App\Models\Listing::query()->where('source', \App\Services\ListingImporter::SOURCE)->count()) }}</div>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text class="text-sm">{{ __('Each search uses') }}</flux:text>
            <div class="mt-1 text-2xl font-bold">{{ __('1 request') }}</div>
            <flux:text class="text-xs">{{ __('per type, for up to :n homes. Repeat searches within :h hours are free.', ['n' => config('services.rentcast.limit'), 'h' => config('services.rentcast.cache_hours')]) }}</flux:text>
        </div>
    </div>

    <form wire:submit="import" class="grid gap-5 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:radio.group wire:model.live="by" :label="__('Search by')" variant="segmented">
            <flux:radio value="zip" :label="__('ZIP code')" />
            <flux:radio value="city" :label="__('City')" />
        </flux:radio.group>

        @if ($by === 'zip')
            <flux:input wire:model="zip" inputmode="numeric" maxlength="5" :label="__('ZIP code')" placeholder="78704" class="max-w-48" />
        @else
            <div class="grid gap-5 sm:grid-cols-3">
                <flux:input wire:model="city" :label="__('City')" placeholder="Austin" class="sm:col-span-2" />
                <flux:select wire:model="state" :label="__('State')">
                    @foreach (\App\Services\ListingImporter::STATES as $code => $name)
                        <flux:select.option :value="$code">{{ $name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif

        <flux:checkbox.group wire:model="types" :label="__('Homes to bring in')">
            <div class="flex gap-6">
                <flux:checkbox value="sale" :label="__('For sale')" />
                <flux:checkbox value="rent" :label="__('For rent')" />
            </div>
        </flux:checkbox.group>

        @error('import')
            <flux:callout icon="exclamation-triangle" color="red">{{ $message }}</flux:callout>
        @enderror

        @if ($result)
            <flux:callout icon="check-circle" color="green">
                {{ __('Added :c new homes, updated :u, kept :k you had edited, skipped :s with missing details.', ['c' => $result['created'], 'u' => $result['updated'], 'k' => $result['kept'], 's' => $result['skipped']]) }}
            </flux:callout>
        @endif

        <div>
            <flux:button type="submit" variant="primary" icon="arrow-down-tray" :disabled="! $this->rentCast->isActive()">
                <span wire:loading.remove wire:target="import">{{ __('Fetch homes') }}</span>
                <span wire:loading wire:target="import">{{ __('Fetching from RentCast…') }}</span>
            </flux:button>
        </div>
    </form>

    <form wire:submit="saveSettings" class="grid gap-5 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading size="lg">{{ __('Feed settings') }}</flux:heading>
        <flux:select wire:model="default_agent_id" :label="__('Default agent for new feed homes')" :description="__('Used when no agent covers the home’s ZIP code or city. Set areas on each agent’s page.')">
            <flux:select.option value="">{{ __('First active agent') }}</flux:select.option>
            @foreach ($this->agents as $option)
                <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:switch wire:model="auto_fetch" :label="__('Fetch live homes when a visitor searches a ZIP code')" :description="__('Uses at most one request per ZIP every :h hours, and stops at the monthly limit.', ['h' => config('services.rentcast.cache_hours')])" />
        <div><flux:button type="submit">{{ __('Save settings') }}</flux:button></div>
    </form>

    @endif

    @if ($this->recent->isNotEmpty())
        <section class="grid gap-2">
            <flux:heading size="lg">{{ __('Recently imported') }}</flux:heading>
            @foreach ($this->recent as $listing)
                <a href="{{ route('dashboard.listings.edit', $listing) }}" wire:navigate wire:key="recent-{{ $listing->id }}" class="flex flex-wrap items-center gap-3 rounded-lg border border-zinc-200 px-3 py-2 hover:border-zinc-400 dark:border-zinc-700">
                    <span class="font-semibold">{{ $listing->priceLabel() }}</span>
                    <span class="min-w-0 flex-1 truncate text-sm">{{ $listing->fullAddress() }}</span>
                    <flux:badge size="sm">{{ $listing->status->label() }}</flux:badge>
                    @if ($listing->edited_by_admin)<flux:badge size="sm" color="amber">{{ __('Edited') }}</flux:badge>@endif
                    <span class="text-xs text-zinc-500">{{ $listing->agent->name }}</span>
                </a>
            @endforeach
        </section>
    @endif
</div>
