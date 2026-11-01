<?php

use App\Enums\ApplicationStatus;
use App\Enums\ListingStatus;
use App\Enums\PaymentPurpose;
use App\Models\Listing;
use App\Models\RentalApplication;
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

    public string $move_in_date = '';

    public string $occupants = '1';

    public bool $has_pets = false;

    public string $employer = '';

    public string $job_title = '';

    public string $monthly_income = '';

    public string $current_address = '';

    public string $references = '';

    public string $message = '';

    public bool $agree = false;

    public bool $submitted = false;

    /**
     * Only homes for rent take applications.
     */
    public function mount(Listing $listing): void
    {
        abort_unless($listing->is_published && $listing->agent->is_active && $listing->status === ListingStatus::ForRent, 404);

        if ($user = auth()->user()) {
            $this->name = $user->name;
            $this->email = $user->email;
        }

        $this->move_in_date = today()->addDays(30)->toDateString();
    }

    /**
     * Get the validation rules for the form.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9(][0-9\s\-().]{6,24}$/'],
            'move_in_date' => ['required', 'date', 'after_or_equal:today'],
            'occupants' => ['required', 'integer', 'min:1', 'max:20'],
            'has_pets' => ['boolean'],
            'employer' => ['nullable', 'string', 'max:150'],
            'job_title' => ['nullable', 'string', 'max:150'],
            'monthly_income' => ['required', 'integer', 'min:0', 'max:10000000'],
            'current_address' => ['nullable', 'string', 'max:255'],
            'references' => ['nullable', 'string', 'max:2000'],
            'message' => ['nullable', 'string', 'max:2000'],
            'agree' => ['accepted'],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'phone.regex' => 'Please enter a valid phone number, e.g. (512) 555-0142.',
            'agree.accepted' => 'Please confirm the information is true.',
            'move_in_date.after_or_equal' => 'Choose today or a later date.',
        ];
    }

    /**
     * Save the application, then send the renter to pay the fee (if there is one).
     */
    public function submit(PaymentManager $payments): void
    {
        $this->monthly_income = preg_replace('/[^0-9]/', '', $this->monthly_income) ?? '';

        $limiterKey = 'rental-application:'.request()->ip();

        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            $this->addError('form', 'You have sent several applications already. Please try again later.');

            return;
        }

        $validated = $this->validate();
        RateLimiter::hit($limiterKey, 3600);

        $needsPayment = $payments->offers(PaymentPurpose::ApplicationFee);

        $application = RentalApplication::query()->create([
            ...collect($validated)->except('agree')->all(),
            'listing_id' => $this->listing->id,
            'user_id' => auth()->id(),
            'email' => strtolower($validated['email']),
            'status' => $needsPayment ? ApplicationStatus::AwaitingPayment : ApplicationStatus::New,
        ]);

        if (! $needsPayment) {
            $this->submitted = true;

            return;
        }

        try {
            $url = $payments->checkout(
                PaymentPurpose::ApplicationFee,
                $payments->price(PaymentPurpose::ApplicationFee),
                'Rental application for '.$this->listing->address,
                ['name' => $validated['name'], 'email' => $validated['email'], 'phone' => $validated['phone'], 'user_id' => auth()->id()],
                $this->listing,
                $application,
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
            ->title('Apply to rent '.$this->listing->address);
    }
}; ?>

<div>
    <div class="page-head">
        <div class="container">
            <nav class="crumbs" aria-label="Breadcrumb"><a href="{{ route('listings.show', $listing) }}">← Back to the home</a></nav>
            <h1>Apply to rent</h1>
            <p>{{ $listing->fullAddress() }} · {{ $listing->priceLabel() }}</p>
        </div>
    </div>

    <section class="section">
        <div class="container" style="max-width: 760px">
            @if ($submitted)
                <div class="panel sent" role="status">
                    <span class="sent__icon"><x-icon name="check" /></span>
                    <strong>Application sent</strong>
                    <span>{{ Str::before($listing->agent->name, ' ') }} will review it and contact you at {{ $email }}.</span>
                    <a class="btn btn--outline btn--sm" href="{{ route('listings.show', $listing) }}">Back to the home</a>
                </div>
            @else
                @php($fee = app(\App\Services\Payments\PaymentManager::class))
                <form class="panel form" wire:submit="submit" novalidate>
                    @if ($fee->offers(\App\Enums\PaymentPurpose::ApplicationFee))
                        <p class="pay-note"><x-icon name="shield" /> After this form you'll pay the <b>{{ $fee->money($fee->price(\App\Enums\PaymentPurpose::ApplicationFee)) }}</b> application fee securely on {{ ucfirst(\App\Models\Setting::get('payment_gateway')) }}'s payment page. Your card details never touch this website.</p>
                    @endif

                    <h2 class="form-section">About you</h2>
                    <label>Full name<input class="input" wire:model="name" autocomplete="name" @error('name') aria-invalid="true" @enderror>@error('name')<span class="field-error">{{ $message }}</span>@enderror</label>
                    <div class="row2">
                        <label>Email<input class="input" type="email" wire:model="email" autocomplete="email" @error('email') aria-invalid="true" @enderror>@error('email')<span class="field-error">{{ $message }}</span>@enderror</label>
                        <label>Phone<input class="input" type="tel" wire:model="phone" autocomplete="tel" @error('phone') aria-invalid="true" @enderror>@error('phone')<span class="field-error">{{ $message }}</span>@enderror</label>
                    </div>
                    <label><span>Current address <small>(optional)</small></span><input class="input" wire:model="current_address" autocomplete="street-address"></label>

                    <h2 class="form-section">The move</h2>
                    <div class="row2">
                        <label>Move-in date<input class="input" type="date" wire:model="move_in_date" @error('move_in_date') aria-invalid="true" @enderror>@error('move_in_date')<span class="field-error">{{ $message }}</span>@enderror</label>
                        <label>People living there<input class="input" type="number" min="1" wire:model="occupants">@error('occupants')<span class="field-error">{{ $message }}</span>@enderror</label>
                    </div>
                    <label class="check"><input type="checkbox" wire:model="has_pets"> I have pets</label>

                    <h2 class="form-section">Work and income</h2>
                    <div class="row2">
                        <label><span>Employer <small>(optional)</small></span><input class="input" wire:model="employer" autocomplete="organization"></label>
                        <label><span>Job title <small>(optional)</small></span><input class="input" wire:model="job_title" autocomplete="organization-title"></label>
                    </div>
                    <label>Monthly income before tax ($)<input class="input" inputmode="numeric" wire:model="monthly_income" placeholder="6500" @error('monthly_income') aria-invalid="true" @enderror>@error('monthly_income')<span class="field-error">{{ $message }}</span>@enderror</label>
                    <label><span>References <small>(optional: past landlords or employers with phone numbers)</small></span><textarea class="textarea" rows="3" wire:model="references"></textarea></label>
                    <label><span>Anything else? <small>(optional)</small></span><textarea class="textarea" rows="3" wire:model="message"></textarea></label>

                    <label class="check"><input type="checkbox" wire:model="agree"> I confirm this information is true and agree that the agent may verify it.</label>
                    @error('agree')<span class="field-error">{{ $message }}</span>@enderror
                    @error('form')<p class="field-error" role="alert">{{ $message }}</p>@enderror

                    <button class="btn btn--primary btn--block" type="submit" wire:loading.attr="disabled" wire:target="submit">
                        <span wire:loading.remove wire:target="submit">{{ $fee->offers(\App\Enums\PaymentPurpose::ApplicationFee) ? 'Continue to payment' : 'Send application' }}</span>
                        <span wire:loading wire:target="submit">Please wait…</span>
                    </button>
                    <p class="form-note">We use your details only to process this application. See our <a href="{{ route('privacy') }}">privacy policy</a>.</p>
                </form>
            @endif
        </div>
    </section>
</div>
