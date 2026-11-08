<x-mail::message>
# A home owner sent in a home

**{{ $listing->owner_name }}** would like to {{ $listing->status->isRental() ? 'rent out' : 'sell' }} {{ $listing->address }}, {{ $listing->locality() }}.

<x-mail::table>
| | |
| :-- | :-- |
| Price | {{ $listing->priceLabel() }} |
| Home | {{ $listing->typeLabel() }}@if ($listing->facts()) · {{ implode(' · ', $listing->facts()) }}@endif |
| Phone | {{ $listing->owner_phone }} |
| Email | {{ $listing->owner_email }} |
</x-mail::table>

It is hidden until an admin approves it.

<x-mail::button :url="$reviewUrl" color="error">
Check and approve
</x-mail::button>

{{ \App\Models\Setting::get('business_name') }}
</x-mail::message>
