<?php

use App\Mail\SubmissionApproved;
use App\Models\Listing;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Owner submissions')] class extends Component {
    use WithPagination;

    #[Url(except: 'waiting')]
    public string $show = 'waiting';

    public function updatedShow(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Listing>
     */
    #[Computed]
    public function submissions(): LengthAwarePaginator
    {
        return Listing::query()
            ->where('source', 'owner')
            ->where('is_published', $this->show === 'live')
            ->with(['city', 'agent', 'coverPhoto'])
            ->withCount('photos')
            ->latest()
            ->paginate(15);
    }

    /**
     * Put the home on the website and tell the owner.
     */
    public function approve(int $id): void
    {
        $listing = Listing::query()->where('source', 'owner')->findOrFail($id);
        $listing->update(['is_published' => true, 'listed_at' => today()]);

        try {
            if (filled($listing->owner_email)) {
                Mail::to($listing->owner_email)->send(new SubmissionApproved($listing));
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        unset($this->submissions);
        Flux::toast(__('Approved. The home is live and the owner has been emailed.'), variant: 'success');
    }

    /**
     * Turn the submission down: delete it and its photos.
     */
    public function decline(int $id): void
    {
        $listing = Listing::query()->where('source', 'owner')->where('is_published', false)->with('photos')->findOrFail($id);
        $paths = $listing->photos->flatMap(fn ($photo) => $photo->paths())->all();

        $listing->delete();
        Storage::disk('public')->delete($paths);

        unset($this->submissions);
        Flux::toast(__('Submission deleted.'));
    }
}; ?>

<div class="mx-auto flex w-full max-w-5xl flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Owner submissions') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Homes sent in by owners from the “List your home” page. Check the details, edit anything, then approve to put it on the website.') }}</flux:text>
    </div>

    <flux:radio.group wire:model.live="show" variant="segmented">
        <flux:radio value="waiting" :label="__('Waiting for approval')" />
        <flux:radio value="live" :label="__('Approved')" />
    </flux:radio.group>

    <div class="flex flex-col gap-3">
        @forelse ($this->submissions as $listing)
            <div wire:key="sub-{{ $listing->id }}" class="flex flex-wrap gap-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <img src="{{ $listing->coverUrl() ?? $listing->placeholderUrl() }}" alt="" class="aspect-[3/2] w-40 rounded-lg object-cover">
                <div class="min-w-0 flex-1">
                    <flux:heading>{{ $listing->address }}, {{ $listing->locality() }}</flux:heading>
                    <flux:text class="text-sm">{{ $listing->priceLabel() }} · {{ $listing->status->label() }} · {{ $listing->typeLabel() }}@if ($listing->facts()) · {{ implode(' · ', $listing->facts()) }}@endif · {{ $listing->photos_count }} {{ __('photos') }}</flux:text>
                    <flux:text class="mt-1 text-sm">
                        {{ __('Owner:') }} <b>{{ $listing->owner_name }}</b> ·
                        <a class="underline" href="mailto:{{ $listing->owner_email }}">{{ $listing->owner_email }}</a> ·
                        <a class="underline" href="tel:{{ preg_replace('/[^0-9+]/', '', (string) $listing->owner_phone) }}">{{ $listing->owner_phone }}</a>
                    </flux:text>
                    <flux:text class="text-sm">{{ __('Assigned to') }} {{ $listing->agent->name }} · {{ __('sent') }} {{ $listing->created_at->diffForHumans() }}</flux:text>
                    <flux:text class="mt-2 line-clamp-2 text-sm">{{ $listing->description }}</flux:text>
                </div>
                <div class="flex flex-wrap items-start gap-2">
                    <flux:button size="sm" icon="pencil" :href="route('dashboard.listings.edit', $listing)" wire:navigate>{{ __('Edit') }}</flux:button>
                    @if (! $listing->is_published)
                        <flux:button size="sm" variant="primary" icon="check" wire:click="approve({{ $listing->id }})">{{ __('Approve') }}</flux:button>
                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="decline({{ $listing->id }})" wire:confirm="{{ __('Delete this submission and its photos?') }}">{{ __('Decline') }}</flux:button>
                    @else
                        <flux:button size="sm" variant="ghost" icon="arrow-top-right-on-square" :href="route('listings.show', $listing)" target="_blank">{{ __('View') }}</flux:button>
                    @endif
                </div>
            </div>
        @empty
            <flux:callout icon="home">{{ $show === 'live' ? __('No approved owner homes yet.') : __('Nothing waiting. New homes owners send in show up here.') }}</flux:callout>
        @endforelse
    </div>

    {{ $this->submissions->links() }}
</div>
