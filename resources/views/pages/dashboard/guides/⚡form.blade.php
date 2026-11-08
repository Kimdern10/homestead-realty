<?php

use App\Models\City;
use App\Models\Post;
use App\Support\ImageOptimizer;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Guide')] class extends Component {
    use WithFileUploads;

    public ?Post $post = null;

    public string $title = '';

    public string $city_id = '';

    public string $excerpt = '';

    public string $body = '';

    public bool $is_published = true;

    public string $published_at = '';

    /** @var TemporaryUploadedFile|null */
    public $cover = null;

    /**
     * Fill the form when editing.
     */
    public function mount(?Post $post = null): void
    {
        $this->published_at = today()->toDateString();

        if (! $post?->exists) {
            return;
        }

        $this->post = $post;
        $this->fill([
            'title' => $post->title,
            'city_id' => (string) $post->city_id,
            'excerpt' => (string) $post->excerpt,
            'body' => $post->body,
            'is_published' => $post->is_published,
            'published_at' => $post->published_at?->toDateString() ?? today()->toDateString(),
        ]);
    }

    /**
     * @return Collection<int, City>
     */
    #[Computed]
    public function cities(): Collection
    {
        return City::query()->ordered()->get(['id', 'name', 'state_code']);
    }

    /**
     * Save the guide.
     */
    public function save(ImageOptimizer $images): void
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:160'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'body' => ['required', 'string', 'max:60000'],
            'is_published' => ['boolean'],
            'published_at' => ['required', 'date'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
        ], [
            'body.required' => 'Write the guide text.',
        ]);

        $post = $this->post ?? new Post(['user_id' => auth()->id()]);
        $coverPath = $post->cover_path;

        if ($this->cover) {
            if ($coverPath) {
                Storage::disk('public')->delete([$coverPath, str_replace('.webp', '-sm.webp', $coverPath)]);
            }

            $coverPath = $images->store($this->cover, 'guides')['path'];
        }

        $post->fill([
            'title' => $validated['title'],
            'slug' => $post->slug ?? $this->uniqueSlug($validated['title']),
            'city_id' => $validated['city_id'] ?: null,
            'excerpt' => $validated['excerpt'] ?: null,
            'body' => $validated['body'],
            'is_published' => $validated['is_published'],
            'published_at' => $validated['published_at'],
            'cover_path' => $coverPath,
        ])->save();

        Flux::toast(__('Guide saved.'), variant: 'success');
        $this->redirectRoute('dashboard.guides.index', navigate: true);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'guide';
        $slug = $base;
        $number = 2;

        while (Post::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$number++;
        }

        return $slug;
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div>
        <flux:link :href="route('dashboard.guides.index')" wire:navigate class="text-sm">← {{ __('All guides') }}</flux:link>
        <flux:heading size="xl" level="1" class="mt-2">{{ $post ? __('Edit guide') : __('Write a guide') }}</flux:heading>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6">
        <flux:input wire:model="title" :label="__('Title *')" :placeholder="__('e.g. The 5 best Austin neighborhoods for families')" />
        <div class="grid gap-5 sm:grid-cols-2">
            <flux:select wire:model="city_id" :label="__('City')" :description="__('Links the guide to a city and shows its homes below it.')">
                <flux:select.option value="">{{ __('No city (general tips)') }}</flux:select.option>
                @foreach ($this->cities as $city)
                    <flux:select.option value="{{ $city->id }}">{{ $city->name }}, {{ $city->state_code }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input type="date" wire:model="published_at" :label="__('Publish date')" :description="__('A future date keeps it hidden until then.')" />
        </div>
        <flux:textarea wire:model="excerpt" rows="2" :label="__('Short summary')" :description="__('One or two sentences for cards and Google. Optional.')" />
        <flux:textarea wire:model="body" rows="16" :label="__('Guide text *')" :description="__('Leave a blank line between paragraphs. Use ## for a heading, - for a list, **bold** and [link text](https://…).')" />
        <div class="grid gap-3">
            <flux:input type="file" wire:model="cover" accept="image/jpeg,image/png,image/webp" :label="__('Cover photo')" />
            <div wire:loading wire:target="cover"><flux:text>{{ __('Uploading…') }}</flux:text></div>
            @if ($cover && $cover->isPreviewable() && ! $errors->has('cover'))
                <img src="{{ $cover->temporaryUrl() }}" alt="" class="aspect-[16/9] w-80 rounded-lg border border-amber-400 object-cover">
            @elseif ($post?->cover_path)
                <img src="{{ $post->coverUrl(small: true) }}" alt="" class="aspect-[16/9] w-80 rounded-lg object-cover">
            @endif
        </div>
        <flux:switch wire:model="is_published" :label="__('Published (visible on the website)')" />
        <div class="flex items-center gap-3">
            <flux:button variant="primary" type="submit">{{ __('Save guide') }}</flux:button>
            <flux:button :href="route('dashboard.guides.index')" wire:navigate variant="ghost">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>
