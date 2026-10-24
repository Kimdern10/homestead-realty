@php
    $name = \App\Models\Setting::get('business_name');
    $words = explode(' ', $name, 2);
@endphp

<span class="brand__mark" aria-hidden="true">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m3 11 9-7 9 7"/><path d="M5 10v10h14V10"/><path d="M10 20v-5h4v5"/></svg>
</span>
<span class="brand__name">{{ $words[0] }}@if (isset($words[1]))<span> {{ $words[1] }}</span>@endif</span>
