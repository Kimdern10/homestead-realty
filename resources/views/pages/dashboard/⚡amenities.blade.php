<?php

use App\Models\Amenity;
use App\Models\Listing;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Amenities')] class extends Component {
    public string $name = '';

    public ?int $editingId = null;

    public string $editingName = '';

    /**
     * @return Collection<int, Amenity>
     */
    #[Computed]
    public function amenities(): Collection
    {
        return Amenity::query()->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * Add one amenity, or several separated by commas.
     */
    public function add(): void
    {
        $names = collect(explode(',', $this->name))->map(fn (string $name) => trim($name))->filter()->unique();

        if ($names->isEmpty()) {
            $this->addError('name', 'Type an amenity, e.g. Wine cellar.');

            return;
        }

        $existing = Amenity::query()->pluck('name')->map(fn (string $name) => strtolower($name));
        $order = (int) Amenity::query()->max('sort_order');
        $added = 0;

        foreach ($names as $name) {
            if (strlen($name) > 60 || $existing->contains(strtolower($name))) {
                continue;
            }

            Amenity::query()->create(['name' => $name, 'sort_order' => ++$order]);
            $added++;
        }

        $this->reset('name');
        unset($this->amenities);
        Flux::toast(trans_choice(':count amenity added.|:count amenities added.', $added, ['count' => $added]), variant: $added ? 'success' : 'warning');
    }

    /**
     * Start renaming an amenity.
     */
    public function edit(int $id): void
    {
        $this->editingId = $id;
        $this->editingName = (string) Amenity::query()->whereKey($id)->value('name');
        $this->resetValidation();
    }

    /**
     * Rename an amenity and update the listings that have it.
     */
    public function rename(): void
    {
        $amenity = Amenity::query()->findOrFail($this->editingId);
        $this->editingName = trim($this->editingName);

        $this->validate(['editingName' => ['required', 'string', 'max:60', Rule::unique('amenities', 'name')->ignore($amenity->id)]], [
            'editingName.unique' => 'That amenity already exists.',
        ]);

        $old = $amenity->name;
        $amenity->update(['name' => $this->editingName]);

        Listing::query()->whereNotNull('amenities')->get(['id', 'amenities'])->each(function (Listing $listing) use ($old) {
            if (in_array($old, $listing->amenities ?? [], true)) {
                $listing->update(['amenities' => array_values(array_map(fn (string $name) => $name === $old ? $this->editingName : $name, $listing->amenities))]);
            }
        });

        $this->reset('editingId', 'editingName');
        unset($this->amenities);
        Flux::toast(__('Amenity renamed.'), variant: 'success');
    }

    /**
     * Delete an amenity and take it off listings.
     */
    public function delete(int $id): void
    {
        $amenity = Amenity::query()->findOrFail($id);

        Listing::query()->whereNotNull('amenities')->get(['id', 'amenities'])->each(function (Listing $listing) use ($amenity) {
            if (in_array($amenity->name, $listing->amenities ?? [], true)) {
                $listing->update(['amenities' => array_values(array_diff($listing->amenities, [$amenity->name]))]);
            }
        });

        $amenity->delete();
        unset($this->amenities);
        Flux::toast(__('Amenity deleted.'), variant: 'success');
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Amenities') }}</flux:heading>
        <flux:text class="mt-1">{{ __('The features agents can tick when adding a home, like “Swimming pool” or “Garage”.') }}</flux:text>
    </div>

    <form wire:submit="add" class="flex flex-wrap items-end gap-3 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <div class="min-w-60 flex-1">
            <flux:input wire:model="name" :label="__('Add amenities')" :placeholder="__('e.g. Wine cellar, Sauna, Tennis court')" :description="__('Separate several with commas.')" />
        </div>
        <flux:button type="submit" variant="primary" icon="plus">{{ __('Add') }}</flux:button>
    </form>

    <div class="flex flex-wrap gap-2">
        @forelse ($this->amenities as $amenity)
            <div wire:key="amenity-{{ $amenity->id }}" class="flex items-center gap-1 rounded-full border border-zinc-200 py-1 ps-3 pe-1 dark:border-zinc-700">
                @if ($editingId === $amenity->id)
                    <form wire:submit="rename" class="flex items-center gap-1">
                        <flux:input size="sm" wire:model="editingName" class="w-40" :aria-label="__('New name')" />
                        <flux:button size="xs" type="submit" icon="check" :aria-label="__('Save')" />
                        <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="$set('editingId', null)" :aria-label="__('Cancel')" />
                    </form>
                @else
                    <span class="text-sm">{{ $amenity->name }}</span>
                    <flux:button size="xs" variant="ghost" icon="pencil" wire:click="edit({{ $amenity->id }})" :aria-label="__('Rename :name', ['name' => $amenity->name])" />
                    <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="delete({{ $amenity->id }})" wire:confirm="{{ __('Delete this amenity? It will be removed from listings too.') }}" :aria-label="__('Delete :name', ['name' => $amenity->name])" />
                @endif
            </div>
        @empty
            <flux:callout icon="sparkles">{{ __('No amenities yet. Add some above.') }}</flux:callout>
        @endforelse
    </div>
    @error('editingName')<flux:text class="text-sm text-red-600!">{{ $message }}</flux:text>@enderror
</div>
