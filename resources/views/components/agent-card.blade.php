@props(['agent'])

<a {{ $attributes->class(['agent-card']) }} wire:navigate.hover href="{{ route('agents.show', $agent) }}">
    <x-agent-avatar :agent="$agent" />
    <div>
        <h3>{{ $agent->name }}</h3>
        <p>{{ $agent->title ?: 'Real estate agent' }}</p>
        @isset($agent->active_count)
            <p>{{ $agent->active_count }} active · {{ number_format($agent->soldCount()) }} sold</p>
        @endisset
        @if (($agent->reviews_count ?? 0) > 0)
            <p class="rating-line"><x-stars :rating="$agent->rating_avg" /> {{ number_format((float) $agent->rating_avg, 1) }} ({{ $agent->reviews_count }})</p>
        @endif
    </div>
</a>
