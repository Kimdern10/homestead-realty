<x-mail::message>
# New homes for “{{ $search->name }}”

Hi {{ Str::before($search->user->name, ' ') }}, {{ $total === 1 ? 'a new home matches' : $total.' new homes match' }} your saved search ({{ $search->summary() }}).

@foreach ($homes as $home)
**[{{ $home->priceLabel() }} · {{ $home->address }}]({{ route('listings.show', $home) }})**
{{ $home->locality() }}@if ($home->facts()) · {{ implode(' · ', $home->facts()) }}@endif

@endforeach

<x-mail::button :url="$searchUrl" color="error">
See all matching homes
</x-mail::button>

You get this email because you saved this search. [Turn alerts off]({{ $manageUrl }}) any time.

{{ \App\Models\Setting::get('business_name') }}
</x-mail::message>
