@props(['agent'])

<a {{ $attributes->class(['agent-card']) }} href="{{ route('agents.show', $agent) }}">
    <x-agent-avatar :agent="$agent" />
    <div>
        <h3>{{ $agent->name }}</h3>
        <p>{{ $agent->title ?: 'Real estate agent' }}</p>
        @isset($agent->active_count)
            <p>{{ $agent->active_count }} {{ Str::plural('active listing', $agent->active_count) }}</p>
        @endisset
    </div>
</a>
