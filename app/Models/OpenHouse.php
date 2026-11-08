<?php

namespace App\Models;

use Database\Factories\OpenHouseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A time when anyone can walk through a home without booking.
 *
 * @property int $id
 * @property int $listing_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Listing $listing
 */
#[Fillable(['listing_id', 'starts_at', 'ends_at', 'note'])]
class OpenHouse extends Model
{
    /** @use HasFactory<OpenHouseFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
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
     * Open houses that haven't finished yet, soonest first.
     *
     * @param  Builder<OpenHouse>  $query
     */
    #[Scope]
    protected function upcoming(Builder $query): void
    {
        $query->where('ends_at', '>', now())->orderBy('starts_at');
    }

    /**
     * "Sat, Nov 9 · 1–3pm".
     */
    public function label(): string
    {
        $start = $this->starts_at->format('g'.($this->starts_at->minute ? ':i' : '').'a');
        $end = $this->ends_at->format('g'.($this->ends_at->minute ? ':i' : '').'a');

        if (substr($start, -2) === substr($end, -2)) {
            $start = substr($start, 0, -2);
        }

        return $this->starts_at->format('D, M j').' · '.$start.'–'.$end;
    }

    /**
     * "Open Sat 1–3pm" for cards.
     */
    public function shortLabel(): string
    {
        return 'Open '.($this->starts_at->isToday() ? 'today' : $this->starts_at->format('D')).' '.explode(' · ', $this->label())[1];
    }
}
