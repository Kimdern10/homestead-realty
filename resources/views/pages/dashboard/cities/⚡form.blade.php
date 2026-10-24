<?php

use App\Models\City;
use App\Support\ImageOptimizer;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('City')] class extends Component {
    use WithFileUploads;

    public ?City $city = null;

    public string $name = '';

    public string $state = '';

    public string $state_code = '';

    public string $tagline = '';

    public string $description = '';

    public string $latitude = '';

    public string $longitude = '';

    public bool $is_featured = true;

    public int $sort_order = 0;

    /** @var TemporaryUploadedFile|null */
    public $image = null;

    /**
     * Fill the form when editing.
     */
    public function mount(?City $city = null): void
    {
        if (! $city?->exists) {
            $this->sort_order = (int) City::query()->max('sort_order') + 1;

            return;
        }

        $this->city = $city;
        $this->fill([
            'name' => $city->name,
            'state' => $city->state,
            'state_code' => $city->state_code,
            'tagline' => (string) $city->tagline,
            'description' => (string) $city->description,
            'latitude' => (string) $city->latitude,
            'longitude' => (string) $city->longitude,
            'is_featured' => $city->is_featured,
            'sort_order' => $city->sort_order,
        ]);
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
            'state' => ['required', 'string', 'max:60'],
            'state_code' => ['required', 'alpha', 'size:2'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'is_featured' => ['boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
        ];
    }

    /**
     * Save the city.
     */
    public function save(ImageOptimizer $images): void
    {
        $this->state_code = strtoupper(trim($this->state_code));
        $validated = $this->validate();

        $city = $this->city ?? new City;
        $slug = Str::slug($validated['name'].($this->nameIsTaken($validated['name'], $city) ? '-'.$validated['state_code'] : ''));

        $imagePath = $city->image_path;

        if ($this->image) {
            if ($imagePath) {
                Storage::disk('public')->delete([$imagePath, str_replace('.webp', '-sm.webp', $imagePath)]);
            }

            $imagePath = $images->store($this->image, 'cities')['path'];
        }

        $city->fill([
            ...collect($validated)->except('image')->all(),
            'slug' => $city->slug ?? $slug,
            'image_path' => $imagePath,
            'tagline' => $validated['tagline'] ?: null,
            'description' => $validated['description'] ?: null,
        ])->save();

        Flux::toast(__('City saved.'), variant: 'success');

        $this->redirectRoute('dashboard.cities.index', navigate: true);
    }

    /**
     * Two cities can share a name (Portland, OR and Portland, ME); add the state to the address then.
     */
    private function nameIsTaken(string $name, City $city): bool
    {
        return City::query()->where('slug', Str::slug($name))->whereKeyNot($city->id)->exists();
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div>
        <flux:link :href="route('dashboard.cities.index')" wire:navigate class="text-sm">← {{ __('All cities') }}</flux:link>
        <flux:heading size="xl" level="1" class="mt-2">{{ $city ? __('Edit :name', ['name' => $city->name]) : __('Add a city') }}</flux:heading>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6">
        <div class="grid gap-5 sm:grid-cols-3">
            <flux:input wire:model="name" :label="__('City *')" placeholder="Austin" />
            <flux:input wire:model="state" :label="__('State *')" placeholder="Texas" />
            <flux:input wire:model="state_code" maxlength="2" :label="__('State code *')" placeholder="TX" />
        </div>
        <flux:input wire:model="tagline" :label="__('Tagline')" :placeholder="__('e.g. Live music, hill country views and a booming tech scene.')" />
        <flux:textarea wire:model="description" rows="5" :label="__('About the city')" :description="__('Shown on the city page. Leave a blank line between paragraphs.')" />
        <div class="grid gap-5 sm:grid-cols-2">
            <flux:input wire:model="latitude" :label="__('Map latitude *')" placeholder="30.2672" :description="__('In Google Maps, right-click the city centre and click the numbers to copy them.')" />
            <flux:input wire:model="longitude" :label="__('Map longitude *')" placeholder="-97.7431" />
        </div>
        <div class="grid gap-3">
            <flux:input type="file" wire:model="image" accept="image/jpeg,image/png,image/webp" :label="__('Photo')" :description="__('A wide skyline or street photo. Shown on the city page and city cards.')" />
            <div wire:loading wire:target="image"><flux:text>{{ __('Uploading…') }}</flux:text></div>
            @if ($image && $image->isPreviewable() && ! $errors->has('image'))
                <img src="{{ $image->temporaryUrl() }}" alt="" class="aspect-[16/9] w-80 rounded-lg border border-amber-400 object-cover">
            @elseif ($city?->image_path)
                <img src="{{ $city->imageUrl(small: true) }}" alt="" class="aspect-[16/9] w-80 rounded-lg object-cover">
            @endif
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <flux:switch wire:model="is_featured" :label="__('Show on the home page')" />
            <flux:input wire:model="sort_order" type="number" min="0" :label="__('Sort order')" :description="__('Lower numbers show first.')" />
        </div>
        <div class="flex items-center gap-3">
            <flux:button variant="primary" type="submit">{{ __('Save city') }}</flux:button>
            <flux:button :href="route('dashboard.cities.index')" wire:navigate variant="ghost">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>
