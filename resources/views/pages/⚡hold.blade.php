<?php

use App\Enums\ListingStatus;
use App\Enums\PaymentPurpose;
use App\Models\Listing;
use App\Services\Payments\PaymentManager;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public Listing $listing;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public bool $agree = false;

    /**
     * A holding deposit is only for an available rental, and only when payments are on.
     */
    public function mount(Listing $listing, PaymentManager $payments): void
    {
        abort_unless(
            $listing->is_published && $listing->agent->is_active && $listing->status === ListingStatus::ForRent && $payments->offers(PaymentPurpose::HoldingDeposit),
            404,
        );

        if ($user = auth()->user()) {
            $this->name = $user->name;
            $this->email = $user->email;
        }
    }

    /**
     * Send the renter to pay the deposit.
     */
    public function pay(PaymentManager $payments): void
    {
        $limiterKey = 'holding-deposit:'.request()->ip();

        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            $this->addError('form', 'Please try again in a little while.');

            return;
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9(][0-9\s\-().]{6,24}$/'],
            'agree' => ['accepted'],
        ], [
            'phone.regex' => 'Please enter a valid phone number, e.g. (512) 555-0142.',
            'agree.accepted' => 'Please confirm you have read how the deposit works.',
        ]);

        RateLimiter::hit($limiterKey, 3600);

        try {
            $url = $payments->checkout(
                PaymentPurpose::HoldingDeposit,
                $payments->price(PaymentPurpose::HoldingDeposit),
                'Holding deposit for '.$this->listing->address,
                ['name' => $validated['name'], 'email' => $validated['email'], 'phone' => $validated['phone'], 'user_id' => auth()->id()],
                $this->listing,
            );
        } catch (RuntimeException $exception) {
            report($exception);
            $this->addError('form', $exception->getMessage());

            return;
        }

        $this->redirect($url);
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts::site', ['noindex' => true, 'current' => 'rent'])
            ->title('Hold '.$this->listing->address);
    }
}; ?>

<div>
    <div class="page-head">
        <div class="container">
            <nav class="crumbs" aria-label="Breadcrumb"><a href="{{ route('listings.show', $listing) }}">← Back to the home</a></nav>
            <h1>Hold this home</h1>
            <p>{{ $listing->fullAddress() }} · {{ $listing->priceLabel() }}</p>
        </div>
    </div>

    <section class="section">
        <div class="container" style="max-width: 640px">
            @php($payments = app(\App\Services\Payments\PaymentManager::class))
            <form class="panel form" wire:submit="pay" novalidate>
                <div class="hold-amount">
                    <span>Holding deposit</span>
                    <b>{{ $payments->money($payments->price(\App\Enums\PaymentPurpose::HoldingDeposit)) }}</b>
                </div>
                <ul class="hold-points">
                    <li><x-icon name="check" /> The home is marked <b>pending</b> and taken off the market while your application is checked.</li>
                    <li><x-icon name="check" /> {{ Str::before($listing->agent->name, ' ') }} will contact you about the lease and next steps.</li>
                    <li><x-icon name="check" /> The deposit usually goes towards your first month's rent or security deposit. Confirm the terms with the agent.</li>
                </ul>

                <label>Full name<input class="input" wire:model="name" autocomplete="name" @error('name') aria-invalid="true" @enderror>@error('name')<span class="field-error">{{ $message }}</span>@enderror</label>
                <div class="row2">
                    <label>Email<input class="input" type="email" wire:model="email" autocomplete="email" @error('email') aria-invalid="true" @enderror>@error('email')<span class="field-error">{{ $message }}</span>@enderror</label>
                    <label>Phone<input class="input" type="tel" wire:model="phone" autocomplete="tel" @error('phone') aria-invalid="true" @enderror>@error('phone')<span class="field-error">{{ $message }}</span>@enderror</label>
                </div>
                <label class="check"><input type="checkbox" wire:model="agree"> I understand how the holding deposit works.</label>
                @error('agree')<span class="field-error">{{ $message }}</span>@enderror
                @error('form')<p class="field-error" role="alert">{{ $message }}</p>@enderror

                <button class="btn btn--primary btn--block" type="submit" wire:loading.attr="disabled" wire:target="pay">
                    <span wire:loading.remove wire:target="pay"><x-icon name="shield" /> Pay securely</span>
                    <span wire:loading wire:target="pay">Please wait…</span>
                </button>
                <p class="form-note">You'll pay on {{ ucfirst(\App\Models\Setting::get('payment_gateway')) }}'s secure page. Card details never touch this website.</p>
            </form>
        </div>
    </section>
</div>
