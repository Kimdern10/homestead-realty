<?php

use App\Models\Review;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Reviews')] class extends Component {
    use WithPagination;

    #[Url(except: 'pending')]
    public string $status = Review::PENDING;

    /**
     * @return LengthAwarePaginator<int, Review>
     */
    #[Computed]
    public function reviews(): LengthAwarePaginator
    {
        return Review::query()
            ->with('agent:id,name,slug')
            ->when(in_array($this->status, [Review::PENDING, Review::APPROVED, Review::REJECTED], true), fn ($query) => $query->where('status', $this->status))
            ->latest()
            ->paginate(20);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        return Review::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn ($total) => (int) $total)->all();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /**
     * Show the review on the agent's page.
     */
    public function approve(int $id): void
    {
        Review::query()->whereKey($id)->update(['status' => Review::APPROVED]);
        unset($this->reviews, $this->counts);
        Flux::toast(__('Review approved. It now shows on the agent page.'), variant: 'success');
    }

    /**
     * Hide the review.
     */
    public function reject(int $id): void
    {
        Review::query()->whereKey($id)->update(['status' => Review::REJECTED]);
        unset($this->reviews, $this->counts);
        Flux::toast(__('Review hidden.'));
    }

    /**
     * Delete the review for good.
     */
    public function delete(int $id): void
    {
        Review::query()->whereKey($id)->delete();
        unset($this->reviews, $this->counts);
        Flux::toast(__('Review deleted.'));
    }
}; ?>

<div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Agent reviews') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Visitors leave reviews on agent pages. Nothing shows on the website until you approve it.') }}</flux:text>
    </div>

    <flux:radio.group wire:model.live="status" variant="segmented">
        <flux:radio value="pending" :label="__('Waiting').' ('.($this->counts['pending'] ?? 0).')'" />
        <flux:radio value="approved" :label="__('Approved').' ('.($this->counts['approved'] ?? 0).')'" />
        <flux:radio value="rejected" :label="__('Hidden').' ('.($this->counts['rejected'] ?? 0).')'" />
    </flux:radio.group>

    <div class="flex flex-col gap-3">
        @forelse ($this->reviews as $review)
            <div wire:key="review-{{ $review->id }}" class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="text-amber-500" aria-label="{{ $review->rating }} out of 5 stars">{{ str_repeat('★', $review->rating) }}<span class="text-zinc-300 dark:text-zinc-600">{{ str_repeat('★', 5 - $review->rating) }}</span></div>
                        <flux:heading class="mt-1">{{ $review->title ?: __('(no headline)') }}</flux:heading>
                        <flux:text class="text-sm">
                            {{ __('For') }} <a class="underline" href="{{ route('agents.show', $review->agent) }}" target="_blank">{{ $review->agent->name }}</a>
                            · {{ $review->name }} ({{ $review->email }}) · {{ $review->created_at->diffForHumans() }}
                        </flux:text>
                    </div>
                    <div class="flex gap-2">
                        @if ($review->status !== \App\Models\Review::APPROVED)
                            <flux:button size="sm" variant="primary" icon="check" wire:click="approve({{ $review->id }})">{{ __('Approve') }}</flux:button>
                        @endif
                        @if ($review->status !== \App\Models\Review::REJECTED)
                            <flux:button size="sm" icon="eye-slash" wire:click="reject({{ $review->id }})">{{ __('Hide') }}</flux:button>
                        @endif
                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $review->id }})" wire:confirm="{{ __('Delete this review for good?') }}" :aria-label="__('Delete')" />
                    </div>
                </div>
                <flux:text class="mt-3 whitespace-pre-line text-zinc-700! dark:text-zinc-200!">{{ $review->body }}</flux:text>
            </div>
        @empty
            <flux:callout icon="star">{{ __('Nothing here.') }}</flux:callout>
        @endforelse
    </div>

    {{ $this->reviews->links() }}
</div>
