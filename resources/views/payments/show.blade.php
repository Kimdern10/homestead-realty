<x-layouts::site :title="$payment->isPaid() ? 'Payment received' : 'Your payment'" :noindex="true">
    <section class="section">
        <div class="container" style="max-width: 640px">
            <div class="panel pay-status">
                @if ($payment->isPaid())
                    <span class="sent__icon pay-status__icon"><x-icon name="check" /></span>
                    <h1>Payment received</h1>
                    <p class="muted">Thank you, {{ Str::before($payment->payer_name, ' ') }}. A receipt is on its way to {{ $payment->payer_email }}.</p>
                @elseif ($cancelled)
                    <span class="pay-status__icon pay-status__icon--muted"><x-icon name="x" /></span>
                    <h1>Payment cancelled</h1>
                    <p class="muted">No money was taken. You can try again whenever you're ready.</p>
                @else
                    <span class="pay-status__icon pay-status__icon--muted"><x-icon name="clock" /></span>
                    <h1>Waiting for your payment</h1>
                    <p class="muted">If you've just paid, it can take a minute to show here. Refresh this page shortly.</p>
                @endif

                <dl class="facts-grid" style="margin-top: 20px; text-align: left">
                    <div class="fact"><dt>For</dt><dd>{{ $payment->description }}</dd></div>
                    <div class="fact"><dt>Amount</dt><dd>{{ $payment->amountLabel() }}</dd></div>
                    <div class="fact"><dt>Reference</dt><dd style="font-size: 13px; word-break: break-all">{{ $payment->reference }}</dd></div>
                    <div class="fact"><dt>Status</dt><dd>{{ $payment->status->label() }}</dd></div>
                </dl>

                <div class="pay-status__actions">
                    @if (! $payment->isPaid() && $payment->checkout_url)
                        <a class="btn btn--primary" href="{{ $payment->checkout_url }}" data-no-navigate>Pay now</a>
                    @endif
                    @if ($payment->listing)
                        <a class="btn btn--outline" href="{{ route('listings.show', $payment->listing) }}">Back to the home</a>
                    @else
                        <a class="btn btn--outline" href="{{ route('home') }}">Back to the website</a>
                    @endif
                </div>
            </div>
        </div>
    </section>
</x-layouts::site>
