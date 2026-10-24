<?php

namespace App\Models;

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use Database\Factories\EnquiryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A message or tour request sent to an agent from the website.
 *
 * @property int $id
 * @property EnquiryType $type
 * @property EnquiryStatus $status
 * @property int|null $listing_id
 * @property int|null $agent_id
 * @property int|null $user_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $message
 * @property Carbon|null $tour_date
 * @property string|null $tour_time
 * @property string|null $tour_kind
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Listing|null $listing
 * @property-read Agent|null $agent
 */
#[Fillable(['type', 'status', 'listing_id', 'agent_id', 'user_id', 'name', 'email', 'phone', 'message', 'tour_date', 'tour_time', 'tour_kind', 'ip_address'])]
class Enquiry extends Model
{
    /** @use HasFactory<EnquiryFactory> */
    use HasFactory;

    /**
     * Parts of the day a visitor can pick for a tour.
     *
     * @var array<string, string>
     */
    public const array TOUR_TIMES = [
        'morning' => 'Morning (9am – 12pm)',
        'afternoon' => 'Afternoon (12pm – 4pm)',
        'evening' => 'Evening (4pm – 7pm)',
    ];

    /**
     * Ways a visitor can tour a home.
     *
     * @var array<string, string>
     */
    public const array TOUR_KINDS = [
        'in_person' => 'In person',
        'video' => 'Video chat',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EnquiryType::class,
            'status' => EnquiryStatus::class,
            'tour_date' => 'date',
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
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Limit to what a dashboard user may see: everything for admins, their own for agents.
     *
     * @param  Builder<Enquiry>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if (! $user->isAdmin()) {
            $query->where('agent_id', $user->agent?->id ?? 0);
        }
    }

    /**
     * "Saturday, Nov 2 · Morning (9am – 12pm) · In person".
     */
    public function tourSummary(): ?string
    {
        if ($this->tour_date === null) {
            return null;
        }

        return implode(' · ', array_filter([
            $this->tour_date->format('l, M j'),
            self::TOUR_TIMES[$this->tour_time] ?? null,
            self::TOUR_KINDS[$this->tour_kind] ?? null,
        ]));
    }
}
