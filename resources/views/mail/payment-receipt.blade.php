<x-mail::message>
# Thank you, {{ Str::before($payment->payer_name, ' ') }}

We received your payment.

<x-mail::table>
| | |
| :-- | :-- |
| For | {{ $payment->description }} |
| Amount | {{ $payment->amountLabel() }} |
| Date | {{ $payment->paid_at?->format('M j, Y g:ia') }} |
| Reference | {{ $payment->reference }} |
</x-mail::table>

@if ($payment->purpose === \App\Enums\PaymentPurpose::ApplicationFee)
Your rental application is now with the agent, who will be in touch soon.
@elseif ($payment->purpose === \App\Enums\PaymentPurpose::HoldingDeposit)
The home is now on hold for you while the agent completes the next steps.
@elseif ($payment->purpose === \App\Enums\PaymentPurpose::FeaturedListing)
Your listing is now featured on the home page.
@else
We'll contact you to confirm the details of your booking.
@endif

<x-mail::button :url="$url" color="error">
View your payment
</x-mail::button>

Questions? Reply to this email{{ filled($phone) ? ' or call '.$phone : '' }}.

{{ $business }}
</x-mail::message>
