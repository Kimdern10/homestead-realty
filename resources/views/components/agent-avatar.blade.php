@props(['agent', 'size' => null])

@if ($photo = $agent->photoUrl())
    <img {{ $attributes->class(['avatar', 'avatar--'.$size => $size]) }} src="{{ $photo }}" alt="{{ $agent->name }}" loading="lazy">
@else
    <span {{ $attributes->class(['avatar', 'avatar--'.$size => $size]) }} aria-hidden="true">{{ $agent->initials() }}</span>
@endif
