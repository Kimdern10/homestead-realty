<x-mail::message>
# Good news, {{ Str::before($user->name, ' ') }}

The price went down on {{ $homes->count() === 1 ? 'a home' : 'homes' }} you saved.

@foreach ($homes as $home)
@php($old = $oldPrices[$home->id] ?? $home->price)
**[{{ $home->address }}, {{ $home->locality() }}]({{ route('listings.show', $home) }})**
Now **{{ $home->priceLabel() }}**, was ~~${{ number_format($old) }}~~ (down ${{ number_format(max(0, $old - $home->price)) }})

@endforeach

<x-mail::button :url="$savedUrl" color="error">
See your saved homes
</x-mail::button>

You get this email because you saved these homes. Remove a home from your saved list to stop updates about it.

{{ \App\Models\Setting::get('business_name') }}
</x-mail::message>
