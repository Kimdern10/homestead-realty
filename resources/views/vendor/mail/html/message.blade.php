@php($site = \App\Models\Setting::values())
<x-mail::layout>
{{-- Header: logo and business name --}}
<x-slot:header>
<x-mail::header :url="route('home')">
{{ $site['business_name'] }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer: how to reach the office --}}
<x-slot:footer>
<x-mail::footer>
**{{ $site['business_name'] }}**{{ filled($site['tagline']) ? ' · '.$site['tagline'] : '' }}

{{ implode(' · ', array_filter([$site['address'] ?: null, $site['phone'] ?: null, $site['email'] ?: null])) }}

[Visit our website]({{ route('home') }}) · [Homes for sale]({{ route('search', ['status' => 'for_sale']) }}) · [Homes for rent]({{ route('search', ['status' => 'for_rent']) }}) · [Privacy]({{ route('privacy') }})

© {{ date('Y') }} {{ $site['business_name'] }}. Equal Housing Opportunity.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
