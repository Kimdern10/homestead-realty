<?php

use App\Enums\ListingStatus;
use App\Models\Agent;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Agents')] class extends Component {
    /**
     * @return Collection<int, Agent>
     */
    #[Computed]
    public function agents(): Collection
    {
        return Agent::query()
            ->with('user')
            ->withCount([
                'listings as active_count' => fn ($query) => $query->whereIn('status', [ListingStatus::ForSale, ListingStatus::ForRent, ListingStatus::Pending]),
                'listings as sold_count' => fn ($query) => $query->where('status', ListingStatus::Sold),
            ])
            ->orderBy('name')
            ->get();
    }

    /**
     * Show or hide an agent (and their listings) on the website, and allow or block their login.
     */
    public function toggleActive(int $id): void
    {
        $agent = Agent::query()->findOrFail($id);
        $agent->update(['is_active' => ! $agent->is_active]);

        Flux::toast($agent->is_active ? __(':name is active again.', ['name' => $agent->name]) : __(':name and their listings are now hidden.', ['name' => $agent->name]), variant: 'success');
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Agents') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Each agent has a public profile and their own login to manage their listings and enquiries.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="user-plus" :href="route('dashboard.agents.create')" wire:navigate>{{ __('Add an agent') }}</flux:button>
    </div>

    @if ($this->agents->isEmpty())
        <flux:callout icon="users">{{ __('No agents yet. Add one to start posting listings.') }}</flux:callout>
    @else
        <div class="grid gap-3">
            @foreach ($this->agents as $agent)
                <div wire:key="agent-{{ $agent->id }}" class="flex flex-wrap items-center gap-4 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                    @if ($photo = $agent->photoUrl())
                        <img src="{{ $photo }}" alt="" class="size-12 flex-none rounded-full object-cover">
                    @else
                        <flux:avatar :initials="$agent->initials()" class="size-12!" />
                    @endif
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold">{{ $agent->name }}</span>
                            @unless ($agent->is_active)<flux:badge size="sm" color="zinc">{{ __('Inactive') }}</flux:badge>@endunless
                        </div>
                        <div class="text-sm text-zinc-500">{{ $agent->title }} · {{ $agent->user->email }}</div>
                        <div class="text-xs text-zinc-400">{{ $agent->active_count }} {{ __('active') }} · {{ $agent->sold_count }} {{ __('sold') }}</div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <flux:button size="sm" icon="pencil-square" :href="route('dashboard.agents.edit', $agent)" wire:navigate>{{ __('Edit') }}</flux:button>
                        @if ($agent->is_active)
                            <flux:button size="sm" icon="arrow-top-right-on-square" :href="route('agents.show', $agent)" target="_blank">{{ __('Profile') }}</flux:button>
                        @endif
                        <flux:button size="sm" wire:click="toggleActive({{ $agent->id }})" wire:confirm="{{ $agent->is_active ? __('Hide this agent and all their listings from the website?') : __('Show this agent and their listings again?') }}">{{ $agent->is_active ? __('Deactivate') : __('Activate') }}</flux:button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
