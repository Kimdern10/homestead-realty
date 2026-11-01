<?php

namespace App\Services\Payments;

use App\Enums\ApplicationStatus;
use App\Enums\ListingStatus;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Mail\PaymentAlert;
use App\Mail\PaymentReceipt;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\RentalApplication;
use App\Models\ServiceBooking;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Creates payments, sends visitors to Stripe or Paystack, and applies what a payment
 * pays for once the provider confirms it (by webhook or when the visitor comes back).
 */
class PaymentManager
{
    /**
     * The provider the admin chose, or null when online payments are off.
     */
    public function gateway(?string $name = null): ?PaymentGateway
    {
        return match ($name ?? Setting::get('payment_gateway')) {
            'stripe' => app(StripeGateway::class),
            'paystack' => app(PaystackGateway::class),
            default => null,
        };
    }

    /**
     * Whether visitors can pay online right now.
     */
    public function isEnabled(): bool
    {
        return $this->gateway()?->isConfigured() ?? false;
    }

    /**
     * The price set by the admin for a payment type, in whole currency units (0 = not offered).
     * Services have their own prices, set on each service.
     */
    public function price(PaymentPurpose $purpose): int
    {
        return match ($purpose) {
            PaymentPurpose::ApplicationFee => (int) Setting::get('application_fee'),
            PaymentPurpose::HoldingDeposit => (int) Setting::get('holding_deposit'),
            PaymentPurpose::FeaturedListing => (int) Setting::get('featured_price'),
            PaymentPurpose::Service => 0,
        };
    }

    /**
     * Whether a payment type is offered: payments on and (except services) a price above zero.
     */
    public function offers(PaymentPurpose $purpose): bool
    {
        return $this->isEnabled() && ($purpose === PaymentPurpose::Service || $this->price($purpose) > 0);
    }

    public function currency(): string
    {
        return Setting::get('payment_currency') ?: 'USD';
    }

    /**
     * "$45" or "₦45,000".
     */
    public function money(int $wholeUnits): string
    {
        return ($this->currency() === 'NGN' ? '₦' : '$').number_format($wholeUnits);
    }

    /**
     * Record a payment and return the provider's payment page to send the visitor to.
     *
     * @param  array{name: string, email: string, phone?: string|null, user_id?: int|null}  $payer
     *
     * @throws RuntimeException when payments are off or the provider fails
     */
    public function checkout(PaymentPurpose $purpose, int $wholeUnits, string $description, array $payer, ?Listing $listing = null, ?Model $payable = null): string
    {
        $gateway = $this->gateway();

        if ($gateway === null || ! $gateway->isConfigured() || $wholeUnits <= 0) {
            throw new RuntimeException('Online payments are not available right now. Please contact the office.');
        }

        $payment = Payment::query()->create([
            'reference' => Payment::newReference(),
            'gateway' => $gateway->name(),
            'purpose' => $purpose,
            'status' => PaymentStatus::Pending,
            'amount' => $wholeUnits * 100,
            'currency' => $this->currency(),
            'description' => $description,
            'listing_id' => $listing?->id,
            'user_id' => $payer['user_id'] ?? null,
            'payable_type' => $payable?->getMorphClass(),
            'payable_id' => $payable?->getKey(),
            'payer_name' => $payer['name'],
            'payer_email' => strtolower($payer['email']),
            'payer_phone' => $payer['phone'] ?? null,
        ]);

        return $gateway->startCheckout($payment);
    }

    /**
     * Ask the provider about a pending payment and record it if it was paid.
     */
    public function refresh(Payment $payment): Payment
    {
        if ($payment->isPaid()) {
            return $payment;
        }

        try {
            $result = $this->gateway($payment->gateway)?->confirm($payment);
        } catch (RuntimeException $exception) {
            report($exception);

            return $payment;
        }

        if ($result !== null && $result['paid']) {
            $this->markPaid($payment, $result['amount'], $result['currency'], $result['gateway_reference']);
        }

        return $payment->refresh();
    }

    /**
     * Record a confirmed payment once, then apply what it pays for and email everyone.
     * Calling it again for the same payment does nothing, so webhooks may repeat safely.
     */
    public function markPaid(Payment $payment, int $amount, string $currency, ?string $gatewayReference = null): bool
    {
        if ($amount !== $payment->amount || strtoupper($currency) !== strtoupper($payment->currency)) {
            report(new RuntimeException("Payment {$payment->reference} was confirmed for {$amount} {$currency}, expected {$payment->amount} {$payment->currency}."));

            return false;
        }

        $applied = DB::transaction(function () use ($payment, $gatewayReference): bool {
            $locked = Payment::query()->lockForUpdate()->find($payment->id);

            if ($locked === null || $locked->isPaid()) {
                return false;
            }

            $locked->forceFill([
                'status' => PaymentStatus::Paid,
                'paid_at' => now(),
                'gateway_reference' => $gatewayReference ?? $locked->gateway_reference,
            ])->save();

            $this->apply($locked);

            return true;
        });

        if ($applied) {
            $this->notify($payment->refresh());
        }

        return $applied;
    }

    /**
     * What each kind of payment does once paid.
     */
    private function apply(Payment $payment): void
    {
        $listing = $payment->listing;

        match ($payment->purpose) {
            PaymentPurpose::ApplicationFee, PaymentPurpose::Service => $payment->payable instanceof RentalApplication || $payment->payable instanceof ServiceBooking
                ? $payment->payable->update(['status' => ApplicationStatus::New])
                : null,
            PaymentPurpose::HoldingDeposit => $listing?->status === ListingStatus::ForRent
                ? $listing->update(['status' => ListingStatus::Pending])
                : null,
            PaymentPurpose::FeaturedListing => $listing?->forceFill([
                'is_featured' => true,
                'featured_until' => ($listing->featured_until?->isFuture() ? $listing->featured_until : now())
                    ->addDays(max(1, (int) Setting::get('featured_days'))),
            ])->save(),
        };
    }

    /**
     * Send the payer a receipt and tell the agent (or the office) what was paid for.
     */
    private function notify(Payment $payment): void
    {
        try {
            Mail::to($payment->payer_email)->send(new PaymentReceipt($payment));

            $office = Setting::get('email');
            $agentEmail = $payment->listing?->agent?->contactEmail();
            $to = array_values(array_unique(array_filter([$agentEmail, $office])));

            if ($to !== []) {
                Mail::to($to)->send(new PaymentAlert($payment));
            }
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
