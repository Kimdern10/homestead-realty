<?php

use App\Enums\PropertyType;
use App\Models\HomeType;
use App\Models\Listing;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Home types')] class extends Component {
    public ?int $editingId = null;

    public string $name = '';

    public string $kind = 'house';

    /**
     * @return Collection<int, HomeType>
     */
    #[Computed]
    public function types(): Collection
    {
        return HomeType::query()->ordered()->withCount('listings')->get();
    }

    /**
     * Load a type into the form.
     */
    public function edit(int $id): void
    {
        $type = HomeType::query()->findOrFail($id);

        $this->editingId = $type->id;
        $this->name = $type->name;
        $this->kind = $type->kind->value;
        $this->resetValidation();
    }

    /**
     * Clear the form.
     */
    public function cancel(): void
    {
        $this->reset('editingId', 'name', 'kind');
        $this->resetValidation();
    }

    /**
     * Add or rename a home type. Its web key never changes, so listings keep their type.
     */
    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('home_types', 'name')->ignore($this->editingId)],
            'kind' => ['required', Rule::enum(PropertyType::class)],
        ], ['name.unique' => 'There is already a home type with this name.']);

        if ($this->editingId) {
            HomeType::query()->whereKey($this->editingId)->update($validated);
        } else {
            HomeType::query()->create([
                ...$validated,
                'slug' => HomeType::uniqueSlug($validated['name']),
                'sort_order' => (int) HomeType::query()->max('sort_order') + 1,
            ]);
        }

        Flux::toast($this->editingId ? __('Home type updated.') : __('Home type added.'), variant: 'success');
        $this->cancel();
        unset($this->types);
    }

    /**
     * Move a type up or down the list.
     */
    public function move(int $id, int $direction): void
    {
        $types = HomeType::query()->ordered()->get()->values();
        $index = $types->search(fn (HomeType $type) => $type->id === $id);
        $target = $index === false ? null : $types->get($index + $direction);

        if ($index === false || $target === null) {
            return;
        }

        $order = $types->pluck('id')->all();
        [$order[$index], $order[$index + $direction]] = [$order[$index + $direction], $order[$index]];

        foreach ($order as $position => $typeId) {
            HomeType::query()->whereKey($typeId)->update(['sort_order' => $position]);
        }

        unset($this->types);
    }

    /**
     * Delete a type that no listing uses.
     */
    public function delete(int $id): void
    {
        $type = HomeType::query()->findOrFail($id);

        if (Listing::query()->where('property_type', $type->slug)->exists()) {
            Flux::toast(__('Listings use this type. Change them first, or rename the type instead.'), variant: 'danger');

            return;
        }

        $type->delete();
        unset($this->types);
        Flux::toast(__('Home type deleted.'), variant: 'success');
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Home types') }}</flux:heading>
        <flux:text class="mt-1">{{ __('The types agents choose from when adding a home, and visitors filter by in search. “Works like” decides the picture used before photos are added, and land skips bedrooms and bathrooms.') }}</flux:text>
    </div>

    <form wire:submit="save" class="grid gap-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading size="lg">{{ $editingId ? __('Edit home type') : __('Add a home type') }}</flux:heading>
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="name" :label="__('Name')" :placeholder="__('e.g. Duplex, Mobile home, Ranch')" />
            <flux:select wire:model="kind" :label="__('Works like')">
                @foreach (\App\Enums\PropertyType::options() as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">{{ $editingId ? __('Save changes') : __('Add home type') }}</flux:button>
            @if ($editingId)<flux:button wire:click="cancel" variant="ghost">{{ __('Cancel') }}</flux:button>@endif
        </div>
    </form>

    <div class="grid gap-2">
        @foreach ($this->types as $type)
            <div wire:key="type-{{ $type->id }}" class="flex items-center gap-3 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                <div class="min-w-0 flex-1">
                    <span class="font-semibold">{{ $type->name }}</span>
                    <span class="text-sm text-zinc-500">· {{ __('works like') }} {{ strtolower($type->kind->label()) }} · {{ $type->listings_count }} {{ Str::plural('listing', $type->listings_count) }}</span>
                </div>
                <flux:button size="sm" variant="ghost" icon="arrow-up" wire:click="move({{ $type->id }}, -1)" :disabled="$loop->first" :aria-label="__('Move up')" />
                <flux:button size="sm" variant="ghost" icon="arrow-down" wire:click="move({{ $type->id }}, 1)" :disabled="$loop->last" :aria-label="__('Move down')" />
                <flux:button size="sm" icon="pencil-square" wire:click="edit({{ $type->id }})" :aria-label="__('Edit')" />
                <flux:button size="sm" variant="danger" icon="trash" wire:click="delete({{ $type->id }})" wire:confirm="{{ __('Delete this home type?') }}" :aria-label="__('Delete')" />
            </div>
        @endforeach
    </div>
</div>
