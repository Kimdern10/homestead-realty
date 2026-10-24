@props([
    'title',
    'description',
])

<div class="flex w-full flex-col gap-2">
    <h1 class="font-display text-3xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ $title }}</h1>
    <flux:subheading>{{ $description }}</flux:subheading>
</div>
