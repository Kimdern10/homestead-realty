<x-mail::message>
# Your home is live

Hi {{ Str::before((string) $listing->owner_name, ' ') }}, {{ $listing->address }} is now on our website.

<x-mail::button :url="$listingUrl" color="error">
See your listing
</x-mail::button>

{{ $listing->agent->name }} will pass on every message and tour request, and can help with showings. Reply to this email or call {{ $listing->agent->phone ?: \App\Models\Setting::get('phone') }} with any questions.

{{ \App\Models\Setting::get('business_name') }}
</x-mail::message>
