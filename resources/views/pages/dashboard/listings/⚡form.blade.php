<?php

use App\Enums\ListingStatus;
use App\Enums\PropertyType;
use App\Models\Agent;
use App\Models\City;
use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Support\ImageOptimizer;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Listing')] class extends Component {
    use WithFileUploads;

    public ?Listing $listing = null;

    public string $title = '';

    public string $status = 'for_sale';

    public string $property_type = 'house';

    public string $price = '';

    public string $beds = '';

    public string $baths = '';

    public string $sqft = '';

    public string $lot_acres = '';

    public string $year_built = '';

    public string $garage_spaces = '';

    public string $address = '';

    public string $city_id = '';

    public string $neighborhood = '';

    public string $zip = '';

    public string $latitude = '';

    public string $longitude = '';

    public string $description = '';

    /** @var list<string> */
    public array $amenities = [];

    public string $video_url = '';

    public string $virtual_tour_url = '';

    public string $hoa_fee = '';

    public string $property_tax = '';

    public string $agent_id = '';

    public bool $is_published = true;

    public bool $is_featured = false;

    public string $listed_at = '';

    public string $sold_at = '';

    /**
     * Saved photo ids, in display order.
     *
     * @var list<int>
     */
    public array $photoIds = [];

    /** @var array<int, TemporaryUploadedFile> */
    public array $newPhotos = [];

    /**
     * Fill the form when editing.
     */
    public function mount(?Listing $listing = null): void
    {
        $user = auth()->user();

        if (! $listing?->exists) {
            $this->authorize('create', Listing::class);
            $this->agent_id = (string) ($user->agent?->id ?? Agent::query()->orderBy('name')->value('id') ?? '');
            $this->listed_at = today()->toDateString();

            return;
        }

        $this->authorize('update', $listing);

        $this->listing = $listing;
        $this->fill([
            'title' => $listing->title,
            'status' => $listing->status->value,
            'property_type' => $listing->property_type->value,
            'price' => (string) $listing->price,
            'beds' => (string) $listing->beds,
            'baths' => $listing->baths !== null ? $listing->bathsLabel() : '',
            'sqft' => (string) $listing->sqft,
            'lot_acres' => $listing->lot_acres !== null ? rtrim(rtrim($listing->lot_acres, '0'), '.') : '',
            'year_built' => (string) $listing->year_built,
            'garage_spaces' => (string) $listing->garage_spaces,
            'address' => $listing->address,
            'city_id' => (string) $listing->city_id,
            'neighborhood' => (string) $listing->neighborhood,
            'zip' => $listing->zip,
            'latitude' => (string) $listing->latitude,
            'longitude' => (string) $listing->longitude,
            'description' => $listing->description,
            'amenities' => $listing->amenities ?? [],
            'video_url' => (string) $listing->video_url,
            'virtual_tour_url' => (string) $listing->virtual_tour_url,
            'hoa_fee' => (string) $listing->hoa_fee,
            'property_tax' => (string) $listing->property_tax,
            'agent_id' => (string) $listing->agent_id,
            'is_published' => $listing->is_published,
            'is_featured' => $listing->is_featured,
            'listed_at' => $listing->listed_at->toDateString(),
            'sold_at' => (string) $listing->sold_at?->toDateString(),
        ]);
        $this->photoIds = $listing->photos->pluck('id')->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, City>
     */
    #[Computed]
    public function cities()
    {
        return City::query()->ordered()->get(['id', 'name', 'state_code', 'latitude', 'longitude']);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Agent>
     */
    #[Computed]
    public function agents()
    {
        return Agent::query()->orderBy('name')->get(['id', 'name', 'is_active']);
    }

    /**
     * Saved photos in the order they will be shown.
     *
     * @return \Illuminate\Support\Collection<int, ListingPhoto>
     */
    #[Computed]
    public function photos()
    {
        $photos = ListingPhoto::query()->whereIn('id', $this->photoIds)->get()->keyBy('id');

        return collect($this->photoIds)->map(fn (int $id) => $photos->get($id))->filter()->values();
    }

    /**
     * Neighborhood names already used in the chosen city, for suggestions.
     *
     * @return list<string>
     */
    #[Computed]
    public function neighborhoods(): array
    {
        return Listing::query()->where('city_id', (int) $this->city_id)->whereNotNull('neighborhood')->distinct()->orderBy('neighborhood')->pluck('neighborhood')->all();
    }

    /**
     * Centre the map picker on the newly chosen city.
     */
    public function updatedCityId(): void
    {
        $city = City::query()->find((int) $this->city_id);

        if ($city) {
            $this->dispatch('city-center', lat: (float) $city->latitude, lng: (float) $city->longitude);
        }
    }

    /**
     * Move a saved photo one place earlier or later.
     */
    public function movePhoto(int $index, int $direction): void
    {
        $target = $index + $direction;

        if (! isset($this->photoIds[$index], $this->photoIds[$target])) {
            return;
        }

        [$this->photoIds[$index], $this->photoIds[$target]] = [$this->photoIds[$target], $this->photoIds[$index]];
    }

    /**
     * Make a saved photo the cover (first photo).
     */
    public function makeCover(int $index): void
    {
        if (! isset($this->photoIds[$index])) {
            return;
        }

        $id = $this->photoIds[$index];
        unset($this->photoIds[$index]);
        $this->photoIds = [$id, ...array_values($this->photoIds)];
    }

    /**
     * Remove a saved photo (the files are deleted when the listing is saved).
     */
    public function removePhoto(int $index): void
    {
        unset($this->photoIds[$index]);
        $this->photoIds = array_values($this->photoIds);
    }

    /**
     * Remove a photo that has been chosen but not saved yet.
     */
    public function removeNewPhoto(int $index): void
    {
        unset($this->newPhotos[$index]);
        $this->newPhotos = array_values($this->newPhotos);
    }

    /**
     * Get the validation rules for the form.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $isLand = $this->property_type === PropertyType::Land->value;

        return [
            'title' => ['required', 'string', 'max:150'],
            'status' => ['required', Rule::enum(ListingStatus::class)],
            'property_type' => ['required', Rule::enum(PropertyType::class)],
            'price' => ['required', 'integer', 'min:1', 'max:999999999'],
            'beds' => [$isLand ? 'nullable' : 'required', 'nullable', 'integer', 'min:0', 'max:50'],
            'baths' => [$isLand ? 'nullable' : 'required', 'nullable', 'numeric', 'min:0', 'max:50', 'multiple_of:0.5'],
            'sqft' => ['nullable', 'integer', 'min:50', 'max:200000'],
            'lot_acres' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'year_built' => ['nullable', 'integer', 'min:1700', 'max:'.(now()->year + 3)],
            'garage_spaces' => ['nullable', 'integer', 'min:0', 'max:20'],
            'address' => ['required', 'string', 'max:200'],
            'city_id' => ['required', Rule::exists('cities', 'id')],
            'neighborhood' => ['nullable', 'string', 'max:100'],
            'zip' => ['required', 'regex:/^\d{5}(-\d{4})?$/'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'description' => ['required', 'string', 'min:30', 'max:10000'],
            'amenities' => ['array'],
            'amenities.*' => ['string', Rule::in(Listing::AMENITIES)],
            'video_url' => ['nullable', 'url:http,https', 'max:255'],
            'virtual_tour_url' => ['nullable', 'url:http,https', 'max:255'],
            'hoa_fee' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'property_tax' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'agent_id' => auth()->user()->isAdmin() ? ['required', Rule::exists('agents', 'id')] : ['nullable'],
            'is_published' => ['boolean'],
            'is_featured' => ['boolean'],
            'listed_at' => ['required', 'date'],
            'sold_at' => ['nullable', 'date', 'after_or_equal:listed_at'],
            'newPhotos' => ['array', 'max:'.max(0, Listing::MAX_PHOTOS - count($this->photoIds))],
            'newPhotos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
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
            'zip.regex' => 'Enter a 5-digit ZIP code, e.g. 78704.',
            'baths.multiple_of' => 'Use whole or half baths, e.g. 2 or 2.5.',
            'newPhotos.max' => 'A listing can have up to '.Listing::MAX_PHOTOS.' photos. Remove some first.',
            'newPhotos.*.image' => 'Each photo must be an image (JPG, PNG or WebP).',
            'newPhotos.*.max' => 'Each photo must be smaller than 12 MB.',
            'description.min' => 'Please write at least a couple of sentences about the home.',
            'latitude.required_with' => 'Pick the spot on the map, or clear both numbers.',
            'longitude.required_with' => 'Pick the spot on the map, or clear both numbers.',
        ];
    }

    /**
     * Save the listing, compressing new photos to WebP.
     */
    public function save(ImageOptimizer $images): void
    {
        foreach (['price', 'hoa_fee', 'property_tax', 'sqft'] as $money) {
            $this->{$money} = preg_replace('/[^0-9]/', '', $this->{$money});
        }

        $validated = $this->validate();
        $user = auth()->user();
        $listing = $this->listing ?? new Listing;
        $status = ListingStatus::from($validated['status']);
        $city = City::query()->findOrFail((int) $validated['city_id']);
        $nullable = fn (string $key) => filled($validated[$key] ?? null) ? $validated[$key] : null;

        $listing->fill([
            'agent_id' => $user->isAdmin() ? (int) $validated['agent_id'] : $user->agent->id,
            'city_id' => $city->id,
            'title' => $validated['title'],
            'slug' => $listing->exists && $listing->address === $validated['address'] && $listing->city_id === $city->id
                ? $listing->slug
                : Listing::uniqueSlug("{$validated['address']} {$city->name} {$city->state_code} {$validated['zip']}", $listing->id),
            'status' => $status,
            'property_type' => PropertyType::from($validated['property_type']),
            'price' => (int) $validated['price'],
            'beds' => $nullable('beds'),
            'baths' => $nullable('baths'),
            'sqft' => $nullable('sqft'),
            'lot_acres' => $nullable('lot_acres'),
            'year_built' => $nullable('year_built'),
            'garage_spaces' => $nullable('garage_spaces'),
            'address' => trim($validated['address']),
            'neighborhood' => $nullable('neighborhood'),
            'zip' => $validated['zip'],
            'latitude' => $nullable('latitude'),
            'longitude' => $nullable('longitude'),
            'description' => trim($validated['description']),
            'amenities' => array_values(array_intersect(Listing::AMENITIES, $validated['amenities'])),
            'video_url' => $nullable('video_url'),
            'virtual_tour_url' => $nullable('virtual_tour_url'),
            'hoa_fee' => $nullable('hoa_fee'),
            'property_tax' => $nullable('property_tax'),
            'is_published' => $validated['is_published'],
            'is_featured' => $user->isAdmin() ? $validated['is_featured'] : ($listing->is_featured ?? false),
            'listed_at' => $validated['listed_at'],
            'sold_at' => $status === ListingStatus::Sold ? ($nullable('sold_at') ?? today()->toDateString()) : null,
        ]);

        $stored = [];

        foreach ($this->newPhotos as $photo) {
            $stored[] = $images->store($photo, 'listings');
        }

        $removedPaths = [];

        DB::transaction(function () use ($listing, $stored, &$removedPaths) {
            $listing->save();

            $removed = $listing->photos()->whereNotIn('id', $this->photoIds ?: [0])->get();
            $removedPaths = $removed->flatMap(fn (ListingPhoto $photo) => $photo->paths())->all();
            $listing->photos()->whereIn('id', $removed->modelKeys())->delete();

            foreach ($this->photoIds as $order => $id) {
                $listing->photos()->whereKey($id)->update(['sort_order' => $order]);
            }

            foreach ($stored as $offset => $photo) {
                $listing->photos()->create([...$photo, 'sort_order' => count($this->photoIds) + $offset]);
            }
        });

        Storage::disk('public')->delete($removedPaths);

        Flux::toast(__('Listing saved.'), variant: 'success');

        $this->redirectRoute('dashboard.listings.edit', $listing, navigate: true);
    }
}; ?>

<div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
    <div>
        <flux:link :href="route('dashboard.listings.index')" wire:navigate class="text-sm">← {{ __('All listings') }}</flux:link>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
            <flux:heading size="xl" level="1">{{ $listing ? __('Edit listing') : __('Add a listing') }}</flux:heading>
            @if ($listing?->is_published)
                <flux:button size="sm" icon="arrow-top-right-on-square" :href="route('listings.show', $listing)" target="_blank">{{ __('View on website') }}</flux:button>
            @endif
        </div>
        <flux:text class="mt-1">{{ __('Photos are resized and compressed automatically, so phone photos are fine. Fields marked * are required.') }}</flux:text>
    </div>

    <form wire:submit="save" class="flex flex-col gap-8">
        <flux:fieldset>
            <flux:legend>{{ __('The basics') }}</flux:legend>
            <div class="grid gap-5">
                <div class="grid gap-5 sm:grid-cols-3">
                    <flux:select wire:model.live="status" :label="__('Status *')">
                        @foreach (\App\Enums\ListingStatus::options() as $value => $label)
                            <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="property_type" :label="__('Home type *')">
                        @foreach (\App\Enums\PropertyType::options() as $value => $label)
                            <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="price" inputmode="numeric" icon="currency-dollar" :label="$status === 'for_rent' ? __('Rent per month *') : __('Price *')" placeholder="550000" />
                </div>
                <flux:input wire:model="title" :label="__('Headline *')" :placeholder="__('e.g. Renovated craftsman bungalow in Travis Heights')" :description="__('Used in Google results and on the listing page.')" />
                @if (auth()->user()->isAdmin())
                    <flux:select wire:model="agent_id" :label="__('Listing agent *')">
                        @foreach ($this->agents as $option)
                            <flux:select.option :value="$option->id">{{ $option->name }}{{ $option->is_active ? '' : ' ('.__('inactive').')' }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
            </div>
        </flux:fieldset>

        <flux:fieldset>
            <flux:legend>{{ __('Size and features') }}</flux:legend>
            <div class="grid gap-5">
                <div class="grid grid-cols-2 gap-5 sm:grid-cols-4">
                    <flux:input wire:model="beds" type="number" min="0" :label="__('Bedrooms').($property_type === 'land' ? '' : ' *')" :description="__('0 = studio')" />
                    <flux:input wire:model="baths" type="number" min="0" step="0.5" :label="__('Bathrooms').($property_type === 'land' ? '' : ' *')" :description="__('e.g. 2.5')" />
                    <flux:input wire:model="sqft" inputmode="numeric" :label="__('Square feet')" />
                    <flux:input wire:model="lot_acres" type="number" min="0" step="0.01" :label="__('Lot (acres)')" />
                    <flux:input wire:model="year_built" type="number" :label="__('Year built')" />
                    <flux:input wire:model="garage_spaces" type="number" min="0" :label="__('Garage spaces')" />
                    <flux:input wire:model="hoa_fee" inputmode="numeric" icon="currency-dollar" :label="__('HOA per month')" />
                    <flux:input wire:model="property_tax" inputmode="numeric" icon="currency-dollar" :label="__('Tax per year')" />
                </div>
                <flux:checkbox.group wire:model="amenities" :label="__('Amenities')">
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach (\App\Models\Listing::AMENITIES as $amenity)
                            <flux:checkbox :value="$amenity" :label="$amenity" wire:key="amenity-{{ $loop->index }}" />
                        @endforeach
                    </div>
                </flux:checkbox.group>
                <flux:textarea wire:model="description" rows="8" :label="__('Description *')" :description="__('Describe the home, the rooms and the area. Leave a blank line between paragraphs.')" />
            </div>
        </flux:fieldset>

        <flux:fieldset>
            <flux:legend>{{ __('Location') }}</flux:legend>
            <div class="grid gap-5">
                <flux:input wire:model="address" :label="__('Street address *')" :placeholder="__('e.g. 1204 Bluebonnet Ln, or 500 Main St #1204')" />
                <div class="grid gap-5 sm:grid-cols-3">
                    <flux:select wire:model.live="city_id" :label="__('City *')" :placeholder="__('Choose a city')">
                        @foreach ($this->cities as $city)
                            <flux:select.option :value="$city->id">{{ $city->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <div>
                        <flux:input wire:model="neighborhood" :label="__('Neighborhood')" list="neighborhoods" />
                        <datalist id="neighborhoods">
                            @foreach ($this->neighborhoods as $name)
                                <option value="{{ $name }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <flux:input wire:model="zip" inputmode="numeric" :label="__('ZIP code *')" placeholder="78704" />
                </div>
                @if (auth()->user()->isAdmin() && $this->cities->isEmpty())
                    <flux:callout icon="map" color="amber">{{ __('Add a city first under Office › Cities.') }}</flux:callout>
                @endif

                @php($selectedCity = $this->cities->firstWhere('id', (int) $city_id))
                <div class="grid gap-2" wire:ignore
                    x-data="locationPicker({ lat: @js($latitude), lng: @js($longitude), cityLat: @js($selectedCity?->latitude), cityLng: @js($selectedCity?->longitude) })"
                    x-on:city-center.window="center($event.detail)">
                    <flux:label>{{ __('Pin on the map') }}</flux:label>
                    <flux:text class="text-sm">{{ __('Click or tap the map where the home is. Drag the pin to adjust. Leave empty to hide the map on the listing.') }}</flux:text>
                    <div x-ref="map" class="h-72 w-full overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700"></div>
                    <div class="flex flex-wrap items-center gap-3 text-sm text-zinc-500">
                        <span x-text="label"></span>
                        <button type="button" class="text-[#d92228] underline" x-show="hasPin" x-on:click="clear()">{{ __('Remove pin') }}</button>
                    </div>
                </div>
                @error('latitude') <flux:text class="text-red-600! dark:text-red-400!">{{ $message }}</flux:text> @enderror
            </div>
        </flux:fieldset>

        <flux:fieldset>
            <flux:legend>{{ __('Photos') }}</flux:legend>
            <div class="grid gap-3">
                <flux:text class="text-sm">{{ __('The first photo is the cover shown on cards. Use the arrows to reorder. Up to :max photos.', ['max' => \App\Models\Listing::MAX_PHOTOS]) }}</flux:text>
                @if (count($photoIds) + count($newPhotos) < \App\Models\Listing::MAX_PHOTOS)
                    <flux:input type="file" wire:model="newPhotos" multiple accept="image/jpeg,image/png,image/webp" :label="__('Add photos')" />
                @endif
                <div wire:loading wire:target="newPhotos"><flux:text>{{ __('Uploading…') }}</flux:text></div>
                @error('newPhotos') <flux:text class="text-red-600! dark:text-red-400!">{{ $message }}</flux:text> @enderror
                @error('newPhotos.*') <flux:text class="text-red-600! dark:text-red-400!">{{ $message }}</flux:text> @enderror

                @if (count($photoIds) || count($newPhotos))
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        @foreach ($this->photos as $index => $photo)
                            <div class="relative" wire:key="photo-{{ $photo->id }}">
                                <img src="{{ $photo->url(small: true) }}" alt="" class="aspect-[3/2] w-full rounded-lg border border-zinc-200 object-cover dark:border-zinc-700">
                                @if ($index === 0)<div class="absolute start-2 top-2"><flux:badge size="sm" color="red">{{ __('Cover') }}</flux:badge></div>@endif
                                <div class="absolute end-2 top-2">
                                    <flux:button size="xs" variant="danger" icon="x-mark" wire:click="removePhoto({{ $index }})" :aria-label="__('Remove photo')" />
                                </div>
                                <div class="absolute inset-x-2 bottom-2 flex justify-between">
                                    <flux:button size="xs" icon="arrow-left" wire:click="movePhoto({{ $index }}, -1)" :disabled="$index === 0" :aria-label="__('Move earlier')" />
                                    @if ($index > 0)<flux:button size="xs" wire:click="makeCover({{ $index }})">{{ __('Make cover') }}</flux:button>@endif
                                    <flux:button size="xs" icon="arrow-right" wire:click="movePhoto({{ $index }}, 1)" :disabled="$loop->last" :aria-label="__('Move later')" />
                                </div>
                            </div>
                        @endforeach
                        @foreach ($newPhotos as $index => $file)
                            <div class="relative" wire:key="new-{{ $index }}-{{ $file->getFilename() }}">
                                @if ($file->isPreviewable())
                                    <img src="{{ $file->temporaryUrl() }}" alt="" class="aspect-[3/2] w-full rounded-lg border border-amber-400 object-cover">
                                @endif
                                <div class="absolute start-2 top-2"><flux:badge size="sm" color="amber">{{ __('New') }}</flux:badge></div>
                                <div class="absolute end-2 top-2">
                                    <flux:button size="xs" variant="danger" icon="x-mark" wire:click="removeNewPhoto({{ $index }})" :aria-label="__('Remove photo')" />
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </flux:fieldset>

        <flux:fieldset>
            <flux:legend>{{ __('Online tours') }}</flux:legend>
            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input wire:model="video_url" type="url" icon="play-circle" :label="__('Video link')" placeholder="https://youtube.com/watch?v=…" />
                <flux:input wire:model="virtual_tour_url" type="url" icon="cube" :label="__('3D / virtual tour link')" placeholder="https://my.matterport.com/show/?m=…" />
            </div>
        </flux:fieldset>

        <flux:fieldset>
            <flux:legend>{{ __('Showing it on the website') }}</flux:legend>
            <div class="grid gap-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:input wire:model="listed_at" type="date" :label="__('Listed on *')" />
                    @if ($status === 'sold')
                        <flux:input wire:model="sold_at" type="date" :label="__('Sold on')" :description="__('Leave empty for today.')" />
                    @endif
                </div>
                <flux:switch wire:model="is_published" :label="__('Published')" :description="__('Turn off to keep it as a draft that visitors cannot see.')" />
                @if (auth()->user()->isAdmin())
                    <flux:switch wire:model="is_featured" :label="__('Featured')" :description="__('Featured homes appear first on the home page and in search results.')" />
                @endif
            </div>
        </flux:fieldset>

        @if ($errors->any())
            <flux:callout icon="exclamation-triangle" color="red">{{ __('Please fix the highlighted fields above.') }}</flux:callout>
        @endif

        <div class="flex items-center gap-3">
            <flux:button variant="primary" type="submit" data-test="save-listing">
                <span wire:loading.remove wire:target="save">{{ __('Save listing') }}</span>
                <span wire:loading wire:target="save">{{ __('Saving and compressing photos…') }}</span>
            </flux:button>
            <flux:button :href="route('dashboard.listings.index')" wire:navigate variant="ghost">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>
