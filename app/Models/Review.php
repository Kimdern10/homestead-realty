<?php

namespace App\Models;

use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A client's review of an agent. Shown only after the admin approves it.
 *
 * @property int $id
 * @property int $agent_id
 * @property int|null $user_id
 * @property string $name
 * @property string $email
 * @property int $rating
 * @property string|null $title
 * @property string $body
 * @property string $status
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Agent $agent
 */
#[Fillable(['agent_id', 'user_id', 'name', 'email', 'rating', 'title', 'body', 'status', 'ip_address'])]
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    public const string PENDING = 'pending';

    public const string APPROVED = 'approved';

    public const string REJECTED = 'rejected';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Reviews visitors may see.
     *
     * @param  Builder<Review>  $query
     */
    #[Scope]
    protected function approved(Builder $query): void
    {
        $query->where('status', self::APPROVED);
    }

    /**
     * "Ashley N." — first name and last initial only, for privacy.
     */
    public function publicName(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $first = $parts[0] ?? 'Client';
        $last = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1).'.' : '';

        return trim("{$first} {$last}");
    }
}
