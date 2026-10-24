@props(['agent'])

<a {{ $attributes->class(['agent-card']) }} wire:navigate.hover href="{{ route('agents.show', $agent) }}">
    <x-agent-avatar :agent="$agent" />
    <div>
        <h3>{{ $agent->name }}</h3>
        <p>{{ $agent->title ?: 'Real estate agent' }}</p>
        @isset($agent->active_count)
            <p>{{ $agent->active_count }} active · {{ number_format($agent->soldCount()) }} sold</p>
        @endisset
    </div>
</a>
