<?php

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Models\Enquiry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Enquiries & tours')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $status = '';

    /**
     * Start again from page one when a filter changes.
     */
    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Enquiry>
     */
    #[Computed]
    public function enquiries(): LengthAwarePaginator
    {
        return Enquiry::query()
            ->visibleTo(auth()->user())
            ->with(['listing.city', 'agent'])
            ->when(EnquiryType::tryFrom($this->type), fn (Builder $query, EnquiryType $type) => $query->where('type', $type))
            ->when(EnquiryStatus::tryFrom($this->status), fn (Builder $query, EnquiryStatus $status) => $query->where('status', $status))
            ->latest()
            ->paginate(20);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Enquiries & tours') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Messages and tour requests sent from the website. Each one is also emailed to the agent.') }}</flux:text>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:max-w-xl">
        <flux:select wire:model.live="type" :aria-label="__('Type')">
            <flux:select.option value="">{{ __('Messages and tours') }}</flux:select.option>
            <flux:select.option value="message">{{ __('Messages only') }}</flux:select.option>
            <flux:select.option value="tour">{{ __('Tour requests only') }}</flux:select.option>
        </flux:select>
        <flux:select wire:model.live="status" :aria-label="__('Status')">
            <flux:select.option value="">{{ __('Any status') }}</flux:select.option>
            @foreach (\App\Enums\EnquiryStatus::cases() as $option)
                <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if ($this->enquiries->isEmpty())
        <flux:callout icon="inbox">{{ __('Nothing here yet. When a visitor contacts an agent or asks for a tour, it shows up here.') }}</flux:callout>
    @else
        <div class="grid gap-2">
            @foreach ($this->enquiries as $enquiry)
                <a href="{{ route('dashboard.enquiries.show', $enquiry) }}" wire:navigate wire:key="enquiry-{{ $enquiry->id }}" class="flex flex-wrap items-start gap-3 rounded-xl border border-zinc-200 p-3 hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500">
                    <flux:icon :name="$enquiry->type === \App\Enums\EnquiryType::Tour ? 'calendar-days' : 'chat-bubble-left-ellipsis'" class="mt-0.5 size-5 text-zinc-400" />
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold">{{ $enquiry->name }}</span>
                            <flux:badge size="sm" :color="$enquiry->status->color()">{{ $enquiry->status->label() }}</flux:badge>
                            <flux:badge size="sm" color="zinc">{{ $enquiry->type->label() }}</flux:badge>
                        </div>
                        @if ($tour = $enquiry->tourSummary())
                            <div class="text-sm font-medium">{{ $tour }}</div>
                        @endif
                        <div class="truncate text-sm text-zinc-500">{{ Str::limit($enquiry->message, 120) }}</div>
                        <div class="text-xs text-zinc-400">
                            {{ $enquiry->listing?->address ?? __('General message') }}
                            @if (auth()->user()->isAdmin() && $enquiry->agent) · {{ __('to') }} {{ $enquiry->agent->name }} @endif
                        </div>
                    </div>
                    <flux:text class="text-sm">{{ $enquiry->created_at->diffForHumans() }}</flux:text>
                </a>
            @endforeach
        </div>

        <flux:pagination :paginator="$this->enquiries" />
    @endif
</div>
