<?php

namespace App\Models;

use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One online payment through Stripe or Paystack. Amounts are in cents.
 *
 * @property int $id
 * @property string $reference
 * @property string $gateway
 * @property PaymentPurpose $purpose
 * @property PaymentStatus $status
 * @property int $amount
 * @property string $currency
 * @property string $description
 * @property int|null $listing_id
 * @property int|null $user_id
 * @property string|null $payable_type
 * @property int|null $payable_id
 * @property string $payer_name
 * @property string $payer_email
 * @property string|null $payer_phone
 * @property string|null $gateway_reference
 * @property string|null $checkout_url
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Listing|null $listing
 * @property-read Model|null $payable
 */
#[Fillable([
    'reference', 'gateway', 'purpose', 'status', 'amount', 'currency', 'description', 'listing_id', 'user_id',
    'payable_type', 'payable_id', 'payer_name', 'payer_email', 'payer_phone', 'gateway_reference', 'checkout_url', 'paid_at',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => PaymentPurpose::class,
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Use the reference in web addresses (it's hard to guess, unlike the id).
     */
    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /**
     * A new, unique payment reference such as "HR-01JC3…".
     */
    public static function newReference(): string
    {
        return 'HR-'.Str::upper((string) Str::ulid());
    }

    /**
     * @return BelongsTo<Listing, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The application or booking this payment is for.
     *
     * @return MorphTo<Model, $this>
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Payments a dashboard user may see: all for admins, those for their listings for agents.
     *
     * @param  Builder<Payment>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if (! $user->isAdmin()) {
            $query->whereHas('listing', fn (Builder $listing) => $listing->where('agent_id', $user->agent->id ?? 0));
        }
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::Paid;
    }

    /**
     * "$45.00".
     */
    public function amountLabel(): string
    {
        return ($this->currency === 'NGN' ? '₦' : '$').number_format($this->amount / 100, 2);
    }
}
