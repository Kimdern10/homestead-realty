<div class="mx-auto flex w-full max-w-5xl items-start gap-8 max-md:flex-col">
    <aside class="w-full md:sticky md:top-6 md:w-60">
        <div class="mb-4">
            <h2 class="font-display text-3xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ __('Account') }}</h2>
            <flux:text class="mt-1 text-sm">{{ auth()->user()->email }}</flux:text>
        </div>
        <flux:navlist aria-label="{{ __('Settings') }}">
            <flux:navlist.item icon="user" :href="route('profile.edit')" :current="request()->routeIs('profile.edit')" wire:navigate>{{ __('Profile') }}</flux:navlist.item>
            <flux:navlist.item icon="lock-closed" :href="route('security.edit')" :current="request()->routeIs('security.edit')" wire:navigate>{{ __('Password & security') }}</flux:navlist.item>
            <flux:navlist.item icon="swatch" :href="route('appearance.edit')" :current="request()->routeIs('appearance.edit')" wire:navigate>{{ __('Appearance') }}</flux:navlist.item>
        </flux:navlist>
    </aside>

    <section class="w-full flex-1 rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg">{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-6 w-full max-w-lg">
            {{ $slot }}
        </div>
    </section>
</div>
