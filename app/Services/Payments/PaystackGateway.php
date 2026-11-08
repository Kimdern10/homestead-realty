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
 * Paystack's hosted payment page through Paystack's API (no package needed).
 */
class PaystackGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'paystack';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.paystack.secret'));
    }

    public function startCheckout(Payment $payment): string
    {
        $response = $this->send(fn (PendingRequest $http) => $http->post('/transaction/initialize', [
            'email' => $payment->payer_email,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'reference' => $payment->reference,
            'callback_url' => route('payments.show', $payment),
            'metadata' => ['purpose' => $payment->purpose->value, 'description' => $payment->description],
        ]));

        $url = $response['data']['authorization_url'] ?? null;

        $payment->forceFill(['gateway_reference' => $response['data']['access_code'] ?? null, 'checkout_url' => $url])->save();

        return (string) ($url ?? throw new RuntimeException('Paystack did not return a payment page.'));
    }

    public function confirm(Payment $payment): array
    {
        $data = $this->send(fn (PendingRequest $http) => $http->get('/transaction/verify/'.rawurlencode($payment->reference)))['data'] ?? [];

        return [
            'paid' => ($data['status'] ?? null) === 'success',
            'amount' => (int) ($data['amount'] ?? 0),
            'currency' => strtoupper((string) ($data['currency'] ?? '')),
            'gateway_reference' => isset($data['id']) ? (string) $data['id'] : null,
        ];
    }

    public function readWebhook(Request $request): ?array
    {
        $secret = (string) config('services.paystack.secret');
        $expected = hash_hmac('sha512', $request->getContent(), $secret);

        if ($secret === '' || ! hash_equals($expected, (string) $request->header('x-paystack-signature'))) {
            throw new InvalidWebhookSignature('The Paystack webhook signature is not valid.');
        }

        $event = $request->json()->all();
        $data = $event['data'] ?? [];

        if (($event['event'] ?? null) !== 'charge.success' || ($data['status'] ?? null) !== 'success' || blank($data['reference'] ?? null)) {
            return null;
        }

        return [
            'reference' => (string) $data['reference'],
            'amount' => (int) ($data['amount'] ?? 0),
            'currency' => strtoupper((string) ($data['currency'] ?? '')),
            'gateway_reference' => isset($data['id']) ? (string) $data['id'] : null,
        ];
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     * @return array<string, mixed>
     */
    private function send(callable $call): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Add your Paystack secret key (PAYSTACK_SECRET_KEY) to the .env file.');
        }

        try {
            $response = $call(Http::baseUrl('https://api.paystack.co')->withToken((string) config('services.paystack.secret'))->acceptJson()->timeout(20));
        } catch (ConnectionException) {
            throw new RuntimeException('Paystack could not be reached. Please try again.');
        }

        if ($response->failed() || $response->json('status') === false) {
            throw new RuntimeException('Paystack said: '.($response->json('message') ?? 'error '.$response->status()));
        }

        return (array) $response->json();
    }
}
