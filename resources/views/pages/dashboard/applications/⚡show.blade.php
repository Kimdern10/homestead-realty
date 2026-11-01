<?php

use App\Enums\ApplicationStatus;
use App\Models\RentalApplication;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Rental application')] class extends Component {
    public RentalApplication $application;

    /**
     * Only the listing's agent, or the admin, may open it.
     */
    public function mount(RentalApplication $application): void
    {
        $user = auth()->user();

        abort_unless($user->isAdmin() || $application->listing->agent_id === ($user->agent->id ?? 0), 403);

        $this->application = $application->load(['listing.city', 'payment']);
    }

    /**
     * Approve or decline the application.
     */
    public function decide(string $status): void
    {
        $decision = ApplicationStatus::from($status);

        abort_unless(in_array($decision, [ApplicationStatus::Approved, ApplicationStatus::Declined, ApplicationStatus::New], true), 422);

        $this->application->update(['status' => $decision]);
        Flux::toast(__('Marked as :status.', ['status' => strtolower($decision->label())]), variant: 'success');
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div>
        <flux:link :href="route('dashboard.applications.index')" wire:navigate class="text-sm">← {{ __('All applications') }}</flux:link>
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <flux:heading size="xl" level="1">{{ $application->name }}</flux:heading>
            <flux:badge :color="$application->status->color()">{{ $application->status->label() }}</flux:badge>
        </div>
        <flux:text class="mt-1">{{ __('Applied for :address on :date', ['address' => $application->listing->fullAddress(), 'date' => $application->created_at->format('M j, Y')]) }}</flux:text>
    </div>

    @php($ratio = $application->rentToIncome())
    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text class="text-sm">{{ __('Monthly income') }}</flux:text>
            <div class="mt-1 text-xl font-bold">${{ number_format($application->monthly_income) }}</div>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text class="text-sm">{{ __('Rent') }}</flux:text>
            <div class="mt-1 text-xl font-bold">{{ $application->listing->priceLabel() }}</div>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text class="text-sm">{{ __('Rent to income') }}</flux:text>
            <div @class(['mt-1 text-xl font-bold', 'text-green-600 dark:text-green-400' => $ratio !== null && $ratio <= 0.33, 'text-amber-600' => $ratio !== null && $ratio > 0.33])>{{ $ratio !== null ? round($ratio * 100).'%' : '—' }}</div>
            <flux:text class="text-xs">{{ __('Under 33% is usually comfortable.') }}</flux:text>
        </div>
    </div>

    <dl class="grid gap-4 rounded-xl border border-zinc-200 p-4 sm:grid-cols-2 dark:border-zinc-700">
        @foreach ([
            __('Email') => $application->email,
            __('Phone') => $application->phone,
            __('Move-in date') => $application->move_in_date->format('l, M j, Y'),
            __('People') => $application->occupants.($application->has_pets ? ' · '.__('has pets') : ''),
            __('Employer') => $application->employer ?: '—',
            __('Job title') => $application->job_title ?: '—',
            __('Current address') => $application->current_address ?: '—',
            __('Fee') => $application->payment ? $application->payment->amountLabel().' · '.$application->payment->status->label() : __('No fee'),
        ] as $label => $value)
            <div><dt class="text-sm text-zinc-500">{{ $label }}</dt><dd class="font-medium">{{ $value }}</dd></div>
        @endforeach
    </dl>

    @if ($application->references)
        <div>
            <flux:heading size="sm">{{ __('References') }}</flux:heading>
            <div class="mt-2 whitespace-pre-line rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/60">{{ $application->references }}</div>
        </div>
    @endif
    @if ($application->message)
        <div>
            <flux:heading size="sm">{{ __('Message') }}</flux:heading>
            <div class="mt-2 whitespace-pre-line rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/60">{{ $application->message }}</div>
        </div>
    @endif

    <div class="flex flex-wrap gap-2">
        <flux:button variant="primary" icon="envelope" href="mailto:{{ $application->email }}?subject={{ rawurlencode('Your application for '.$application->listing->address) }}">{{ __('Email applicant') }}</flux:button>
        @if ($application->status !== \App\Enums\ApplicationStatus::Approved)
            <flux:button icon="check" wire:click="decide('approved')">{{ __('Approve') }}</flux:button>
        @endif
        @if ($application->status !== \App\Enums\ApplicationStatus::Declined)
            <flux:button icon="x-mark" wire:click="decide('declined')" wire:confirm="{{ __('Decline this application?') }}">{{ __('Decline') }}</flux:button>
        @endif
    </div>
</div>
