<?php

use App\Enums\ApplicationStatus;
use App\Enums\PaymentPurpose;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Services\Payments\PaymentManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public ?int $serviceId = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public string $preferred_date = '';

    public string $notes = '';

    /**
     * Prefill what we know about a signed-in visitor.
     */
    public function mount(): void
    {
        if ($user = auth()->user()) {
            $this->name = $user->name;
            $this->email = $user->email;
        }
    }

    /**
     * @return Collection<int, Service>
     */
    #[Computed]
    public function services(): Collection
    {
        return Service::query()->bookable()->get();
    }

    /**
     * Open the booking form for a service.
     */
    public function choose(int $id): void
    {
        $this->serviceId = $this->services->firstWhere('id', $id)?->id;
        $this->resetValidation();
    }

    /**
     * Save the booking and send the visitor to pay for it.
     */
    public function book(PaymentManager $payments): void
    {
        $limiterKey = 'service-booking:'.request()->ip();

        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            $this->addError('form', 'Please try again in a little while.');

            return;
        }

        $validated = $this->validate([
            'serviceId' => ['required', Rule::exists('services', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9(][0-9\s\-().]{6,24}$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'preferred_date' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['phone.regex' => 'Please enter a valid phone number, e.g. (512) 555-0142.']);

        RateLimiter::hit($limiterKey, 3600);

        $service = Service::query()->findOrFail($validated['serviceId']);

        $booking = ServiceBooking::query()->create([
            'service_id' => $service->id,
            'user_id' => auth()->id(),
            'service_name' => $service->name,
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'phone' => $validated['phone'],
            'address' => $validated['address'] ?: null,
            'preferred_date' => $validated['preferred_date'] ?: null,
            'notes' => $validated['notes'] ?: null,
            'status' => ApplicationStatus::AwaitingPayment,
        ]);

        try {
            $url = $payments->checkout(
                PaymentPurpose::Service,
                $service->price,
                $service->name,
                ['name' => $validated['name'], 'email' => $validated['email'], 'phone' => $validated['phone'], 'user_id' => auth()->id()],
                null,
                $booking,
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
        return $this->view()->layout('layouts::site', ['current' => 'services'])->title('Services');
    }
}; ?>

<div>
    <div class="page-head">
        <div class="container">
            <h1>Services</h1>
            <p>Book an expert from our team and pay online in a few clicks.</p>
        </div>
    </div>

    <section class="section">
        <div class="container">
            @php($payments = app(\App\Services\Payments\PaymentManager::class))
            @if ($this->services->isEmpty() || ! $payments->isEnabled())
                <div class="empty">
                    <h2>No services to book online right now</h2>
                    <p>Please call or message us and we'll help you directly.</p>
                    <a class="btn btn--primary" href="{{ route('agents.index') }}">Talk to an agent</a>
                </div>
            @else
                <div class="grid grid--agents" data-stagger>
                    @foreach ($this->services as $service)
                        <div class="service-card" wire:key="service-{{ $service->id }}">
                            <h3>{{ $service->name }}</h3>
                            @if ($service->description)<p>{{ $service->description }}</p>@endif
                            <div class="service-card__foot">
                                <b>{{ $payments->money($service->price) }}</b>
                                <button class="btn btn--primary btn--sm" type="button" wire:click="choose({{ $service->id }})">Book</button>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($serviceId)
                    @php($chosen = $this->services->firstWhere('id', $serviceId))
                    <form class="panel form" wire:submit="book" novalidate style="max-width: 640px; margin: 32px auto 0" id="book" x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'start' })">
                        <h2 style="font-size: 24px">Book: {{ $chosen->name }} · {{ $payments->money($chosen->price) }}</h2>
                        <label>Full name<input class="input" wire:model="name" autocomplete="name" @error('name') aria-invalid="true" @enderror>@error('name')<span class="field-error">{{ $message }}</span>@enderror</label>
                        <div class="row2">
                            <label>Email<input class="input" type="email" wire:model="email" autocomplete="email" @error('email') aria-invalid="true" @enderror>@error('email')<span class="field-error">{{ $message }}</span>@enderror</label>
                            <label>Phone<input class="input" type="tel" wire:model="phone" autocomplete="tel" @error('phone') aria-invalid="true" @enderror>@error('phone')<span class="field-error">{{ $message }}</span>@enderror</label>
                        </div>
                        <div class="row2">
                            <label><span>Property address <small>(optional)</small></span><input class="input" wire:model="address" autocomplete="street-address"></label>
                            <label><span>Preferred date <small>(optional)</small></span><input class="input" type="date" wire:model="preferred_date">@error('preferred_date')<span class="field-error">{{ $message }}</span>@enderror</label>
                        </div>
                        <label><span>Notes <small>(optional)</small></span><textarea class="textarea" rows="3" wire:model="notes"></textarea></label>
                        @error('form')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                        <button class="btn btn--primary btn--block" type="submit" wire:loading.attr="disabled" wire:target="book">
                            <span wire:loading.remove wire:target="book"><x-icon name="shield" /> Continue to payment</span>
                            <span wire:loading wire:target="book">Please wait…</span>
                        </button>
                        <p class="form-note">You'll pay on {{ ucfirst(\App\Models\Setting::get('payment_gateway')) }}'s secure page.</p>
                    </form>
                @endif
            @endif
        </div>
    </section>
</div>
