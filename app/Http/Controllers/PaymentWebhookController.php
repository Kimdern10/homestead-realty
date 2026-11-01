<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\Payments\InvalidWebhookSignature;
use App\Services\Payments\PaymentManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives "payment succeeded" messages from Stripe and Paystack.
 * Each message is checked with the provider's signature before anything changes.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway, PaymentManager $payments): JsonResponse
    {
        $provider = $payments->gateway($gateway);

        abort_if($provider === null, 404);

        try {
            $event = $provider->readWebhook($request);
        } catch (InvalidWebhookSignature) {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        if ($event === null) {
            return response()->json(['message' => 'Ignored.']);
        }

        $payment = Payment::query()->where('reference', $event['reference'])->where('gateway', $provider->name())->first();

        if ($payment === null) {
            return response()->json(['message' => 'Unknown payment.']);
        }

        $payments->markPaid($payment, $event['amount'], $event['currency'], $event['gateway_reference']);

        return response()->json(['message' => 'OK']);
    }
}
