<?php

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Enums\ListingStatus;
use App\Models\Enquiry;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Overview')] class extends Component {
    /**
     * Listings this user manages: all for admins, their own for agents.
     *
     * @return Builder<Listing>
     */
    private function listings(): Builder
    {
        $user = auth()->user();

        return Listing::query()->when(! $user->isAdmin(), fn (Builder $query) => $query->where('agent_id', $user->agent->id));
    }

    /**
     * The numbers on the cards at the top.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function stats(): array
    {
        $enquiries = Enquiry::query()->visibleTo(auth()->user());

        return [
            'for_sale' => $this->listings()->where('status', ListingStatus::ForSale)->count(),
            'for_rent' => $this->listings()->where('status', ListingStatus::ForRent)->count(),
            'new_enquiries' => (clone $enquiries)->where('status', EnquiryStatus::New)->count(),
            'upcoming_tours' => (clone $enquiries)->where('type', EnquiryType::Tour)->whereDate('tour_date', '>=', today())->where('status', '!=', EnquiryStatus::Closed)->count(),
            'views' => (int) $this->listings()->sum('views'),
            'saves' => (int) $this->listings()->withCount('savedBy')->get()->sum('saved_by_count'),
        ];
    }

    /**
     * The latest enquiries.
     *
     * @return Collection<int, Enquiry>
     */
    #[Computed]
    public function recentEnquiries(): Collection
    {
        return Enquiry::query()->visibleTo(auth()->user())->with('listing.city')->latest()->take(6)->get();
    }

    /**
     * Listings with the most views.
     *
     * @return Collection<int, Listing>
     */
    #[Computed]
    public function popular(): Collection
    {
        return $this->listings()->with(['city', 'coverPhoto'])->withCount('savedBy')->orderByDesc('views')->take(5)->get();
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Welcome back, :name', ['name' => Str::before(auth()->user()->name, ' ')]) }}</flux:heading>
            <flux:text class="mt-1">{{ auth()->user()->isAdmin() ? __('Here is how the whole office is doing.') : __('Here is how your listings are doing.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" :href="route('dashboard.listings.create')" wire:navigate>{{ __('Add a listing') }}</flux:button>
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-6">
        @foreach ([
            ['for_sale', __('For sale'), 'home', route('dashboard.listings.index', ['status' => 'for_sale'])],
            ['for_rent', __('For rent'), 'key', route('dashboard.listings.index', ['status' => 'for_rent'])],
            ['new_enquiries', __('New enquiries'), 'inbox', route('dashboard.enquiries.index', ['status' => 'new'])],
            ['upcoming_tours', __('Upcoming tours'), 'calendar-days', route('dashboard.enquiries.index', ['type' => 'tour'])],
            ['views', __('Listing views'), 'eye', null],
            ['saves', __('Times saved'), 'heart', null],
        ] as [$key, $label, $icon, $href])
            <a @if ($href) href="{{ $href }}" wire:navigate @endif class="rounded-xl border border-zinc-200 p-4 transition hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500">
                <flux:icon :name="$icon" class="size-5 text-[#d92228] dark:text-[#ff4a50]" />
                <div class="mt-3 text-2xl font-bold tracking-tight">{{ number_format($this->stats[$key]) }}</div>
                <flux:text class="text-sm">{{ $label }}</flux:text>
            </a>
        @endforeach
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="mb-3 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Latest enquiries') }}</flux:heading>
                <flux:link :href="route('dashboard.enquiries.index')" wire:navigate class="text-sm">{{ __('See all') }}</flux:link>
            </div>
            @forelse ($this->recentEnquiries as $enquiry)
                <a href="{{ route('dashboard.enquiries.show', $enquiry) }}" wire:navigate wire:key="enquiry-{{ $enquiry->id }}" class="-mx-2 flex items-start gap-3 rounded-lg px-2 py-2.5 hover:bg-zinc-50 dark:hover:bg-zinc-700/40">
                    <flux:icon :name="$enquiry->type === \App\Enums\EnquiryType::Tour ? 'calendar-days' : 'chat-bubble-left-ellipsis'" class="mt-0.5 size-5 text-zinc-400" />
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ $enquiry->name }}</span>
                            <flux:badge size="sm" :color="$enquiry->status->color()">{{ $enquiry->status->label() }}</flux:badge>
                        </div>
                        <div class="truncate text-sm text-zinc-500">{{ $enquiry->tourSummary() ?? Str::limit($enquiry->message, 80) }}</div>
                        <div class="truncate text-xs text-zinc-400">{{ $enquiry->listing?->address ?? __('General message') }} · {{ $enquiry->created_at->diffForHumans() }}</div>
                    </div>
                </a>
            @empty
                <flux:text>{{ __('No enquiries yet. They appear here when visitors contact you from a listing.') }}</flux:text>
            @endforelse
        </section>

        <section class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="mb-3 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Most viewed listings') }}</flux:heading>
                <flux:link :href="route('dashboard.listings.index')" wire:navigate class="text-sm">{{ __('All listings') }}</flux:link>
            </div>
            @forelse ($this->popular as $listing)
                <a href="{{ route('dashboard.listings.edit', $listing) }}" wire:navigate wire:key="popular-{{ $listing->id }}" class="-mx-2 flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-700/40">
                    @if ($cover = $listing->coverUrl())
                        <img src="{{ $cover }}" alt="" class="h-12 w-16 flex-none rounded-md object-cover">
                    @else
                        <div class="h-12 w-16 flex-none rounded-md bg-zinc-100 dark:bg-zinc-800"></div>
                    @endif
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium">{{ $listing->address }}</div>
                        <div class="text-sm text-zinc-500">{{ $listing->priceLabel() }} · {{ $listing->city->label() }}</div>
                    </div>
                    <div class="text-end text-sm text-zinc-500">
                        <div><flux:icon name="eye" variant="micro" class="inline" /> {{ number_format($listing->views) }}</div>
                        <div><flux:icon name="heart" variant="micro" class="inline" /> {{ $listing->saved_by_count }}</div>
                    </div>
                </a>
            @empty
                <flux:text>{{ __('No listings yet.') }}</flux:text>
            @endforelse
        </section>
    </div>
</div>
