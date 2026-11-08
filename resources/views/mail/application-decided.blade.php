<x-mail::message>
@if ($approved)
# Good news, {{ Str::before($application->name, ' ') }}!

Your application for **{{ $application->listing->address }}, {{ $application->listing->locality() }}** has been **approved**.

@if ($depositUrl)
To secure the home, pay the **{{ $depositAmount }} holding deposit**. The home is then taken off the market for you, and {{ Str::before($application->listing->agent->name, ' ') }} will send you the lease.

<x-mail::button :url="$depositUrl" color="error">
Pay the holding deposit
</x-mail::button>

This link works for {{ \App\Mail\ApplicationDecided::LINK_DAYS }} days. The deposit usually goes towards your first month's rent or security deposit.
@else
{{ Str::before($application->listing->agent->name, ' ') }} will contact you shortly about the lease and how to pay.
@endif
@else
# Update on your application

Thank you for applying for **{{ $application->listing->address }}**. Unfortunately the landlord has chosen another applicant this time.

<x-mail::button :url="$searchUrl" color="error">
See other homes for rent
</x-mail::button>
@endif

Questions? Reply to this email to reach {{ $application->listing->agent->name }}.

{{ \App\Models\Setting::get('business_name') }}
</x-mail::message>
