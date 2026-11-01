<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Stripe Checkout through Stripe's API (no package needed).
 */
class StripeGateway implements PaymentGateway
{
    /**
     * How old a webhook may be before it is refused, in seconds.
     */
    public const int WEBHOOK_TOLERANCE = 300;

    public function name(): string
    {
        return 'stripe';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.stripe.secret'));
    }

    public function startCheckout(Payment $payment): string
    {
        $returnUrl = route('payments.show', $payment);

        $response = $this->send(fn (PendingRequest $http) => $http->asForm()->post('/checkout/sessions', [
            'mode' => 'payment',
            'client_reference_id' => $payment->reference,
            'customer_email' => $payment->payer_email,
            'success_url' => $returnUrl.'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $returnUrl.'?cancelled=1',
            'metadata' => ['reference' => $payment->reference, 'purpose' => $payment->purpose->value],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($payment->currency),
                    'unit_amount' => $payment->amount,
                    'product_data' => ['name' => $payment->description],
                ],
            ]],
        ]));

        $payment->forceFill(['gateway_reference' => $response['id'] ?? null, 'checkout_url' => $response['url'] ?? null])->save();

        return (string) ($response['url'] ?? throw new RuntimeException('Stripe did not return a payment page.'));
    }

    public function confirm(Payment $payment): array
    {
        if (blank($payment->gateway_reference)) {
            return ['paid' => false, 'amount' => 0, 'currency' => $payment->currency, 'gateway_reference' => null];
        }

        $session = $this->send(fn (PendingRequest $http) => $http->get('/checkout/sessions/'.$payment->gateway_reference));

        return [
            'paid' => ($session['payment_status'] ?? null) === 'paid',
            'amount' => (int) ($session['amount_total'] ?? 0),
            'currency' => strtoupper((string) ($session['currency'] ?? '')),
            'gateway_reference' => (string) ($session['payment_intent'] ?? $payment->gateway_reference),
        ];
    }

    public function readWebhook(Request $request): ?array
    {
        $this->verifySignature($request->getContent(), (string) $request->header('Stripe-Signature'));

        $event = $request->json()->all();
        $session = $event['data']['object'] ?? [];

        if (! in_array($event['type'] ?? null, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)
            || ($session['payment_status'] ?? null) !== 'paid'
            || blank($session['client_reference_id'] ?? null)) {
            return null;
        }

        return [
            'reference' => (string) $session['client_reference_id'],
            'amount' => (int) ($session['amount_total'] ?? 0),
            'currency' => strtoupper((string) ($session['currency'] ?? '')),
            'gateway_reference' => isset($session['payment_intent']) ? (string) $session['payment_intent'] : null,
        ];
    }

    /**
     * Stripe signs "timestamp.body" with the webhook secret (HMAC-SHA256).
     *
     * @throws InvalidWebhookSignature
     */
    private function verifySignature(string $payload, string $header): void
    {
        $secret = (string) config('services.stripe.webhook_secret');
        $parts = collect(explode(',', $header))->mapToGroups(function (string $part): array {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            return [$key => $value];
        });

        $timestamp = (int) ($parts->get('t')?->first() ?? 0);
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        $matches = $parts->get('v1', collect())->contains(fn (string $signature) => hash_equals($expected, $signature));

        if ($secret === '' || ! $matches || abs(time() - $timestamp) > self::WEBHOOK_TOLERANCE) {
            throw new InvalidWebhookSignature('The Stripe webhook signature is not valid.');
        }
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     * @return array<string, mixed>
     */
    private function send(callable $call): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Add your Stripe secret key (STRIPE_SECRET) to the .env file.');
        }

        try {
            $response = $call(Http::baseUrl('https://api.stripe.com/v1')->withToken((string) config('services.stripe.secret'))->acceptJson()->timeout(20));
        } catch (ConnectionException) {
            throw new RuntimeException('Stripe could not be reached. Please try again.');
        }

        if ($response->failed()) {
            throw new RuntimeException('Stripe said: '.($response->json('error.message') ?? 'error '.$response->status()));
        }

        return (array) $response->json();
    }
}
