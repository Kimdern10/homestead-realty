<x-mail::message>
# {{ $enquiry->type === \App\Enums\EnquiryType::Tour ? 'New tour request' : 'New message' }}

**{{ $enquiry->name }}** {{ $enquiry->type === \App\Enums\EnquiryType::Tour ? 'would like to tour' : 'sent a message about' }} {{ $enquiry->listing ? $enquiry->listing->fullAddress() : 'your listings' }}.

<x-mail::table>
| | |
| :-- | :-- |
@if ($enquiry->tourSummary())
| When | {{ $enquiry->tourSummary() }} |
@endif
| Email | {{ $enquiry->email }} |
| Phone | {{ $enquiry->phone ?: '—' }} |
@if ($enquiry->listing)
| Home | {{ $enquiry->listing->priceLabel() }} · {{ implode(' · ', $enquiry->listing->facts()) }} |
@endif
</x-mail::table>

@if (filled($enquiry->message))
**Their message**

{{ $enquiry->message }}
@endif

<x-mail::button :url="$dashboardUrl" color="error">
Open in your dashboard
</x-mail::button>

Reply to this email to answer {{ Str::before($enquiry->name, ' ') }} directly.@if ($listingUrl) [View the listing]({{ $listingUrl }})@endif

{{ \App\Models\Setting::get('business_name') }}
</x-mail::message>
