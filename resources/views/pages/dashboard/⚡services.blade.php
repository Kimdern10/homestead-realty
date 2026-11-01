<?php

use App\Models\Service;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Services')] class extends Component {
    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public string $price = '';

    public bool $is_active = true;

    /**
     * @return Collection<int, Service>
     */
    #[Computed]
    public function services(): Collection
    {
        return Service::query()->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * Load a service into the form.
     */
    public function edit(int $id): void
    {
        $service = Service::query()->findOrFail($id);

        $this->editingId = $service->id;
        $this->name = $service->name;
        $this->description = (string) $service->description;
        $this->price = (string) $service->price;
        $this->is_active = $service->is_active;
        $this->resetValidation();
    }

    /**
     * Clear the form for a new service.
     */
    public function cancel(): void
    {
        $this->reset('editingId', 'name', 'description', 'price', 'is_active');
        $this->resetValidation();
    }

    /**
     * Add or update a service.
     */
    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'integer', 'min:1', 'max:1000000'],
            'is_active' => ['boolean'],
        ]);

        Service::query()->updateOrCreate(['id' => $this->editingId], [
            ...$validated,
            'description' => $validated['description'] ?: null,
            'sort_order' => $this->editingId ? Service::query()->whereKey($this->editingId)->value('sort_order') : (int) Service::query()->max('sort_order') + 1,
        ]);

        Flux::toast($this->editingId ? __('Service updated.') : __('Service added.'), variant: 'success');
        $this->cancel();
        unset($this->services);
    }

    /**
     * Delete a service (paid bookings keep its name).
     */
    public function delete(int $id): void
    {
        Service::query()->whereKey($id)->delete();

        if ($this->editingId === $id) {
            $this->cancel();
        }

        unset($this->services);
        Flux::toast(__('Service deleted.'), variant: 'success');
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Services') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Paid services visitors can book online, e.g. a home valuation or a buyer consultation. They appear on the “Services” page when payments are on.') }}</flux:text>
    </div>

    <form wire:submit="save" class="grid gap-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading size="lg">{{ $editingId ? __('Edit service') : __('Add a service') }}</flux:heading>
        <div class="grid gap-4 sm:grid-cols-3">
            <flux:input wire:model="name" :label="__('Name')" :placeholder="__('e.g. Home valuation')" class="sm:col-span-2" />
            <flux:input wire:model="price" type="number" min="1" :label="__('Price')" placeholder="150" />
        </div>
        <flux:textarea wire:model="description" rows="2" :label="__('Short description')" :placeholder="__('e.g. A licensed agent visits and prices your home within 48 hours.')" />
        <flux:switch wire:model="is_active" :label="__('Show on the website')" />
        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">{{ $editingId ? __('Save changes') : __('Add service') }}</flux:button>
            @if ($editingId)<flux:button wire:click="cancel" variant="ghost">{{ __('Cancel') }}</flux:button>@endif
        </div>
    </form>

    <div class="grid gap-2">
        @forelse ($this->services as $service)
            <div wire:key="service-{{ $service->id }}" class="flex flex-wrap items-center gap-3 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="font-semibold">{{ $service->name }}</span>
                        <span class="text-sm text-zinc-500">{{ app(\App\Services\Payments\PaymentManager::class)->money($service->price) }}</span>
                        @unless ($service->is_active)<flux:badge size="sm" color="zinc">{{ __('Hidden') }}</flux:badge>@endunless
                    </div>
                    @if ($service->description)<div class="truncate text-sm text-zinc-500">{{ $service->description }}</div>@endif
                </div>
                <flux:button size="sm" icon="pencil-square" wire:click="edit({{ $service->id }})" :aria-label="__('Edit')" />
                <flux:button size="sm" variant="danger" icon="trash" wire:click="delete({{ $service->id }})" wire:confirm="{{ __('Delete this service?') }}" :aria-label="__('Delete')" />
            </div>
        @empty
            <flux:callout icon="briefcase">{{ __('No services yet. Add your first one above.') }}</flux:callout>
        @endforelse
    </div>
</div>
