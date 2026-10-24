<?php

use App\Enums\ListingStatus;
use App\Models\Agent;
use App\Models\Listing;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Listings')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $agent = '';

    #[Url(except: '')]
    public string $source = '';

    /**
     * Start again from page one when a filter changes.
     */
    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Listing>
     */
    #[Computed]
    public function listings(): LengthAwarePaginator
    {
        $user = auth()->user();

        return Listing::query()
            ->with(['city', 'coverPhoto', 'agent'])
            ->withCount(['enquiries', 'savedBy'])
            ->when(! $user->isAdmin(), fn (Builder $query) => $query->where('agent_id', $user->agent->id))
            ->when($user->isAdmin() && $this->agent !== '', fn (Builder $query) => $query->where('agent_id', (int) $this->agent))
            ->when(ListingStatus::tryFrom($this->status), fn (Builder $query, ListingStatus $status) => $query->where('status', $status))
            ->when($this->source === 'feed', fn (Builder $query) => $query->where('source', '!=', 'manual'))
            ->when($this->source === 'manual', fn (Builder $query) => $query->where('source', 'manual'))
            ->when(trim($this->search) !== '', function (Builder $query) {
                $like = '%'.trim($this->search).'%';
                $query->where(fn (Builder $where) => $where->where('address', 'like', $like)->orWhere('title', 'like', $like)->orWhere('zip', trim($this->search))->orWhereHas('city', fn (Builder $city) => $city->where('name', 'like', $like)));
            })
            ->latest('updated_at')
            ->paginate(15);
    }

    /**
     * Agents for the admin's filter.
     *
     * @return \Illuminate\Support\Collection<int, Agent>
     */
    #[Computed]
    public function agents()
    {
        return Agent::query()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Show or hide a listing on the website.
     */
    public function togglePublished(int $id): void
    {
        $listing = Listing::query()->findOrFail($id);
        $this->authorize('update', $listing);

        $listing->forceFill(['is_published' => ! $listing->is_published, 'edited_by_admin' => $listing->edited_by_admin || $listing->isFromApi()])->save();
        Flux::toast($listing->is_published ? __('Listing is live on the website.') : __('Listing hidden from the website.'), variant: 'success');
    }

    /**
     * Quickly change the status, e.g. mark as sold.
     */
    public function setStatus(int $id, string $status): void
    {
        $listing = Listing::query()->findOrFail($id);
        $this->authorize('update', $listing);
        $newStatus = ListingStatus::from($status);

        $listing->forceFill([
            'status' => $newStatus,
            'sold_at' => $newStatus === ListingStatus::Sold ? ($listing->sold_at ?? today()) : null,
            'edited_by_admin' => $listing->edited_by_admin || $listing->isFromApi(),
        ])->save();

        Flux::toast(__('Marked as :status.', ['status' => strtolower($newStatus->label())]), variant: 'success');
    }

    /**
     * Delete a listing and its photos.
     */
    public function delete(int $id): void
    {
        $listing = Listing::query()->with('photos')->findOrFail($id);
        $this->authorize('delete', $listing);

        $paths = $listing->imagePaths();
        $listing->delete();
        Storage::disk('public')->delete($paths);

        Flux::toast(__('Listing deleted.'), variant: 'success');
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ auth()->user()->isAdmin() ? __('Listings') : __('My listings') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Homes for sale, for rent and sold. Draft listings are hidden from visitors.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" :href="route('dashboard.listings.create')" wire:navigate data-test="add-listing">{{ __('Add a listing') }}</flux:button>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search address, city or ZIP')" :aria-label="__('Search listings')" />
        <flux:select wire:model.live="status" :aria-label="__('Status')">
            <flux:select.option value="">{{ __('Any status') }}</flux:select.option>
            @foreach (\App\Enums\ListingStatus::options() as $value => $label)
                <flux:select.option :value="$value">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="source" :aria-label="__('Source')">
            <flux:select.option value="">{{ __('All sources') }}</flux:select.option>
            <flux:select.option value="manual">{{ __('Added by us') }}</flux:select.option>
            <flux:select.option value="feed">{{ __('From the live feed') }}</flux:select.option>
        </flux:select>
        @if (auth()->user()->isAdmin())
            <flux:select wire:model.live="agent" :aria-label="__('Agent')">
                <flux:select.option value="">{{ __('All agents') }}</flux:select.option>
                @foreach ($this->agents as $option)
                    <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif
    </div>

    @if ($this->listings->isEmpty())
        <flux:callout icon="home">{{ __('No listings found. Add your first home with photos, price and details.') }}</flux:callout>
    @else
        <div class="grid gap-3">
            @foreach ($this->listings as $listing)
                <div wire:key="listing-{{ $listing->id }}" class="flex flex-wrap items-center gap-4 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                    @if ($cover = $listing->coverUrl())
                        <img src="{{ $cover }}" alt="" class="h-16 w-24 flex-none rounded-lg object-cover">
                    @else
                        <div class="flex h-16 w-24 flex-none items-center justify-center rounded-lg bg-zinc-100 text-xs text-zinc-500 dark:bg-zinc-800">{{ __('No photos') }}</div>
                    @endif
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold">{{ $listing->priceLabel() }}</span>
                            <flux:badge size="sm" :color="match ($listing->status) { \App\Enums\ListingStatus::ForSale => 'red', \App\Enums\ListingStatus::ForRent => 'blue', \App\Enums\ListingStatus::Pending => 'amber', default => 'zinc' }">{{ $listing->status->label() }}</flux:badge>
                            @unless ($listing->is_published)<flux:badge size="sm" color="zinc" icon="eye-slash">{{ __('Draft') }}</flux:badge>@endunless
                            @if ($listing->isFromApi())<flux:badge size="sm" color="sky" icon="cloud-arrow-down">{{ __('Live feed') }}</flux:badge>@endif
                            @if ($listing->is_featured)<flux:badge size="sm" color="amber" icon="star">{{ __('Featured') }}</flux:badge>@endif
                        </div>
                        <div class="truncate text-sm">{{ $listing->fullAddress() }}</div>
                        <div class="text-xs text-zinc-500">
                            {{ implode(' · ', $listing->facts()) }}
                            @if (auth()->user()->isAdmin()) · {{ $listing->agent->name }} @endif
                            · {{ number_format($listing->views) }} {{ __('views') }} · {{ $listing->saved_by_count }} {{ __('saves') }} · {{ $listing->enquiries_count }} {{ __('enquiries') }}
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <flux:button size="sm" icon="pencil-square" :href="route('dashboard.listings.edit', $listing)" wire:navigate>{{ __('Edit') }}</flux:button>
                        <flux:dropdown>
                            <flux:button size="sm" icon:trailing="chevron-down">{{ __('More') }}</flux:button>
                            <flux:menu>
                                @if ($listing->is_published)
                                    <flux:menu.item icon="arrow-top-right-on-square" :href="route('listings.show', $listing)" target="_blank">{{ __('View on website') }}</flux:menu.item>
                                @endif
                                <flux:menu.item icon="{{ $listing->is_published ? 'eye-slash' : 'eye' }}" wire:click="togglePublished({{ $listing->id }})">{{ $listing->is_published ? __('Hide (make draft)') : __('Publish') }}</flux:menu.item>
                                <flux:menu.separator />
                                @foreach (\App\Enums\ListingStatus::cases() as $option)
                                    @if ($option !== $listing->status)
                                        <flux:menu.item wire:click="setStatus({{ $listing->id }}, '{{ $option->value }}')">{{ __('Mark as :status', ['status' => strtolower($option->label())]) }}</flux:menu.item>
                                    @endif
                                @endforeach
                                <flux:menu.separator />
                                <flux:menu.item variant="danger" icon="trash" wire:click="delete({{ $listing->id }})" wire:confirm="{{ __('Delete this listing and all its photos? This cannot be undone.') }}">{{ __('Delete') }}</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                </div>
            @endforeach
        </div>

        <flux:pagination :paginator="$this->listings" />
    @endif
</div>
