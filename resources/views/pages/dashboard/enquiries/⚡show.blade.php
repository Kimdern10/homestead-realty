<?php

use App\Enums\EnquiryStatus;
use App\Models\Enquiry;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Enquiry')] class extends Component {
    public Enquiry $enquiry;

    /**
     * Only the agent it was sent to, or the admin, may open it.
     */
    public function mount(Enquiry $enquiry): void
    {
        $this->authorize('view', $enquiry);
        $this->enquiry = $enquiry->load(['listing.city', 'agent']);
    }

    /**
     * Move the enquiry along: new, contacted, closed.
     */
    public function setStatus(string $status): void
    {
        $this->authorize('update', $this->enquiry);

        $this->enquiry->update(['status' => EnquiryStatus::from($status)]);
        Flux::toast(__('Marked as :status.', ['status' => strtolower($this->enquiry->status->label())]), variant: 'success');
    }

    /**
     * Delete the enquiry (admins only).
     */
    public function delete(): void
    {
        $this->authorize('delete', $this->enquiry);

        $this->enquiry->delete();
        Flux::toast(__('Enquiry deleted.'), variant: 'success');

        $this->redirectRoute('dashboard.enquiries.index', navigate: true);
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div>
        <flux:link :href="route('dashboard.enquiries.index')" wire:navigate class="text-sm">← {{ __('All enquiries') }}</flux:link>
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <flux:heading size="xl" level="1">{{ $enquiry->type->label() }} {{ __('from') }} {{ $enquiry->name }}</flux:heading>
            <flux:badge :color="$enquiry->status->color()">{{ $enquiry->status->label() }}</flux:badge>
        </div>
        <flux:text class="mt-1">{{ __('Received :date', ['date' => $enquiry->created_at->format('l, M j, Y \a\t g:ia')]) }}</flux:text>
    </div>

    @if ($tour = $enquiry->tourSummary())
        <flux:callout icon="calendar-days" color="red">
            <flux:callout.heading>{{ __('Requested tour') }}</flux:callout.heading>
            <flux:callout.text>{{ $tour }}. {{ __('Reply to confirm the exact time.') }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid gap-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700 sm:grid-cols-2">
        <div>
            <flux:text class="text-sm">{{ __('Email') }}</flux:text>
            <flux:link href="mailto:{{ $enquiry->email }}?subject={{ rawurlencode('Re: '.($enquiry->listing?->address ?? 'your enquiry')) }}">{{ $enquiry->email }}</flux:link>
        </div>
        <div>
            <flux:text class="text-sm">{{ __('Phone') }}</flux:text>
            @if ($enquiry->phone)
                <flux:link href="tel:{{ preg_replace('/[^0-9+]/', '', $enquiry->phone) }}">{{ $enquiry->phone }}</flux:link>
            @else
                <span class="text-zinc-500">—</span>
            @endif
        </div>
        @if ($enquiry->listing)
            <div class="sm:col-span-2">
                <flux:text class="text-sm">{{ __('Home') }}</flux:text>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-medium">{{ $enquiry->listing->fullAddress() }}</span>
                    <span class="text-sm text-zinc-500">{{ $enquiry->listing->priceLabel() }}</span>
                    <flux:link :href="route('listings.show', $enquiry->listing)" target="_blank" class="text-sm">{{ __('View') }}</flux:link>
                </div>
            </div>
        @endif
        @if (auth()->user()->isAdmin() && $enquiry->agent)
            <div>
                <flux:text class="text-sm">{{ __('Sent to') }}</flux:text>
                <span>{{ $enquiry->agent->name }}</span>
            </div>
        @endif
    </div>

    @if (filled($enquiry->message))
        <div class="rounded-xl bg-zinc-50 p-4 whitespace-pre-line dark:bg-zinc-800/60">{{ $enquiry->message }}</div>
    @endif

    <div class="flex flex-wrap gap-2">
        <flux:button variant="primary" icon="envelope" href="mailto:{{ $enquiry->email }}?subject={{ rawurlencode('Re: '.($enquiry->listing?->address ?? 'your enquiry')) }}">{{ __('Reply by email') }}</flux:button>
        @foreach (\App\Enums\EnquiryStatus::cases() as $option)
            @if ($option !== $enquiry->status)
                <flux:button wire:click="setStatus('{{ $option->value }}')">{{ __('Mark as :status', ['status' => strtolower($option->label())]) }}</flux:button>
            @endif
        @endforeach
        @if (auth()->user()->isAdmin())
            <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="{{ __('Delete this enquiry? This cannot be undone.') }}">{{ __('Delete') }}</flux:button>
        @endif
    </div>
</div>
