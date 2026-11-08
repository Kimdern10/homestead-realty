<?php

use App\Models\Post;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Guides')] class extends Component {
    use WithPagination;

    /**
     * @return LengthAwarePaginator<int, Post>
     */
    #[Computed]
    public function posts(): LengthAwarePaginator
    {
        return Post::query()->with('city:id,name')->latest('published_at')->latest('id')->paginate(20);
    }

    /**
     * Show or hide a guide.
     */
    public function togglePublished(int $id): void
    {
        $post = Post::query()->findOrFail($id);
        $post->update(['is_published' => ! $post->is_published, 'published_at' => $post->published_at ?? now()]);
        unset($this->posts);
    }

    /**
     * Delete a guide and its cover photo.
     */
    public function delete(int $id): void
    {
        $post = Post::query()->findOrFail($id);

        if ($post->cover_path) {
            Storage::disk('public')->delete([$post->cover_path, str_replace('.webp', '-sm.webp', $post->cover_path)]);
        }

        $post->delete();
        unset($this->posts);
        Flux::toast(__('Guide deleted.'));
    }
}; ?>

<div class="mx-auto flex w-full max-w-5xl flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Guides') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Neighborhood guides and tips. They help visitors and bring in visitors from Google.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" :href="route('dashboard.guides.create')" wire:navigate>{{ __('Write a guide') }}</flux:button>
    </div>

    <flux:table :paginate="$this->posts">
        <flux:table.columns>
            <flux:table.column>{{ __('Title') }}</flux:table.column>
            <flux:table.column>{{ __('City') }}</flux:table.column>
            <flux:table.column>{{ __('Date') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->posts as $post)
                <flux:table.row :key="$post->id">
                    <flux:table.cell class="max-w-sm whitespace-normal font-medium">{{ $post->title }}</flux:table.cell>
                    <flux:table.cell>{{ $post->city?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $post->published_at?->format('M j, Y') }}</flux:table.cell>
                    <flux:table.cell>
                        @if (! $post->is_published)
                            <flux:badge size="sm" color="zinc">{{ __('Draft') }}</flux:badge>
                        @elseif ($post->published_at?->isFuture())
                            <flux:badge size="sm" color="amber">{{ __('Scheduled') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="green">{{ __('Live') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="text-end">
                        <flux:dropdown>
                            <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Actions')" />
                            <flux:menu>
                                <flux:menu.item icon="pencil" :href="route('dashboard.guides.edit', $post)" wire:navigate>{{ __('Edit') }}</flux:menu.item>
                                @if ($post->is_published)
                                    <flux:menu.item icon="arrow-top-right-on-square" :href="route('guides.show', $post)" target="_blank">{{ __('View') }}</flux:menu.item>
                                @endif
                                <flux:menu.item icon="eye" wire:click="togglePublished({{ $post->id }})">{{ $post->is_published ? __('Unpublish') : __('Publish') }}</flux:menu.item>
                                <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $post->id }})" wire:confirm="{{ __('Delete this guide?') }}">{{ __('Delete') }}</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('No guides yet. Write your first one.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
