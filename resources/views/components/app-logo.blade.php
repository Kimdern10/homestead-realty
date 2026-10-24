@props([
    'sidebar' => false,
])

@php($businessName = \App\Models\Setting::get('business_name'))

@if($sidebar)
    <flux:sidebar.brand :name="$businessName" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-[#d92228] text-white">
            <x-app-logo-icon class="size-5" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="$businessName" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-[#d92228] text-white">
            <x-app-logo-icon class="size-5" />
        </x-slot>
    </flux:brand>
@endif
