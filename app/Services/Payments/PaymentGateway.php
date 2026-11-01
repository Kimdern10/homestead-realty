<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A card payment provider (Stripe or Paystack). The visitor pays on the provider's own
 * checkout page, so card details never touch this website.
 */
interface PaymentGateway
{
    /**
     * The key stored on payments, e.g. "stripe".
     */
    public function name(): string;

    /**
     * Whether the secret key has been added to .env.
     */
    public function isConfigured(): bool;

    /**
     * Start a checkout and return the provider's payment page address.
     *
     * @throws RuntimeException
     */
    public function startCheckout(Payment $payment): string;

    /**
     * Ask the provider whether the payment went through.
     *
     * @return array{paid: bool, amount: int, currency: string, gateway_reference: string|null}
     *
     * @throws RuntimeException
     */
    public function confirm(Payment $payment): array;

    /**
     * Check a webhook really came from the provider and read it.
     * Returns the paid payment's reference, amount and currency, or null for events we don't use.
     *
     * @return array{reference: string, amount: int, currency: string, gateway_reference: string|null}|null
     *
     * @throws InvalidWebhookSignature
     */
    public function readWebhook(Request $request): ?array;
}
