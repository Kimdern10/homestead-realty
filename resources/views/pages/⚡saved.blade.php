<?php

use App\Models\Listing;
use App\Models\SavedSearch;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    /**
     * Homes the visitor saved, newest first.
     *
     * @return Collection<int, Listing>
     */
    #[Computed]
    public function homes(): Collection
    {
        return auth()->user()->favorites()->published()->forCards()->orderByPivot('created_at', 'desc')->get();
    }

    /**
     * Searches the visitor saved, newest first.
     *
     * @return Collection<int, SavedSearch>
     */
    #[Computed]
    public function searches(): Collection
    {
        return auth()->user()->savedSearches()->latest()->get();
    }

    /**
     * Forget a saved search.
     */
    public function deleteSearch(int $id): void
    {
        auth()->user()->savedSearches()->whereKey($id)->delete();
        unset($this->searches);
    }

    /**
     * Remove a home from the saved list.
     */
    public function removeHome(int $id): void
    {
        auth()->user()->favorites()->detach($id);
        unset($this->homes);
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts::site', ['current' => 'saved', 'noindex' => true])
            ->title('Saved homes and searches');
    }
}; ?>

<div>
    <div class="page-head">
        <div class="container">
            <h1>Saved homes</h1>
            <p>Homes and searches you've saved, {{ Str::before(auth()->user()->name, ' ') }}. Tap the heart on any home to add it here.</p>
        </div>
    </div>

    <section class="section">
        <div class="container">
            @if ($this->homes->isEmpty())
                <div class="empty">
                    <h2>No saved homes yet</h2>
                    <p>When you find a home you like, tap the heart to keep it here.</p>
                    <a class="btn btn--primary" href="{{ route('search') }}"><x-icon name="search" /> Start searching</a>
                </div>
            @else
                <div class="grid grid--cards">
                    @foreach ($this->homes as $listing)
                        <div wire:key="home-{{ $listing->id }}" style="display: grid; gap: 8px">
                            <x-listing-card :listing="$listing" />
                            <button class="btn btn--ghost btn--sm" type="button" wire:click="removeHome({{ $listing->id }})"><x-icon name="trash" /> Remove</button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="section section--soft">
        <div class="container">
            <div class="section-head">
                <div>
                    <h2>Saved searches</h2>
                    <p>Run a saved search again to see what's new.</p>
                </div>
            </div>
            @if ($this->searches->isEmpty())
                <p class="muted">No saved searches yet. Choose your filters on the <a class="link" href="{{ route('search') }}">search page</a> and press “Save search”.</p>
            @else
                <div class="stack">
                    @foreach ($this->searches as $search)
                        <div class="saved-search" wire:key="search-{{ $search->id }}">
                            <div>
                                <h3>{{ $search->name }}</h3>
                                <p>{{ $search->summary() }} · Saved {{ $search->created_at->diffForHumans() }}</p>
                            </div>
                            <div class="saved-search__actions">
                                <a class="btn btn--primary btn--sm" href="{{ $search->url() }}">See homes</a>
                                <button class="btn btn--outline btn--sm" type="button" wire:click="deleteSearch({{ $search->id }})" wire:confirm="Delete this saved search?" aria-label="Delete saved search {{ $search->name }}"><x-icon name="trash" /></button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</div>
