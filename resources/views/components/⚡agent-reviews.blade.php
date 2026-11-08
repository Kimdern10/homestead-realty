<?php

use App\Models\Agent;
use App\Models\Review;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public Agent $agent;

    public bool $writing = false;

    public int $rating = 5;

    public string $title = '';

    public string $body = '';

    public string $name = '';

    public string $email = '';

    /**
     * Hidden from people; spam bots fill it in.
     */
    public string $website = '';

    public bool $sent = false;

    public int $shown = 5;

    public function mount(): void
    {
        if ($user = auth()->user()) {
            $this->name = $user->name;
            $this->email = $user->email;
        }
    }

    /**
     * Approved reviews, newest first.
     *
     * @return Paginator<int, Review>
     */
    #[Computed]
    public function reviews(): Paginator
    {
        return $this->agent->reviews()->approved()->latest()->simplePaginate($this->shown);
    }

    /**
     * Show more reviews.
     */
    public function more(): void
    {
        $this->shown += 10;
        unset($this->reviews);
    }

    /**
     * Save the review for the admin to approve.
     */
    public function submit(): void
    {
        $limiterKey = 'review:'.request()->ip();

        if (RateLimiter::tooManyAttempts($limiterKey, 3)) {
            $this->addError('form', 'You have sent several reviews already. Please try again later.');

            return;
        }

        $validated = $this->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:100'],
            'body' => ['required', 'string', 'min:20', 'max:2000'],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
        ], [
            'body.required' => 'Please tell others about your experience.',
            'body.min' => 'Please write a little more (at least 20 characters).',
            'name.required' => 'Please add your name. Only your first name and initial are shown.',
            'email.required' => 'Please add your email. It is never shown.',
        ]);

        RateLimiter::hit($limiterKey, 3600);

        if (blank($this->website)) {
            $this->agent->reviews()->create([
                ...$validated,
                'email' => strtolower($validated['email']),
                'user_id' => auth()->id(),
                'status' => Review::PENDING,
                'ip_address' => request()->ip(),
            ]);
        }

        $this->sent = true;
        $this->writing = false;
        $this->reset('title', 'body');
    }
}; ?>

<div>
    @php($summary = $agent->ratingSummary())
    <div class="reviews-head">
        <div>
            <h2>Reviews</h2>
            @if ($summary['count'] > 0)
                <p class="rating-line">
                    <x-stars :rating="$summary['average']" />
                    <b>{{ number_format($summary['average'], 1) }}</b>
                    <span class="muted">from {{ $summary['count'] }} {{ Str::plural('review', $summary['count']) }}</span>
                </p>
            @else
                <p class="muted">No reviews yet. Worked with {{ Str::before($agent->name, ' ') }}? Be the first.</p>
            @endif
        </div>
        @unless ($writing || $sent)
            <button class="btn btn--outline btn--sm" type="button" wire:click="$set('writing', true)"><x-icon name="pencil" /> Write a review</button>
        @endunless
    </div>

    @if ($sent)
        <div class="sent" role="status" style="justify-items: start; text-align: left; padding: 12px 0">
            <strong>Thank you for your review!</strong>
            <span>We check every review before it appears, usually within a day.</span>
        </div>
    @endif

    @if ($writing)
        <form class="form review-form" wire:submit="submit" novalidate>
            @error('form') <p class="field-error" role="alert">{{ $message }}</p> @enderror
            <fieldset class="star-input">
                <legend>Your rating</legend>
                @foreach (range(1, 5) as $star)
                    <label wire:key="star-{{ $star }}">
                        <input type="radio" name="rating" value="{{ $star }}" wire:model.live="rating">
                        <span @class(['is-on' => $star <= $rating]) aria-hidden="true">★</span>
                        <span class="sr-only">{{ $star }} {{ Str::plural('star', $star) }}</span>
                    </label>
                @endforeach
            </fieldset>
            <label>Headline <small>(optional)</small>
                <input class="input" wire:model="title" maxlength="100" placeholder="e.g. Found us the perfect first home">
            </label>
            <label>Your review
                <textarea class="textarea" wire:model="body" rows="4" maxlength="2000" @error('body') aria-invalid="true" @enderror></textarea>
                @error('body') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <div class="row2">
                <label>Name
                    <input class="input" wire:model="name" autocomplete="name" @error('name') aria-invalid="true" @enderror>
                    @error('name') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label>Email <small>(not shown)</small>
                    <input class="input" type="email" wire:model="email" autocomplete="email" @error('email') aria-invalid="true" @enderror>
                    @error('email') <span class="field-error">{{ $message }}</span> @enderror
                </label>
            </div>
            <div class="hp" aria-hidden="true"><label>Website <input tabindex="-1" autocomplete="off" wire:model="website"></label></div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap">
                <button class="btn btn--primary" type="submit">Send review</button>
                <button class="btn btn--ghost" type="button" wire:click="$set('writing', false)">Cancel</button>
            </div>
        </form>
    @endif

    @if ($summary['count'] > 0)
        <ul class="reviews">
            @foreach ($this->reviews as $review)
                <li wire:key="review-{{ $review->id }}">
                    <div class="reviews__top">
                        <x-stars :rating="$review->rating" />
                        <span class="muted">{{ $review->publicName() }} · {{ $review->created_at->format('M Y') }}</span>
                    </div>
                    @if ($review->title)<h3>{{ $review->title }}</h3>@endif
                    <p>{{ $review->body }}</p>
                </li>
            @endforeach
        </ul>
        @if ($this->reviews->hasMorePages())
            <button class="btn btn--ghost btn--sm" type="button" wire:click="more">Show more reviews</button>
        @endif
    @endif
</div>
