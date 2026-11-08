<?php

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Models\Enquiry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Leads')] class extends Component {
    use WithPagination;

    #[Url(except: 'list')]
    public string $view = 'list';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: false)]
    public bool $due = false;

    /**
     * Start again from page one when a filter changes.
     */
    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * Leads this person may see, with the chosen filters.
     *
     * @return Builder<Enquiry>
     */
    private function baseQuery(): Builder
    {
        return Enquiry::query()
            ->visibleTo(auth()->user())
            ->with(['listing.city', 'agent'])
            ->when(EnquiryType::tryFrom($this->type), fn (Builder $query, EnquiryType $type) => $query->where('type', $type))
            ->when($this->due, fn (Builder $query) => $query->whereDate('follow_up_on', '<=', today())->whereNotIn('status', [EnquiryStatus::Closed, EnquiryStatus::Lost]));
    }

    /**
     * @return LengthAwarePaginator<int, Enquiry>
     */
    #[Computed]
    public function enquiries(): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->when(EnquiryStatus::tryFrom($this->status), fn (Builder $query, EnquiryStatus $status) => $query->where('status', $status))
            ->latest()
            ->paginate(20);
    }

    /**
     * Leads grouped by stage for the board. Won and lost show the last 30 days only.
     *
     * @return Collection<string, Collection<int, Enquiry>>
     */
    #[Computed]
    public function board(): Collection
    {
        $leads = $this->baseQuery()
            ->where(fn (Builder $query) => $query->whereNotIn('status', [EnquiryStatus::Closed, EnquiryStatus::Lost])->orWhere('updated_at', '>=', now()->subDays(30)))
            ->orderByRaw('follow_up_on IS NULL, follow_up_on')
            ->latest()
            ->take(300)
            ->get()
            ->groupBy(fn (Enquiry $enquiry) => $enquiry->status->value);

        return collect(EnquiryStatus::cases())->mapWithKeys(fn (EnquiryStatus $stage) => [$stage->value => $leads->get($stage->value, collect())]);
    }

    /**
     * How many open leads have a follow-up due.
     */
    #[Computed]
    public function dueCount(): int
    {
        return Enquiry::query()->visibleTo(auth()->user())
            ->whereDate('follow_up_on', '<=', today())
            ->whereNotIn('status', [EnquiryStatus::Closed, EnquiryStatus::Lost])
            ->count();
    }

    /**
     * Move a lead one stage forward from the board.
     */
    public function advance(int $id): void
    {
        $enquiry = Enquiry::query()->visibleTo(auth()->user())->findOrFail($id);
        $this->authorize('update', $enquiry);

        if ($next = $enquiry->status->next()) {
            $enquiry->update(['status' => $next]);
        }

        unset($this->board, $this->enquiries);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Leads') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Messages, tour requests and home valuations from the website. Move each one along as you work it.') }}</flux:text>
        </div>
        <flux:radio.group wire:model.live="view" variant="segmented" size="sm">
            <flux:radio value="list" icon="list-bullet" :label="__('List')" />
            <flux:radio value="board" icon="view-columns" :label="__('Board')" />
        </flux:radio.group>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <div class="w-56">
            <flux:select wire:model.live="type" :aria-label="__('Type')">
                <flux:select.option value="">{{ __('All lead types') }}</flux:select.option>
                @foreach (\App\Enums\EnquiryType::cases() as $option)
                    <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        @if ($view === 'list')
            <div class="w-48">
                <flux:select wire:model.live="status" :aria-label="__('Stage')">
                    <flux:select.option value="">{{ __('Any stage') }}</flux:select.option>
                    @foreach (\App\Enums\EnquiryStatus::cases() as $option)
                        <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif
        <flux:checkbox wire:model.live="due" :label="__('Follow-up due').($this->dueCount ? ' ('.$this->dueCount.')' : '')" />
    </div>

    @if ($view === 'board')
        <div class="-mx-2 flex gap-3 overflow-x-auto px-2 pb-2">
            @foreach ($this->board as $stageValue => $leads)
                @php($stage = \App\Enums\EnquiryStatus::from($stageValue))
                <div wire:key="col-{{ $stageValue }}" class="flex w-64 shrink-0 flex-col gap-2 rounded-xl bg-zinc-50 p-2 dark:bg-zinc-800/50">
                    <div class="flex items-center justify-between px-1">
                        <flux:badge size="sm" :color="$stage->color()">{{ $stage->label() }}</flux:badge>
                        <span class="text-xs text-zinc-500">{{ $leads->count() }}</span>
                    </div>
                    @foreach ($leads as $enquiry)
                        <div wire:key="card-{{ $enquiry->id }}" class="rounded-lg border border-zinc-200 bg-white p-3 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <a href="{{ route('dashboard.enquiries.show', $enquiry) }}" wire:navigate class="font-semibold hover:underline">{{ $enquiry->name }}</a>
                            <div class="text-xs text-zinc-500">{{ $enquiry->type->label() }}{{ $enquiry->listing ? ' · '.$enquiry->listing->address : '' }}</div>
                            @if ($enquiry->follow_up_on)
                                <div @class(['mt-1 text-xs', 'font-semibold text-red-600' => $enquiry->followUpDue(), 'text-zinc-500' => ! $enquiry->followUpDue()])>
                                    {{ __('Follow up') }} {{ $enquiry->follow_up_on->isToday() ? __('today') : $enquiry->follow_up_on->format('M j') }}
                                </div>
                            @endif
                            @if ($next = $stage->next())
                                <flux:button size="xs" variant="ghost" class="mt-2" icon-trailing="arrow-right" wire:click="advance({{ $enquiry->id }})">{{ $next->label() }}</flux:button>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @elseif ($this->enquiries->isEmpty())
        <flux:callout icon="inbox">{{ __('Nothing here yet. When a visitor contacts an agent, asks for a tour or values their home, it shows up here.') }}</flux:callout>
    @else
        <div class="grid gap-2">
            @foreach ($this->enquiries as $enquiry)
                <a href="{{ route('dashboard.enquiries.show', $enquiry) }}" wire:navigate wire:key="enquiry-{{ $enquiry->id }}" class="flex flex-wrap items-start gap-3 rounded-xl border border-zinc-200 p-3 hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500">
                    <flux:icon :name="match ($enquiry->type) { \App\Enums\EnquiryType::Tour => 'calendar-days', \App\Enums\EnquiryType::Valuation => 'currency-dollar', default => 'chat-bubble-left-ellipsis' }" class="mt-0.5 size-5 text-zinc-400" />
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold">{{ $enquiry->name }}</span>
                            <flux:badge size="sm" :color="$enquiry->status->color()">{{ $enquiry->status->label() }}</flux:badge>
                            <flux:badge size="sm" color="zinc">{{ $enquiry->type->label() }}</flux:badge>
                            @if ($enquiry->followUpDue())
                                <flux:badge size="sm" color="red" icon="bell">{{ __('Follow up') }}</flux:badge>
                            @endif
                        </div>
                        @if ($tour = $enquiry->tourSummary())
                            <div class="text-sm font-medium">{{ $tour }}</div>
                        @endif
                        <div class="truncate text-sm text-zinc-500">{{ Str::limit($enquiry->message, 120) }}</div>
                        <div class="text-xs text-zinc-400">
                            {{ $enquiry->listing?->address ?? ($enquiry->type === \App\Enums\EnquiryType::Valuation ? __('Possible seller') : __('General message')) }}
                            @if (auth()->user()->isAdmin() && $enquiry->agent) · {{ __('to') }} {{ $enquiry->agent->name }} @endif
                            @if ($enquiry->source) · {{ __('came from') }} {{ $enquiry->source }} @endif
                        </div>
                    </div>
                    <flux:text class="text-sm">{{ $enquiry->created_at->diffForHumans() }}</flux:text>
                </a>
            @endforeach
        </div>

        <flux:pagination :paginator="$this->enquiries" />
    @endif
</div>
