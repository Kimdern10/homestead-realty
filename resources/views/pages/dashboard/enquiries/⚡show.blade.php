<?php

use App\Enums\EnquiryStatus;
use App\Models\Enquiry;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Lead')] class extends Component {
    public Enquiry $enquiry;

    public string $note = '';

    public string $follow_up_on = '';

    /**
     * Only the agent it was sent to, or the admin, may open it.
     */
    public function mount(Enquiry $enquiry): void
    {
        $this->authorize('view', $enquiry);
        $this->enquiry = $enquiry->load(['listing.city', 'agent', 'notes.user']);
        $this->follow_up_on = (string) $enquiry->follow_up_on?->toDateString();
    }

    /**
     * Add a private note, e.g. "Called, wants a second viewing".
     */
    public function addNote(): void
    {
        $this->authorize('update', $this->enquiry);
        $this->validate(['note' => ['required', 'string', 'max:2000']], ['note.required' => 'Write a note first.']);

        $this->enquiry->notes()->create(['user_id' => auth()->id(), 'body' => trim($this->note)]);
        $this->reset('note');
        $this->enquiry->load('notes.user');
    }

    /**
     * Delete one of the notes.
     */
    public function deleteNote(int $id): void
    {
        $this->authorize('update', $this->enquiry);
        $this->enquiry->notes()->whereKey($id)->delete();
        $this->enquiry->load('notes.user');
    }

    /**
     * Plan when to get back to this person. We email a reminder that morning.
     */
    public function saveFollowUp(): void
    {
        $this->authorize('update', $this->enquiry);
        $this->validate(['follow_up_on' => ['nullable', 'date']]);

        $this->enquiry->update(['follow_up_on' => $this->follow_up_on ?: null]);
        Flux::toast($this->follow_up_on ? __('Reminder set for :date.', ['date' => $this->enquiry->follow_up_on?->format('l, M j')]) : __('Reminder removed.'), variant: 'success');
    }

    /**
     * Quick follow-up dates.
     */
    public function followUpIn(int $days): void
    {
        $this->follow_up_on = today()->addDays($days)->toDateString();
        $this->saveFollowUp();
    }

    /**
     * Move the lead to another stage.
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
        <flux:link :href="route('dashboard.enquiries.index')" wire:navigate class="text-sm">← {{ __('All leads') }}</flux:link>
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
        @if (auth()->user()->isAdmin())
            <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="{{ __('Delete this enquiry? This cannot be undone.') }}">{{ __('Delete') }}</flux:button>
        @endif
    </div>

    <div class="flex flex-col gap-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
        <flux:heading>{{ __('Stage') }}</flux:heading>
        <div class="flex flex-wrap gap-2">
            @foreach (\App\Enums\EnquiryStatus::cases() as $option)
                <flux:button size="sm" :variant="$option === $enquiry->status ? 'primary' : 'outline'" wire:click="setStatus('{{ $option->value }}')" wire:key="stage-{{ $option->value }}">{{ $option->label() }}</flux:button>
            @endforeach
        </div>
    </div>

    <div class="flex flex-col gap-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
        <flux:heading>{{ __('Follow-up reminder') }}</flux:heading>
        <flux:text class="text-sm">{{ __('Pick a day and we will email you a reminder that morning.') }}</flux:text>
        <form wire:submit="saveFollowUp" class="flex flex-wrap items-end gap-2">
            <flux:input type="date" wire:model="follow_up_on" :aria-label="__('Follow-up date')" class="w-44" />
            <flux:button type="submit" size="sm">{{ __('Save') }}</flux:button>
            <flux:button size="sm" variant="ghost" wire:click="followUpIn(1)">{{ __('Tomorrow') }}</flux:button>
            <flux:button size="sm" variant="ghost" wire:click="followUpIn(3)">{{ __('In 3 days') }}</flux:button>
            <flux:button size="sm" variant="ghost" wire:click="followUpIn(7)">{{ __('Next week') }}</flux:button>
        </form>
        @if ($enquiry->followUpDue())
            <flux:badge color="red" icon="bell" class="self-start">{{ __('Due now') }}</flux:badge>
        @endif
    </div>

    <div class="flex flex-col gap-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
        <flux:heading>{{ __('Notes') }}</flux:heading>
        <form wire:submit="addNote" class="flex flex-col gap-2">
            <flux:textarea wire:model="note" rows="2" :placeholder="__('e.g. Called, wants a second viewing on Saturday.')" :aria-label="__('New note')" />
            <flux:button type="submit" size="sm" class="self-start" icon="plus">{{ __('Add note') }}</flux:button>
        </form>
        @foreach ($enquiry->notes as $item)
            <div wire:key="note-{{ $item->id }}" class="flex items-start justify-between gap-3 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800/60">
                <div>
                    <div class="whitespace-pre-line text-sm">{{ $item->body }}</div>
                    <div class="mt-1 text-xs text-zinc-500">{{ $item->user?->name ?? __('Someone') }} · {{ $item->created_at->diffForHumans() }}</div>
                </div>
                <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="deleteNote({{ $item->id }})" wire:confirm="{{ __('Delete this note?') }}" :aria-label="__('Delete note')" />
            </div>
        @endforeach
    </div>
</div>
