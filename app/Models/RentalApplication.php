<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Database\Factories\RentalApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

/**
 * A renter's application for a home for rent. It reaches the agent once the fee is paid.
 *
 * @property int $id
 * @property int $listing_id
 * @property int|null $user_id
 * @property ApplicationStatus $status
 * @property string $name
 * @property string $email
 * @property string $phone
 * @property Carbon $move_in_date
 * @property int $occupants
 * @property bool $has_pets
 * @property string|null $employer
 * @property string|null $job_title
 * @property int $monthly_income
 * @property string|null $current_address
 * @property string|null $references
 * @property string|null $message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Listing $listing
 * @property-read Payment|null $payment
 */
#[Fillable([
    'listing_id', 'user_id', 'status', 'name', 'email', 'phone', 'move_in_date', 'occupants', 'has_pets',
    'employer', 'job_title', 'monthly_income', 'current_address', 'references', 'message',
])]
class RentalApplication extends Model
{
    /** @use HasFactory<RentalApplicationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'move_in_date' => 'date',
            'occupants' => 'integer',
            'has_pets' => 'boolean',
            'monthly_income' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Listing, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /**
     * @return MorphOne<Payment, $this>
     */
    public function payment(): MorphOne
    {
        return $this->morphOne(Payment::class, 'payable');
    }

    /**
     * Applications a dashboard user may see: all for admins, those for their listings for agents.
     *
     * @param  Builder<RentalApplication>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if (! $user->isAdmin()) {
            $query->whereHas('listing', fn (Builder $listing) => $listing->where('agent_id', $user->agent->id ?? 0));
        }
    }

    /**
     * Rent as a share of income, e.g. 0.31 (landlords usually want under a third).
     */
    public function rentToIncome(): ?float
    {
        return $this->monthly_income > 0 ? round($this->listing->price / $this->monthly_income, 2) : null;
    }
}
