<?php

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\OpenHouse;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Open houses')] class extends Component {
    public string $listing_id = '';

    public string $date = '';

    public string $starts = '13:00';

    public string $ends = '15:00';

    public string $note = '';

    /**
     * Start with next Saturday, the usual open-house day.
     */
    public function mount(): void
    {
        $this->date = CarbonImmutable::today()->next(CarbonImmutable::SATURDAY)->toDateString();
        $this->listing_id = (string) request()->query('listing', '');
    }

    /**
     * Homes this person may add an open house to.
     *
     * @return Builder<Listing>
     */
    private function ownListings(): Builder
    {
        $user = auth()->user();

        return Listing::query()
            ->when(! $user->isAdmin(), fn (Builder $query) => $query->where('agent_id', $user->agent->id ?? 0));
    }

    /**
     * @return Collection<int, Listing>
     */
    #[Computed]
    public function listings(): Collection
    {
        return $this->ownListings()
            ->where('is_published', true)
            ->whereIn('status', [ListingStatus::ForSale, ListingStatus::ForRent, ListingStatus::Pending])
            ->orderBy('address')
            ->get(['id', 'address', 'zip', 'city_id']);
    }

    /**
     * @return Collection<int, OpenHouse>
     */
    #[Computed]
    public function upcoming(): Collection
    {
        return OpenHouse::query()->upcoming()
            ->whereIn('listing_id', $this->ownListings()->select('id'))
            ->with('listing:id,address,slug')
            ->get();
    }

    /**
     * Schedule an open house.
     */
    public function add(): void
    {
        $validated = $this->validate([
            'listing_id' => ['required', 'integer', Rule::in($this->listings->modelKeys())],
            'date' => ['required', 'date', 'after_or_equal:today', 'before:'.now()->addMonths(3)->toDateString()],
            'starts' => ['required', 'date_format:H:i'],
            'ends' => ['required', 'date_format:H:i', 'after:starts'],
            'note' => ['nullable', 'string', 'max:160'],
        ], [
            'listing_id.required' => 'Pick a home.',
            'ends.after' => 'The end time must be after the start time.',
            'date.after_or_equal' => 'Pick today or a later day.',
        ]);

        $startsAt = CarbonImmutable::parse($validated['date'].' '.$validated['starts']);

        if ($startsAt->isPast()) {
            $this->addError('starts', 'That time has already passed.');

            return;
        }

        OpenHouse::query()->create([
            'listing_id' => (int) $validated['listing_id'],
            'starts_at' => $startsAt,
            'ends_at' => CarbonImmutable::parse($validated['date'].' '.$validated['ends']),
            'note' => $validated['note'] ?: null,
        ]);

        $this->reset('note');
        unset($this->upcoming);
        Flux::toast(__('Open house added. It now shows on the home and the Open houses page.'), variant: 'success');
    }

    /**
     * Cancel an open house.
     */
    public function cancel(int $id): void
    {
        OpenHouse::query()->whereKey($id)->whereIn('listing_id', $this->ownListings()->select('id'))->delete();
        unset($this->upcoming);
        Flux::toast(__('Open house cancelled.'));
    }
}; ?>

<div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Open houses') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Pick a home, a day and the hours. Visitors see an “Open house” badge on the home and on the Open houses page.') }}</flux:text>
    </div>

    <form wire:submit="add" class="grid gap-4 rounded-xl border border-zinc-200 p-5 sm:grid-cols-2 dark:border-zinc-700">
        <div class="sm:col-span-2">
            <flux:select wire:model="listing_id" :label="__('Home')" :placeholder="__('Choose a home…')">
                @foreach ($this->listings as $listing)
                    <flux:select.option value="{{ $listing->id }}">{{ $listing->address }} ({{ $listing->zip }})</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <flux:input type="date" wire:model="date" :label="__('Day')" min="{{ today()->toDateString() }}" />
        <div class="grid grid-cols-2 gap-3">
            <flux:input type="time" wire:model="starts" :label="__('From')" step="900" />
            <flux:input type="time" wire:model="ends" :label="__('To')" step="900" />
        </div>
        <div class="sm:col-span-2">
            <flux:input wire:model="note" :label="__('Note (optional)')" :placeholder="__('e.g. Refreshments served. Park on the street.')" />
        </div>
        <div class="sm:col-span-2">
            <flux:button type="submit" variant="primary" icon="plus">{{ __('Add open house') }}</flux:button>
        </div>
    </form>

    <div class="flex flex-col gap-2">
        <flux:heading size="lg">{{ __('Coming up') }}</flux:heading>
        @forelse ($this->upcoming as $openHouse)
            <div wire:key="oh-{{ $openHouse->id }}" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div>
                    <flux:text class="font-semibold text-zinc-800! dark:text-white!">{{ $openHouse->label() }}</flux:text>
                    <flux:text>
                        <a class="underline" href="{{ route('listings.show', $openHouse->listing) }}" target="_blank">{{ $openHouse->listing->address }}</a>
                        @if ($openHouse->note) · {{ $openHouse->note }} @endif
                    </flux:text>
                </div>
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="cancel({{ $openHouse->id }})" wire:confirm="{{ __('Cancel this open house?') }}">{{ __('Cancel') }}</flux:button>
            </div>
        @empty
            <flux:callout icon="calendar-days">{{ __('No open houses planned. Add one above.') }}</flux:callout>
        @endforelse
    </div>
</div>
