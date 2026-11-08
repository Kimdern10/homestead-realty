<?php

namespace App\Models;

use App\Enums\ListingStatus;
use Database\Factories\SavedSearchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property array<string, mixed> $filters
 * @property bool $alerts_enabled
 * @property Carbon|null $last_alerted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'name', 'filters', 'alerts_enabled', 'last_alerted_at'])]
class SavedSearch extends Model
{
    /** @use HasFactory<SavedSearchFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'alerts_enabled' => 'boolean',
            'last_alerted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The search page address with these filters.
     */
    public function url(): string
    {
        return route('search', array_filter($this->filters, fn ($value) => filled($value)));
    }

    /**
     * A plain description, e.g. "For sale · 3+ beds · Up to $900,000".
     */
    public function summary(): string
    {
        $filters = $this->filters;

        return implode(' · ', array_filter([
            filled($filters['q'] ?? null) ? '“'.$filters['q'].'”' : null,
            ListingStatus::tryFrom((string) ($filters['status'] ?? ''))?->label(),
            collect((array) ($filters['types'] ?? []))->map(fn ($type) => HomeType::options()[(string) $type] ?? null)->filter()->implode(', ') ?: null,
            (int) ($filters['beds'] ?? 0) > 0 ? $filters['beds'].'+ beds' : null,
            (float) ($filters['baths'] ?? 0) > 0 ? $filters['baths'].'+ baths' : null,
            (int) ($filters['min_price'] ?? 0) > 0 ? 'From $'.number_format((int) $filters['min_price']) : null,
            (int) ($filters['max_price'] ?? 0) > 0 ? 'Up to $'.number_format((int) $filters['max_price']) : null,
            filled($filters['area'] ?? null) ? 'Drawn map area' : null,
        ])) ?: 'All homes';
    }
}
