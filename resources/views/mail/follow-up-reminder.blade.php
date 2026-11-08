<x-mail::message>
# Leads to follow up today

Morning {{ Str::before($agent->name, ' ') }}. You planned to get back to {{ $enquiries->count() === 1 ? 'this person' : 'these people' }}:

<x-mail::table>
| Who | About | Stage | Due |
| :-- | :-- | :-- | :-- |
@foreach ($enquiries as $enquiry)
| [{{ $enquiry->name }}]({{ route('dashboard.enquiries.show', $enquiry) }}) | {{ $enquiry->listing?->address ?? $enquiry->type->label() }} | {{ $enquiry->status->label() }} | {{ $enquiry->follow_up_on?->isToday() ? 'Today' : $enquiry->follow_up_on?->format('M j') }} |
@endforeach
</x-mail::table>

<x-mail::button :url="$dashboardUrl" color="error">
Open your leads
</x-mail::button>

{{ \App\Models\Setting::get('business_name') }}
</x-mail::message>
