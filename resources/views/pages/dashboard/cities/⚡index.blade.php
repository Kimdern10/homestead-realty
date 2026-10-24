<?php

use App\Models\City;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Cities')] class extends Component {
    /**
     * @return Collection<int, City>
     */
    #[Computed]
    public function cities(): Collection
    {
        return City::query()->ordered()->withCount('listings')->get();
    }

    /**
     * Delete a city that has no listings.
     */
    public function delete(int $id): void
    {
        $city = City::query()->withCount('listings')->findOrFail($id);

        if ($city->listings_count > 0) {
            Flux::toast(__('Move or delete this city’s :count listings first.', ['count' => $city->listings_count]), variant: 'danger');

            return;
        }

        if ($city->image_path) {
            Storage::disk('public')->delete([$city->image_path, str_replace('.webp', '-sm.webp', $city->image_path)]);
        }

        $city->delete();
        Flux::toast(__('City deleted.'), variant: 'success');
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Cities') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Every listing belongs to a city. Each city gets its own page with prices, neighborhoods and homes.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" :href="route('dashboard.cities.create')" wire:navigate>{{ __('Add a city') }}</flux:button>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($this->cities as $city)
            <div wire:key="city-{{ $city->id }}" class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                @if ($image = $city->imageUrl(small: true))
                    <img src="{{ $image }}" alt="" class="aspect-[16/9] w-full object-cover">
                @else
                    <div class="aspect-[16/9] w-full bg-zinc-100 dark:bg-zinc-800"></div>
                @endif
                <div class="flex items-center gap-3 p-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold">{{ $city->label() }}</span>
                            @if ($city->is_featured)<flux:badge size="sm" color="amber">{{ __('Home page') }}</flux:badge>@endif
                        </div>
                        <div class="text-sm text-zinc-500">{{ $city->listings_count }} {{ __('listings') }} · {{ __('Sort') }} {{ $city->sort_order }}</div>
                    </div>
                    <flux:button size="sm" icon="pencil-square" :href="route('dashboard.cities.edit', $city)" wire:navigate :aria-label="__('Edit')" />
                    <flux:button size="sm" variant="danger" icon="trash" wire:click="delete({{ $city->id }})" wire:confirm="{{ __('Delete this city?') }}" :aria-label="__('Delete')" />
                </div>
            </div>
        @empty
            <flux:callout icon="map">{{ __('No cities yet. Add the first city you sell in.') }}</flux:callout>
        @endforelse
    </div>
</div>
