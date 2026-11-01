<x-mail::message>
# {{ $payment->purpose->label() }} paid

**{{ $payment->payer_name }}** paid **{{ $payment->amountLabel() }}** for: {{ $payment->description }}.

<x-mail::table>
| | |
| :-- | :-- |
| Email | {{ $payment->payer_email }} |
| Phone | {{ $payment->payer_phone ?: '—' }} |
@if ($payment->listing)
| Home | {{ $payment->listing->fullAddress() }} |
@endif
| Paid with | {{ ucfirst($payment->gateway) }} · {{ $payment->reference }} |
</x-mail::table>

<x-mail::button :url="$dashboardUrl" color="error">
Open in your dashboard
</x-mail::button>

{{ \App\Models\Setting::get('business_name') }}
</x-mail::message>
