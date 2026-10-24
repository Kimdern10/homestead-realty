@props(['listing', 'large' => false])

@php
    $user = auth()->user();
    $saved = $user !== null && in_array($listing->id, $user->favoriteListingIds(), true);
    $label = $saved ? 'Remove from saved homes' : 'Save this home';
@endphp

@if ($user)
    <button type="button" {{ $attributes->class(['fav-btn', 'fav-btn--lg' => $large]) }}
        data-favorite="{{ route('favorites.toggle', $listing) }}" aria-pressed="{{ $saved ? 'true' : 'false' }}" aria-label="{{ $label }}" title="{{ $label }}">
        <x-icon name="heart" />
        @if ($large)<span data-favorite-text>{{ $saved ? 'Saved' : 'Save' }}</span>@endif
    </button>
@else
    <a {{ $attributes->class(['fav-btn', 'fav-btn--lg' => $large]) }} href="{{ route('login') }}" aria-label="Sign in to save this home" title="Sign in to save this home">
        <x-icon name="heart" />
        @if ($large)<span>Save</span>@endif
    </a>
@endif
