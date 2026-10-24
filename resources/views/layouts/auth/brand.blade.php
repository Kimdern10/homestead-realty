@php
    $businessName = \App\Models\Setting::get('business_name');
    $tagline = \App\Models\Setting::get('tagline');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-[#faf8f5] antialiased dark:bg-[#0f1012]">
        <div class="grid min-h-dvh lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)]">
            <div class="flex flex-col px-6 py-8 sm:px-12 lg:px-16">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 text-lg font-bold tracking-tight text-zinc-900 dark:text-white">
                    <span class="flex size-9 items-center justify-center rounded-[11px] bg-[#d92228] text-white"><x-app-logo-icon class="size-5" /></span>
                    {{ $businessName }}
                </a>

                <div class="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center py-10 auth-rise">
                    {{ $slot }}
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-zinc-500">
                    <a href="{{ route('home') }}" class="hover:text-zinc-800 dark:hover:text-zinc-200">← {{ __('Back to the website') }}</a>
                    <a href="{{ route('privacy') }}" class="hover:text-zinc-800 dark:hover:text-zinc-200">{{ __('Privacy policy') }}</a>
                </div>
            </div>

            <div class="relative hidden overflow-hidden lg:block">
                <img src="{{ asset('images/auth-home.webp') }}" alt="" class="auth-photo absolute inset-0 size-full object-cover">
                <div class="absolute inset-0 bg-linear-to-t from-black/75 via-black/20 to-black/10"></div>
                <div class="absolute inset-x-12 bottom-12 text-white">
                    <p class="font-display text-4xl leading-tight xl:text-5xl">{{ $tagline }}</p>
                    <p class="mt-4 max-w-md text-sm text-white/80">{{ __('Save homes, keep your searches and book tours with a local agent, all in one place.') }}</p>
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
