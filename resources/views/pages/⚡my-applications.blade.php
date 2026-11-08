<?php

use App\Enums\ApplicationStatus;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\RentalApplication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    /**
     * The visitor's rental applications, newest first. Applications sent before signing in
     * are matched by their (verified) email address.
     *
     * @return Collection<int, RentalApplication>
     */
    #[Computed]
    public function applications(): Collection
    {
        $user = auth()->user();

        return RentalApplication::query()
            ->where(fn (Builder $query) => $query->where('user_id', $user->id)->orWhere('email', strtolower($user->email)))
            ->with(['listing.city', 'listing.coverPhoto', 'listing.agent', 'payment', 'depositPayment'])
            ->latest()
            ->get();
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts::site', ['noindex' => true])
            ->title('My rental applications');
    }
}; ?>

<div>
    <div class="page-head">
        <div class="container">
            <h1>My rental applications</h1>
            <p>See where each application stands. When one is approved, you can pay the holding deposit here.</p>
        </div>
    </div>

    <section class="section">
        <div class="container" style="max-width: 860px">
            @forelse ($this->applications as $application)
                @php($payments = app(\App\Services\Payments\PaymentManager::class))
                <article class="my-app" wire:key="app-{{ $application->id }}" data-test="application-{{ $application->id }}">
                    <a class="my-app__photo" href="{{ route('listings.show', $application->listing) }}">
                        <img src="{{ $application->listing->coverUrl() ?? $application->listing->placeholderUrl() }}" alt="" width="240" height="160" loading="lazy">
                    </a>
                    <div class="my-app__body">
                        <span @class(['my-app__status', 'is-approved' => $application->status === ApplicationStatus::Approved, 'is-declined' => $application->status === ApplicationStatus::Declined])>{{ $application->status->applicantLabel() }}</span>
                        <h2><a href="{{ route('listings.show', $application->listing) }}">{{ $application->listing->address }}</a></h2>
                        <p class="muted">{{ $application->listing->locality() }} · {{ $application->listing->priceLabel() }} · Applied {{ $application->created_at->format('M j, Y') }}</p>

                        @if ($application->depositPayment)
                            <p class="pay-note"><x-icon name="check" /> Holding deposit of {{ $application->depositPayment->amountLabel() }} paid. {{ Str::before($application->listing->agent->name, ' ') }} will send you the lease.</p>
                        @elseif ($application->canPayDeposit())
                            <p class="pay-note"><x-icon name="check" /> Approved! Pay the holding deposit to take the home off the market for you.</p>
                            <a class="btn btn--primary" href="{{ route('listings.hold', $application->listing) }}" data-test="pay-deposit"><x-icon name="shield" /> Pay holding deposit · {{ $payments->money($payments->price(PaymentPurpose::HoldingDeposit)) }}</a>
                        @elseif ($application->status === ApplicationStatus::Approved)
                            <p class="pay-note"><x-icon name="check" /> Approved! {{ Str::before($application->listing->agent->name, ' ') }} will contact you about the lease and how to pay.</p>
                        @elseif ($application->status === ApplicationStatus::AwaitingPayment)
                            <p class="pay-note"><x-icon name="clock" /> The application fee wasn't paid, so the agent can't see this application yet.</p>
                            @if ($application->payment?->status === PaymentStatus::Pending && $application->payment->checkout_url)
                                <a class="btn btn--primary" href="{{ $application->payment->checkout_url }}" data-no-navigate>Finish paying the fee</a>
                            @else
                                <a class="btn btn--outline" href="{{ route('listings.apply', $application->listing) }}">Apply again</a>
                            @endif
                        @elseif ($application->status === ApplicationStatus::New)
                            <p class="muted">{{ Str::before($application->listing->agent->name, ' ') }} is reviewing it. We'll email you as soon as there's a decision.</p>
                        @else
                            <p class="muted">The landlord chose another applicant. <a class="link" href="{{ route('search', ['status' => 'for_rent', 'q' => $application->listing->city->name]) }}">See other homes for rent</a></p>
                        @endif
                    </div>
                </article>
            @empty
                <div class="empty">
                    <span class="empty__icon"><x-icon name="key" /></span>
                    <h2>No applications yet</h2>
                    <p>Find a home for rent and press “Apply to rent”. You'll be able to follow it here.</p>
                    <a class="btn btn--primary" href="{{ route('search', ['status' => 'for_rent']) }}">Browse homes for rent</a>
                </div>
            @endforelse
        </div>
    </section>
</div>
