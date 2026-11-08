<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>


<link rel="icon" href="/favicon.svg" type="image/svg+xml">


@fonts('manrope')
@fonts('fraunces')

@vite(['resources/css/app.css', 'resources/js/app.js'])
{{-- Light mode first; dark only when the person picks it in Settings › Appearance (there is no "follow the device" option). --}}
<script>try { if (localStorage.getItem('flux.appearance') !== 'dark') { localStorage.setItem('flux.appearance', 'light'); } } catch (e) {}</script>
@fluxAppearance
