<?php

use App\Enums\ListingStatus;
use App\Mail\SubmissionReceived;
use App\Models\HomeType;
use App\Models\Listing;
use App\Models\Setting;
use App\Services\ListingImporter;
use App\Services\ZipLookup;
use App\Support\ImageOptimizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    /**
     * Most photos an owner can send.
     */
    public const int MAX_PHOTOS = 12;

    public string $status = 'for_sale';

    public string $property_type = '';

    public string $address = '';

    public string $zip = '';

    public string $price = '';

    public string $beds = '';

    public string $baths = '';

    public string $sqft = '';

    public string $year_built = '';

    public string $description = '';

    public string $owner_name = '';

    public string $owner_email = '';

    public string $owner_phone = '';

    /** @var list<TemporaryUploadedFile> */
    public array $photos = [];

    public bool $agree = false;

    /**
     * Hidden from people; spam bots fill it in.
     */
    public string $website = '';

    #[Locked]
    public bool $sent = false;

    public function mount(): void
    {
        $this->property_type = (string) array_key_first(HomeType::options());

        if ($user = auth()->user()) {
            $this->owner_name = $user->name;
            $this->owner_email = $user->email;
        }
    }

    /**
     * Remove one chosen photo before sending.
     */
    public function removePhoto(int $index): void
    {
        unset($this->photos[$index]);
        $this->photos = array_values($this->photos);
    }

    /**
     * Save the home as a hidden listing for the office to check, then thank the owner.
     */
    public function submit(ZipLookup $zips, ListingImporter $importer, ImageOptimizer $images): void
    {
        $limiterKey = 'sell:'.request()->ip();

        if (RateLimiter::tooManyAttempts($limiterKey, 3)) {
            $this->addError('form', 'You have sent several homes already today. Please call us if you have more.');

            return;
        }

        $validated = $this->validate([
            'status' => ['required', Rule::in([ListingStatus::ForSale->value, ListingStatus::ForRent->value])],
            'property_type' => ['required', Rule::in(array_keys(HomeType::options()))],
            'address' => ['required', 'string', 'max:150'],
            'zip' => ['required', 'regex:/^\d{5}$/'],
            'price' => ['required', 'integer', 'min:100', 'max:100000000'],
            'beds' => ['nullable', 'integer', 'between:0,30'],
            'baths' => ['nullable', 'numeric', 'between:0,30'],
            'sqft' => ['nullable', 'integer', 'between:100,100000'],
            'year_built' => ['nullable', 'integer', 'between:1700,'.(now()->year + 1)],
            'description' => ['required', 'string', 'min:40', 'max:5000'],
            'owner_name' => ['required', 'string', 'max:100'],
            'owner_email' => ['required', 'email', 'max:150'],
            'owner_phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9(][0-9\s\-().]{6,24}$/'],
            'photos' => ['array', 'max:'.self::MAX_PHOTOS],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
            'agree' => ['accepted'],
        ], [
            'price.required' => 'Please enter your asking price.',
            'description.min' => 'Please describe the home in a few sentences (at least 40 characters).',
            'owner_phone.required' => 'Please add a phone number so we can reach you.',
            'owner_phone.regex' => 'Please enter a valid phone number.',
            'agree.accepted' => 'Please confirm you own this home or may list it.',
            'photos.max' => 'You can send up to '.self::MAX_PHOTOS.' photos.',
            'photos.*.image' => 'Each photo must be a JPG, PNG or WebP image.',
        ]);

        $place = $zips->find($validated['zip']);

        if ($place === null) {
            $this->addError('zip', 'We could not find that ZIP code. Please check it.');

            return;
        }

        RateLimiter::hit($limiterKey, 86400);

        if (filled($this->website)) {
            $this->sent = true;

            return;
        }

        $city = $importer->city($place['place'], $place['state_code'], ['latitude' => $place['lat'], 'longitude' => $place['lng']]);
        $agent = $importer->agentFor($validated['zip'], $city);

        if ($agent === null) {
            $this->addError('form', 'We cannot take new homes online right now. Please call us.');

            return;
        }

        $stored = array_map(fn (TemporaryUploadedFile $photo) => $images->store($photo, 'listings'), $this->photos);
        $status = ListingStatus::from($validated['status']);
        $typeName = HomeType::options()[$validated['property_type']] ?? 'Home';

        $listing = DB::transaction(function () use ($validated, $city, $agent, $place, $status, $typeName, $stored): Listing {
            $listing = Listing::query()->create([
                'source' => 'owner',
                'agent_id' => $agent->id,
                'city_id' => $city->id,
                'title' => $typeName.' '.($status === ListingStatus::ForRent ? 'for rent' : 'for sale').' in '.$city->name,
                'slug' => Listing::uniqueSlug("{$validated['address']} {$city->name} {$city->state_code} {$validated['zip']}"),
                'status' => $status,
                'property_type' => $validated['property_type'],
                'price' => (int) $validated['price'],
                'beds' => $validated['beds'] !== '' && $validated['beds'] !== null ? (int) $validated['beds'] : null,
                'baths' => $validated['baths'] !== '' && $validated['baths'] !== null ? round((float) $validated['baths'] * 2) / 2 : null,
                'sqft' => $validated['sqft'] !== '' && $validated['sqft'] !== null ? (int) $validated['sqft'] : null,
                'year_built' => $validated['year_built'] !== '' && $validated['year_built'] !== null ? (int) $validated['year_built'] : null,
                'address' => trim($validated['address']),
                'zip' => $validated['zip'],
                'latitude' => $place['lat'],
                'longitude' => $place['lng'],
                'description' => trim($validated['description']),
                'amenities' => [],
                'is_featured' => false,
                'is_published' => false,
                'listed_at' => today(),
                'owner_name' => $validated['owner_name'],
                'owner_email' => strtolower($validated['owner_email']),
                'owner_phone' => $validated['owner_phone'],
            ]);

            foreach ($stored as $order => $photo) {
                $listing->photos()->create([...$photo, 'sort_order' => $order]);
            }

            return $listing;
        });

        try {
            $office = Setting::get('email');
            $mail = Mail::to($agent->contactEmail());

            if (filled($office) && strcasecmp($office, $agent->contactEmail()) !== 0) {
                $mail->cc($office);
            }

            $mail->send(new SubmissionReceived($listing));
        } catch (\Throwable $exception) {
            report($exception);
        }

        $this->sent = true;
        $this->reset('photos');
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts::site', ['description' => 'List your home for sale or rent. Send us the details and photos; we check every home before it goes live.'])
            ->title('List your home');
    }
}; ?>

<div>
    <div class="page-head">
        <div class="container">
            <h1>List your home</h1>
            <p>Selling or renting out your home yourself? Send us the details and photos. Our team checks every home, then puts it on the website and calls you to talk about next steps.</p>
        </div>
    </div>

    <section class="section">
        <div class="container tool-layout">
            <div class="panel">
                @if ($sent)
                    <div class="sent" role="status" data-test="submission-sent">
                        <span class="sent__icon"><x-icon name="check" /></span>
                        <strong>Thanks, we've got your home!</strong>
                        <span>We'll check the details and email {{ $owner_email }} when it's live, usually within one business day.</span>
                        <a class="btn btn--outline btn--sm" href="{{ route('home') }}">Back to the home page</a>
                    </div>
                @else
                    <form class="form" wire:submit="submit" novalidate>
                        @error('form') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                        <fieldset style="border: 0; padding: 0; margin: 0">
                            <legend class="sr-only">Sell or rent</legend>
                            <div class="pills">
                                <label><input type="radio" wire:model="status" value="for_sale" name="status"><span>Sell it</span></label>
                                <label><input type="radio" wire:model="status" value="for_rent" name="status"><span>Rent it out</span></label>
                            </div>
                        </fieldset>
                        <div class="row2">
                            <label>Home type
                                <select class="select" wire:model="property_type">
                                    @foreach (\App\Models\HomeType::options() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>{{ $status === 'for_rent' ? 'Monthly rent' : 'Asking price' }}
                                <span class="calc__money"><span>$</span><input class="input" wire:model="price" inputmode="numeric" @error('price') aria-invalid="true" @enderror></span>
                                @error('price') <span class="field-error">{{ $message }}</span> @enderror
                            </label>
                        </div>
                        <div class="row2">
                            <label>Street address
                                <input class="input" wire:model="address" autocomplete="street-address" @error('address') aria-invalid="true" @enderror>
                                @error('address') <span class="field-error">{{ $message }}</span> @enderror
                            </label>
                            <label>ZIP code
                                <input class="input" wire:model="zip" inputmode="numeric" maxlength="5" autocomplete="postal-code" @error('zip') aria-invalid="true" @enderror>
                                @error('zip') <span class="field-error">{{ $message }}</span> @enderror
                            </label>
                        </div>
                        <div class="row4">
                            <label>Beds <input class="input" wire:model="beds" inputmode="numeric"></label>
                            <label>Baths <input class="input" wire:model="baths" inputmode="decimal"></label>
                            <label>Sqft <input class="input" wire:model="sqft" inputmode="numeric"></label>
                            <label>Year built <input class="input" wire:model="year_built" inputmode="numeric"></label>
                        </div>
                        @error('beds') <span class="field-error">{{ $message }}</span> @enderror
                        @error('baths') <span class="field-error">{{ $message }}</span> @enderror
                        @error('sqft') <span class="field-error">{{ $message }}</span> @enderror
                        @error('year_built') <span class="field-error">{{ $message }}</span> @enderror
                        <label>About the home
                            <textarea class="textarea" wire:model="description" rows="5" placeholder="What makes it special? Updates, the yard, the street, nearby schools…" @error('description') aria-invalid="true" @enderror></textarea>
                            @error('description') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label>Photos <small>(up to {{ $this::MAX_PHOTOS }}, the first one is the cover)</small>
                            <input class="input" type="file" wire:model="photos" multiple accept="image/jpeg,image/png,image/webp">
                            <span wire:loading wire:target="photos" class="muted" style="font-weight: 500">Uploading…</span>
                            @error('photos') <span class="field-error">{{ $message }}</span> @enderror
                            @error('photos.*') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        @if ($photos)
                            <div class="upload-grid">
                                @foreach ($photos as $index => $photo)
                                    @if ($photo->isPreviewable())
                                        <div wire:key="photo-{{ $index }}">
                                            <img src="{{ $photo->temporaryUrl() }}" alt="">
                                            <button type="button" class="icon-btn" wire:click="removePhoto({{ $index }})" aria-label="Remove photo {{ $index + 1 }}"><x-icon name="x" /></button>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                        <hr style="border: 0; border-top: 1px solid var(--line); margin: 4px 0">
                        <div class="row2">
                            <label>Your name
                                <input class="input" wire:model="owner_name" autocomplete="name" @error('owner_name') aria-invalid="true" @enderror>
                                @error('owner_name') <span class="field-error">{{ $message }}</span> @enderror
                            </label>
                            <label>Phone
                                <input class="input" type="tel" wire:model="owner_phone" autocomplete="tel" @error('owner_phone') aria-invalid="true" @enderror>
                                @error('owner_phone') <span class="field-error">{{ $message }}</span> @enderror
                            </label>
                        </div>
                        <label>Email
                            <input class="input" type="email" wire:model="owner_email" autocomplete="email" @error('owner_email') aria-invalid="true" @enderror>
                            @error('owner_email') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="check" style="font-weight: 600">
                            <input type="checkbox" wire:model="agree"> I own this home or have permission to list it.
                        </label>
                        @error('agree') <span class="field-error">{{ $message }}</span> @enderror
                        <div class="hp" aria-hidden="true"><label>Website <input tabindex="-1" autocomplete="off" wire:model="website"></label></div>
                        <button class="btn btn--primary btn--block" type="submit" wire:loading.attr="disabled" wire:target="submit, photos">
                            <span wire:loading.remove wire:target="submit">Send my home</span>
                            <span wire:loading wire:target="submit">Sending…</span>
                        </button>
                        <p class="form-note">Nothing goes live until our team checks it. <a href="{{ route('privacy') }}">Privacy policy</a>.</p>
                    </form>
                @endif
            </div>
            <aside class="tool-aside">
                <h2>What happens next</h2>
                <ol class="steps">
                    <li><b>We check the details.</b> Usually within one business day.</li>
                    <li><b>Your home goes live.</b> On our website, with a map, payment calculator and tour booking.</li>
                    <li><b>Buyers contact us.</b> A local agent passes every enquiry to you and can help with showings.</li>
                </ol>
                <p class="muted" style="margin-top: 16px; font-size: 14px">Not sure of the price? <a class="link" href="{{ route('home-value') }}">Get a free estimate</a> first.</p>
            </aside>
        </div>
    </section>
</div>
