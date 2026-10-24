<?php

namespace App\Models;

use App\Enums\ListingStatus;
use App\Enums\PropertyType;
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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'name', 'filters'])]
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
            collect((array) ($filters['types'] ?? []))->map(fn ($type) => PropertyType::tryFrom((string) $type)?->label())->filter()->implode(', ') ?: null,
            (int) ($filters['beds'] ?? 0) > 0 ? $filters['beds'].'+ beds' : null,
            (float) ($filters['baths'] ?? 0) > 0 ? $filters['baths'].'+ baths' : null,
            (int) ($filters['min_price'] ?? 0) > 0 ? 'From $'.number_format((int) $filters['min_price']) : null,
            (int) ($filters['max_price'] ?? 0) > 0 ? 'Up to $'.number_format((int) $filters['max_price']) : null,
        ])) ?: 'All homes';
    }
}
