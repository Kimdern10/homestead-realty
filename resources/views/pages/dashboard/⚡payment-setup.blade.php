<?php

use App\Models\Setting;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\PaystackGateway;
use App\Services\Payments\StripeGateway;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Payments setup')] class extends Component {
    public string $payment_gateway = 'none';

    public string $payment_currency = 'USD';

    public string $application_fee = '';

    public string $holding_deposit = '';

    public string $featured_price = '';

    public string $featured_days = '';

    /**
     * Load the saved payment settings.
     */
    public function mount(): void
    {
        $this->fill(collect(Setting::values())->only([
            'payment_gateway', 'payment_currency', 'application_fee', 'holding_deposit', 'featured_price', 'featured_days',
        ])->all());
    }

    /**
     * Save the payment settings.
     */
    public function save(): void
    {
        $validated = $this->validate([
            'payment_gateway' => ['required', Rule::in(['none', 'stripe', 'paystack'])],
            'payment_currency' => ['required', Rule::in($this->payment_gateway === 'paystack' ? ['USD', 'NGN'] : ['USD'])],
            'application_fee' => ['required', 'integer', 'min:0', 'max:100000'],
            'holding_deposit' => ['required', 'integer', 'min:0', 'max:1000000'],
            'featured_price' => ['required', 'integer', 'min:0', 'max:100000'],
            'featured_days' => ['required', 'integer', 'min:1', 'max:365'],
        ], ['payment_currency.in' => 'Stripe here charges in US dollars. Choose USD, or use Paystack for naira.']);

        Setting::put($validated);

        Flux::toast(__('Payment settings saved.'), variant: 'success');
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Payments setup') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Visitors pay on Stripe’s or Paystack’s own secure page, so card details never touch this website. Each payment is confirmed by a webhook from the provider.') }}</flux:text>
    </div>

    @php($stripe = app(StripeGateway::class))
    @php($paystack = app(PaystackGateway::class))

    <form wire:submit="save" class="flex flex-col gap-8">
        <flux:fieldset>
            <flux:legend>{{ __('Payment provider') }}</flux:legend>
            <flux:radio.group wire:model.live="payment_gateway" variant="cards" class="grid gap-3 sm:grid-cols-3">
                <flux:radio value="none" :label="__('Off')" :description="__('No online payments. Applications are free.')" />
                <flux:radio value="stripe" label="Stripe" :description="$stripe->isConfigured() ? __('Key added ✓') : __('Needs STRIPE_SECRET')" />
                <flux:radio value="paystack" label="Paystack" :description="$paystack->isConfigured() ? __('Key added ✓') : __('Needs PAYSTACK_SECRET_KEY')" />
            </flux:radio.group>

            @if ($payment_gateway !== 'none')
                @php($chosen = $payment_gateway === 'stripe' ? $stripe : $paystack)
                <div class="mt-5 grid gap-4">
                    @unless ($chosen->isConfigured())
                        <flux:callout icon="key" color="amber">
                            <flux:callout.heading>{{ __('Add your :name keys to the .env file', ['name' => ucfirst($payment_gateway)]) }}</flux:callout.heading>
                            <flux:callout.text>
                                @if ($payment_gateway === 'stripe')
                                    {{ __('In Stripe: Developers › API keys, copy the secret key. Use the test key (sk_test_…) until you’re ready.') }}
                                    <code class="mt-2 block rounded bg-zinc-100 px-2 py-1 dark:bg-zinc-800">STRIPE_SECRET=sk_test_…<br>STRIPE_WEBHOOK_SECRET=whsec_…</code>
                                @else
                                    {{ __('In Paystack: Settings › API Keys & Webhooks, copy the secret key. Use the test key (sk_test_…) until you’re ready.') }}
                                    <code class="mt-2 block rounded bg-zinc-100 px-2 py-1 dark:bg-zinc-800">PAYSTACK_SECRET_KEY=sk_test_…</code>
                                @endif
                                {{ __('Until then, visitors can’t pay and payment buttons stay hidden.') }}
                            </flux:callout.text>
                        </flux:callout>
                    @endunless

                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:heading size="sm">{{ __('Webhook address') }}</flux:heading>
                        <flux:text class="mt-1 text-sm">
                            @if ($payment_gateway === 'stripe')
                                {{ __('In Stripe: Developers › Webhooks › Add endpoint. Paste this address and choose the event “checkout.session.completed”. Then copy the signing secret into STRIPE_WEBHOOK_SECRET.') }}
                            @else
                                {{ __('In Paystack: Settings › API Keys & Webhooks. Paste this address into “Live/Test Webhook URL”.') }}
                            @endif
                        </flux:text>
                        <flux:input class="mt-3" readonly copyable :value="route('payments.webhook', $payment_gateway)" />
                        <flux:text class="mt-2 text-xs">{{ __('Webhooks only reach a live website. Locally, payments are still confirmed when the visitor returns to the site.') }}</flux:text>
                    </div>

                    <flux:select wire:model="payment_currency" :label="__('Currency')" class="max-w-60">
                        <flux:select.option value="USD">US dollar ($)</flux:select.option>
                        @if ($payment_gateway === 'paystack')
                            <flux:select.option value="NGN">Nigerian naira (₦)</flux:select.option>
                        @endif
                    </flux:select>
                    @if ($payment_gateway === 'paystack' && $payment_currency === 'USD')
                        <flux:text class="text-sm">{{ __('Paystack accepts US dollars only on accounts approved for USD. Check your Paystack dashboard.') }}</flux:text>
                    @endif
                </div>
            @endif
        </flux:fieldset>

        <flux:fieldset>
            <flux:legend>{{ __('Prices') }}</flux:legend>
            <flux:text class="mb-4 text-sm">{{ __('Whole amounts in your currency. Set a price to 0 to turn that payment off. Service prices are set on the Services page.') }}</flux:text>
            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input wire:model="application_fee" type="number" min="0" :label="__('Rental application fee')" :description="__('Paid with every rental application.')" />
                <flux:input wire:model="holding_deposit" type="number" min="0" :label="__('Holding deposit')" :description="__('Marks the rental as pending once paid.')" />
                <flux:input wire:model="featured_price" type="number" min="0" :label="__('Featured listing price')" :description="__('What an agent pays to feature a home.')" />
                <flux:input wire:model="featured_days" type="number" min="1" :label="__('Featured for (days)')" />
            </div>
        </flux:fieldset>

        <div><flux:button variant="primary" type="submit">{{ __('Save payment settings') }}</flux:button></div>
    </form>
</div>
