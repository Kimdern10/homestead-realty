<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payments\PaymentManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class PaymentController extends Controller
{
    /**
     * Where the visitor lands after paying (or cancelling). The payment is double-checked with
     * the provider here, so it shows as paid even if the webhook is late.
     */
    public function show(Request $request, Payment $payment, PaymentManager $payments): View
    {
        $checkKey = 'payment-check:'.$payment->id;

        if ($payment->status === PaymentStatus::Pending && ! $request->boolean('cancelled') && ! RateLimiter::tooManyAttempts($checkKey, 10)) {
            RateLimiter::hit($checkKey, 600);
            $payment = $payments->refresh($payment);
        }

        return view('payments.show', [
            'payment' => $payment->load('listing.city'),
            'cancelled' => $request->boolean('cancelled') && ! $payment->isPaid(),
        ]);
    }
}
