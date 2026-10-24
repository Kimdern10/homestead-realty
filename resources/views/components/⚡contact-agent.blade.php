<?php

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Mail\EnquiryReceived;
use App\Models\Agent;
use App\Models\Enquiry;
use App\Models\Listing;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    /**
     * How many messages one visitor may send in ten minutes.
     */
    public const int MAX_ATTEMPTS = 5;

    /**
     * How many days ahead a visitor can book a tour.
     */
    public const int TOUR_DAYS = 14;

    #[Locked]
    public Agent $agent;

    #[Locked]
    public ?Listing $listing = null;

    #[Locked]
    public bool $allowTours = true;

    public string $mode = 'message';

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $message = '';

    public string $tour_date = '';

    public string $tour_time = 'morning';

    public string $tour_kind = 'in_person';

    /**
     * Hidden from people; spam bots fill it in.
     */
    public string $website = '';

    public bool $sent = false;

    /**
     * Prefill what we already know about a signed-in visitor.
     */
    public function mount(): void
    {
        if ($user = auth()->user()) {
            $this->name = $user->name;
            $this->email = $user->email;
        }

        $this->message = $this->listing
            ? "I'm interested in {$this->listing->address}. Please send me more information."
            : '';

        $this->tour_date = $this->tourDays[0]['value'];
        $this->mode = $this->allowTours && $this->listing ? 'tour' : 'message';
    }

    /**
     * The days a visitor can pick for a tour, starting tomorrow.
     *
     * @return list<array{value: string, weekday: string, day: string, month: string}>
     */
    #[Computed]
    public function tourDays(): array
    {
        return collect(range(1, self::TOUR_DAYS))
            ->map(fn (int $offset): CarbonImmutable => CarbonImmutable::today()->addDays($offset))
            ->map(fn (CarbonImmutable $date): array => [
                'value' => $date->toDateString(),
                'weekday' => $date->format('D'),
                'day' => $date->format('j'),
                'month' => $date->format('M'),
            ])
            ->all();
    }

    /**
     * Switch between "Ask a question" and "Schedule a tour".
     */
    public function setMode(string $mode): void
    {
        $this->mode = $mode === 'tour' && $this->allowTours ? 'tour' : 'message';
        $this->resetValidation();
    }

    /**
     * Get the validation rules for the form.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $tour = $this->mode === 'tour';

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => [$tour ? 'required' : 'nullable', 'string', 'max:30', 'regex:/^\+?[0-9(][0-9\s\-().]{6,24}$/'],
            'message' => [$tour ? 'nullable' : 'required', 'string', 'max:3000'],
            'tour_date' => [Rule::requiredIf($tour), 'nullable', Rule::in(array_column($this->tourDays, 'value'))],
            'tour_time' => [Rule::requiredIf($tour), 'nullable', Rule::in(array_keys(Enquiry::TOUR_TIMES))],
            'tour_kind' => [Rule::requiredIf($tour), 'nullable', Rule::in(array_keys(Enquiry::TOUR_KINDS))],
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
            'name.required' => 'Please tell us your name.',
            'email.required' => 'Please add your email so the agent can reply.',
            'email.email' => 'Please enter a valid email address.',
            'phone.required' => 'Please add a phone number so the agent can confirm your tour.',
            'phone.regex' => 'Please enter a valid phone number, e.g. (512) 555-0142.',
            'message.required' => 'Please write a short message.',
            'tour_date.in' => 'Please pick one of the days shown.',
            'tour_date.required' => 'Please pick a day.',
        ];
    }

    /**
     * Save the message or tour request and email the agent.
     */
    public function submit(): void
    {
        $limiterKey = 'contact-agent:'.request()->ip();

        if (RateLimiter::tooManyAttempts($limiterKey, self::MAX_ATTEMPTS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($limiterKey) / 60);
            $this->addError('form', "You've sent several messages already. Please try again in {$minutes} minutes, or call the agent.");

            return;
        }

        $validated = $this->validate();

        RateLimiter::hit($limiterKey, 600);

        if (filled($this->website)) {
            $this->sent = true;

            return;
        }

        $tour = $this->mode === 'tour';

        $enquiry = Enquiry::query()->create([
            'type' => $tour ? EnquiryType::Tour : EnquiryType::Message,
            'status' => EnquiryStatus::New,
            'listing_id' => $this->listing?->id,
            'agent_id' => $this->agent->id,
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'phone' => $validated['phone'] ?: null,
            'message' => $validated['message'] ?: null,
            'tour_date' => $tour ? $validated['tour_date'] : null,
            'tour_time' => $tour ? $validated['tour_time'] : null,
            'tour_kind' => $tour ? $validated['tour_kind'] : null,
            'ip_address' => request()->ip(),
        ]);

        try {
            $office = Setting::get('email');
            $mail = Mail::to($this->agent->contactEmail());

            if (filled($office) && strcasecmp($office, $this->agent->contactEmail()) !== 0) {
                $mail->cc($office);
            }

            $mail->send(new EnquiryReceived($enquiry));
        } catch (\Throwable $exception) {
            report($exception);
        }

        $this->sent = true;
    }

    /**
     * Show the form again.
     */
    public function another(): void
    {
        $this->sent = false;
        $this->reset('website');
    }
}; ?>

<div>
    @if ($sent)
        <div class="sent" role="status">
            <span class="sent__icon"><x-icon name="check" /></span>
            <strong>{{ $mode === 'tour' ? 'Tour request sent!' : 'Message sent!' }}</strong>
            <span>{{ Str::before($agent->name, ' ') }} will reply to {{ $email }}{{ $mode === 'tour' ? ' to confirm the time' : '' }}, usually within a few hours.</span>
            <button class="btn btn--outline btn--sm" type="button" wire:click="another">Send another message</button>
        </div>
    @else
        @if ($allowTours)
            <div class="tabs" role="tablist" aria-label="Contact the agent">
                <button type="button" role="tab" aria-selected="{{ $mode === 'tour' ? 'true' : 'false' }}" wire:click="setMode('tour')">Schedule a tour</button>
                <button type="button" role="tab" aria-selected="{{ $mode === 'message' ? 'true' : 'false' }}" wire:click="setMode('message')">Ask a question</button>
            </div>
        @endif

        <form class="form" wire:submit="submit" novalidate>
            @if ($mode === 'tour')
                <fieldset style="border: 0; padding: 0; margin: 0; min-width: 0" class="form">
                    <legend class="sr-only">Pick a day</legend>
                    <div class="days">
                        @foreach ($this->tourDays as $day)
                            <label wire:key="day-{{ $day['value'] }}">
                                <input type="radio" wire:model="tour_date" value="{{ $day['value'] }}" name="tour_date">
                                <span>{{ $day['weekday'] }}<b>{{ $day['day'] }}</b>{{ $day['month'] }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('tour_date') <span class="field-error">{{ $message }}</span> @enderror
                </fieldset>
                <fieldset style="border: 0; padding: 0; margin: 0">
                    <legend class="sr-only">Time of day</legend>
                    <div class="pills">
                        @foreach (\App\Models\Enquiry::TOUR_TIMES as $value => $label)
                            <label wire:key="time-{{ $value }}"><input type="radio" wire:model="tour_time" value="{{ $value }}" name="tour_time"><span>{{ $label }}</span></label>
                        @endforeach
                    </div>
                </fieldset>
                <fieldset style="border: 0; padding: 0; margin: 0">
                    <legend class="sr-only">Tour type</legend>
                    <div class="pills">
                        @foreach (\App\Models\Enquiry::TOUR_KINDS as $value => $label)
                            <label wire:key="kind-{{ $value }}"><input type="radio" wire:model="tour_kind" value="{{ $value }}" name="tour_kind"><span>{{ $label }}</span></label>
                        @endforeach
                    </div>
                </fieldset>
            @endif

            <label for="ca-name-{{ $this->getId() }}">Full name
                <input class="input" id="ca-name-{{ $this->getId() }}" wire:model="name" autocomplete="name" required @error('name') aria-invalid="true" @enderror>
                @error('name') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <div class="row2">
                <label for="ca-email-{{ $this->getId() }}">Email
                    <input class="input" id="ca-email-{{ $this->getId() }}" type="email" wire:model="email" autocomplete="email" required @error('email') aria-invalid="true" @enderror>
                    @error('email') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label for="ca-phone-{{ $this->getId() }}"><span>Phone @if ($mode !== 'tour')<small>(optional)</small>@endif</span>
                    <input class="input" id="ca-phone-{{ $this->getId() }}" type="tel" wire:model="phone" autocomplete="tel" inputmode="tel" @error('phone') aria-invalid="true" @enderror>
                    @error('phone') <span class="field-error">{{ $message }}</span> @enderror
                </label>
            </div>
            <label for="ca-message-{{ $this->getId() }}"><span>{{ $mode === 'tour' ? 'Anything the agent should know?' : 'Message' }} @if ($mode === 'tour')<small>(optional)</small>@endif</span>
                <textarea class="textarea" id="ca-message-{{ $this->getId() }}" wire:model="message" rows="4" @error('message') aria-invalid="true" @enderror></textarea>
                @error('message') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <div class="hp" aria-hidden="true">
                <label for="ca-website-{{ $this->getId() }}">Leave this empty<input id="ca-website-{{ $this->getId() }}" wire:model="website" tabindex="-1" autocomplete="off"></label>
            </div>

            @error('form') <p class="field-error" role="alert">{{ $message }}</p> @enderror

            <button class="btn btn--primary btn--block" type="submit" wire:loading.attr="disabled" wire:target="submit">
                <span wire:loading.remove wire:target="submit">{{ $mode === 'tour' ? 'Request a tour' : 'Send message' }}</span>
                <span wire:loading wire:target="submit">Sending…</span>
            </button>
            <p class="form-note">By sending, you agree that {{ \App\Models\Setting::get('business_name') }} and the agent may contact you about this request. See our <a href="{{ route('privacy') }}">privacy policy</a>.</p>
        </form>
    @endif
</div>
