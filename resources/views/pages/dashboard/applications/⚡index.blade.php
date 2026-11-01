<?php

use App\Enums\ApplicationStatus;
use App\Models\RentalApplication;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Rental applications')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $status = '';

    /**
     * Start again from page one when the filter changes.
     */
    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, RentalApplication>
     */
    #[Computed]
    public function applications(): LengthAwarePaginator
    {
        return RentalApplication::query()
            ->visibleTo(auth()->user())
            ->with('listing.city')
            ->when(
                ApplicationStatus::tryFrom($this->status),
                fn (Builder $query, ApplicationStatus $status) => $query->where('status', $status),
                fn (Builder $query) => $query->where('status', '!=', ApplicationStatus::AwaitingPayment),
            )
            ->latest()
            ->paginate(20);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Rental applications') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Applications from renters. Unpaid ones are hidden unless you filter for them.') }}</flux:text>
    </div>

    <flux:select wire:model.live="status" class="max-w-xs" :aria-label="__('Status')">
        <flux:select.option value="">{{ __('All paid applications') }}</flux:select.option>
        @foreach (\App\Enums\ApplicationStatus::cases() as $option)
            <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
        @endforeach
    </flux:select>

    @if ($this->applications->isEmpty())
        <flux:callout icon="document-text">{{ __('No rental applications yet. Renters apply from any home for rent.') }}</flux:callout>
    @else
        <div class="grid gap-2">
            @foreach ($this->applications as $application)
                <a href="{{ route('dashboard.applications.show', $application) }}" wire:navigate wire:key="application-{{ $application->id }}" class="flex flex-wrap items-center gap-3 rounded-xl border border-zinc-200 p-3 hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold">{{ $application->name }}</span>
                            <flux:badge size="sm" :color="$application->status->color()">{{ $application->status->label() }}</flux:badge>
                        </div>
                        <div class="truncate text-sm">{{ $application->listing->fullAddress() }}</div>
                        <div class="text-xs text-zinc-500">{{ __('Move in') }} {{ $application->move_in_date->format('M j, Y') }} · ${{ number_format($application->monthly_income) }}/mo {{ __('income') }} · {{ $application->occupants }} {{ Str::plural('person', $application->occupants) }}</div>
                    </div>
                    <flux:text class="text-sm">{{ $application->created_at->diffForHumans() }}</flux:text>
                </a>
            @endforeach
        </div>

        <flux:pagination :paginator="$this->applications" />
    @endif
</div>
