<?php

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Mail\EnquiryReceived;
use App\Models\Agent;
use App\Models\Enquiry;
use App\Models\Listing;
use App\Models\Setting;
use App\Services\HomeValueEstimator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    public string $address = '';

    public string $zip = '';

    public string $beds = '';

    public string $baths = '';

    public string $sqft = '';

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    /**
     * Hidden from people; spam bots fill it in.
     */
    public string $website = '';

    /**
     * @var array{price: int, low: int, high: int, source: string, comparables: int}|null
     */
    #[Locked]
    public ?array $result = null;

    #[Locked]
    public bool $done = false;

    #[Locked]
    public ?string $agentName = null;

    public function mount(): void
    {
        if ($user = auth()->user()) {
            $this->name = $user->name;
            $this->email = $user->email;
        }
    }

    /**
     * Work out the estimate, save the lead and tell the agent.
     */
    public function submit(HomeValueEstimator $estimator): void
    {
        $limiterKey = 'home-value:'.request()->ip();

        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            $this->addError('form', 'You have asked for several estimates already. Please try again in a little while.');

            return;
        }

        $validated = $this->validate([
            'address' => ['required', 'string', 'max:150'],
            'zip' => ['required', 'regex:/^\d{5}$/'],
            'beds' => ['nullable', 'integer', 'between:0,20'],
            'baths' => ['nullable', 'numeric', 'between:0,20'],
            'sqft' => ['nullable', 'integer', 'between:200,30000'],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9(][0-9\s\-().]{6,24}$/'],
        ], [
            'address.required' => 'Please enter the street address.',
            'zip.regex' => 'Please enter a 5-digit ZIP code.',
            'email.required' => 'We need your email to send the estimate.',
            'phone.regex' => 'Please enter a valid phone number.',
        ]);

        RateLimiter::hit($limiterKey, 600);

        if (filled($this->website)) {
            $this->done = true;

            return;
        }

        $home = [
            'address' => $validated['address'],
            'zip' => $validated['zip'],
            'beds' => $validated['beds'] !== '' && $validated['beds'] !== null ? (int) $validated['beds'] : null,
            'baths' => $validated['baths'] !== '' && $validated['baths'] !== null ? (float) $validated['baths'] : null,
            'sqft' => $validated['sqft'] !== '' && $validated['sqft'] !== null ? (int) $validated['sqft'] : null,
        ];

        $this->result = $estimator->estimate($home);
        $agent = $this->agentFor($home['zip']);
        $this->agentName = $agent?->name;

        $facts = implode(', ', array_filter([
            $home['beds'] !== null ? $home['beds'].' beds' : null,
            $home['baths'] !== null ? $home['baths'].' baths' : null,
            $home['sqft'] !== null ? number_format($home['sqft']).' sqft' : null,
        ]));

        $enquiry = Enquiry::query()->create([
            'type' => EnquiryType::Valuation,
            'status' => EnquiryStatus::New,
            'agent_id' => $agent?->id,
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'phone' => $validated['phone'] ?: null,
            'message' => "Home value request for {$home['address']}, {$home['zip']}".($facts ? " ({$facts})" : '').'. '
                .($this->result ? 'Online estimate shown: $'.number_format($this->result['price']).' (range $'.number_format($this->result['low']).' – $'.number_format($this->result['high']).').' : 'Not enough data for an online estimate; please prepare one.'),
            'ip_address' => request()->ip(),
            'source' => session('visit_source'),
        ]);

        if ($agent !== null) {
            try {
                Mail::to($agent->contactEmail())->send(new EnquiryReceived($enquiry));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        $this->done = true;
    }

    /**
     * The agent who covers this ZIP code, else the city, else the office's default agent.
     */
    private function agentFor(string $zip): ?Agent
    {
        $active = Agent::query()->active();
        $cityId = Listing::query()->where('zip', $zip)->value('city_id');

        return (clone $active)->get()->first(fn (Agent $agent) => in_array($zip, $agent->zipCodeList(), true))
            ?? ($cityId ? (clone $active)->whereHas('cities', fn ($query) => $query->whereKey($cityId))->orderBy('id')->first() : null)
            ?? (clone $active)->whereKey((int) Setting::get('default_agent_id'))->first()
            ?? (clone $active)->orderBy('id')->first();
    }

    /**
     * Start over for another address.
     */
    public function again(): void
    {
        $this->reset('address', 'zip', 'beds', 'baths', 'sqft', 'result', 'done', 'agentName');
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts::site', ['description' => 'Find out what your home is worth. A free instant estimate, plus a detailed valuation from a local agent.'])
            ->title("What's my home worth?");
    }
}; ?>

<div>
    <div class="page-head">
        <div class="container">
            <h1>What's my home worth?</h1>
            <p>Get a free estimate in under a minute, then a detailed valuation from a local agent who knows your street. No obligation.</p>
        </div>
    </div>

    <section class="section">
        <div class="container tool-layout">
            <div class="panel">
                @if ($done)
                    <div class="value-result" role="status">
                        @if ($result)
                            <p class="muted">Estimated value of {{ $address }}</p>
                            <div class="value-result__price" data-test="estimate">${{ number_format($result['price']) }}</div>
                            <p class="value-result__range">Likely range ${{ number_format($result['low']) }} – ${{ number_format($result['high']) }}</p>
                            <p class="muted" style="font-size: 14px">
                                {{ $result['source'] === 'rentcast' ? 'Based on recent sales and listings near you.' : 'Based on '.$result['comparables'].' similar homes on our website in your area.' }}
                                An online estimate can't see your upgrades, view or condition.
                            </p>
                        @else
                            <span class="sent__icon"><x-icon name="check" /></span>
                            <strong>Thanks, we've got it</strong>
                            <p class="muted">We don't have enough sales near {{ $zip }} for an instant number, so an agent will work it out by hand.</p>
                        @endif
                        <p style="margin-top: 14px">{{ $agentName ? Str::before($agentName, ' ') : 'A local agent' }} will email {{ $email }} with a detailed valuation, usually within one business day.</p>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap; justify-content: center; margin-top: 16px">
                            <a class="btn btn--primary" href="{{ route('sell') }}">List your home with us</a>
                            <button class="btn btn--outline" type="button" wire:click="again">Value another home</button>
                        </div>
                    </div>
                @else
                    <form class="form" wire:submit="submit" novalidate>
                        @error('form') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                        <label>Street address
                            <input class="input" wire:model="address" autocomplete="street-address" placeholder="e.g. 1408 Elm Street" @error('address') aria-invalid="true" @enderror>
                            @error('address') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <div class="row2">
                            <label>ZIP code
                                <input class="input" wire:model="zip" inputmode="numeric" maxlength="5" autocomplete="postal-code" placeholder="78704" @error('zip') aria-invalid="true" @enderror>
                                @error('zip') <span class="field-error">{{ $message }}</span> @enderror
                            </label>
                            <label>Size <small>(sqft, optional)</small>
                                <input class="input" wire:model="sqft" inputmode="numeric" placeholder="1,850">
                                @error('sqft') <span class="field-error">{{ $message }}</span> @enderror
                            </label>
                        </div>
                        <div class="row2">
                            <label>Bedrooms
                                <select class="select" wire:model="beds">
                                    <option value="">Not sure</option>
                                    @foreach (range(0, 6) as $count)<option value="{{ $count }}">{{ $count === 0 ? 'Studio' : $count }}</option>@endforeach
                                </select>
                            </label>
                            <label>Bathrooms
                                <select class="select" wire:model="baths">
                                    <option value="">Not sure</option>
                                    @foreach ([1, 1.5, 2, 2.5, 3, 3.5, 4, 5] as $count)<option value="{{ $count }}">{{ $count }}</option>@endforeach
                                </select>
                            </label>
                        </div>
                        <hr style="border: 0; border-top: 1px solid var(--line); margin: 4px 0">
                        <div class="row2">
                            <label>Your name
                                <input class="input" wire:model="name" autocomplete="name" @error('name') aria-invalid="true" @enderror>
                                @error('name') <span class="field-error">{{ $message }}</span> @enderror
                            </label>
                            <label>Email
                                <input class="input" type="email" wire:model="email" autocomplete="email" @error('email') aria-invalid="true" @enderror>
                                @error('email') <span class="field-error">{{ $message }}</span> @enderror
                            </label>
                        </div>
                        <label>Phone <small>(optional)</small>
                            <input class="input" type="tel" wire:model="phone" autocomplete="tel">
                            @error('phone') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <div class="hp" aria-hidden="true"><label>Website <input tabindex="-1" autocomplete="off" wire:model="website"></label></div>
                        <button class="btn btn--primary btn--block" type="submit" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="submit">Get my estimate</span>
                            <span wire:loading wire:target="submit">Working it out…</span>
                        </button>
                        <p class="form-note">We share your details only with our agent, who will contact you about a valuation. <a href="{{ route('privacy') }}">Privacy policy</a>.</p>
                    </form>
                @endif
            </div>
            <aside class="tool-aside">
                <h2>How it works</h2>
                <ol class="steps">
                    <li><b>Tell us about the home.</b> The address and a few basics.</li>
                    <li><b>See an instant estimate.</b> From recent sales and listings nearby.</li>
                    <li><b>Get the real number.</b> A local agent checks it and sends you a full valuation, free.</li>
                </ol>
                <p class="muted" style="margin-top: 16px; font-size: 14px">Want to sell without an agent? <a class="link" href="{{ route('sell') }}">List it yourself</a>.</p>
            </aside>
        </div>
    </section>
</div>
