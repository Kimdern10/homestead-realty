<?php

namespace App\Models;

use Database\Factories\AmenityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An amenity agents can tick on a listing, e.g. "Swimming pool". Managed by the admin.
 *
 * @property int $id
 * @property string $name
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'sort_order'])]
class Amenity extends Model
{
    /** @use HasFactory<AmenityFactory> */
    use HasFactory;

    /**
     * Amenity names in the admin's order, loaded once per request.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return once(fn (): array => array_values(static::query()->orderBy('sort_order')->orderBy('name')->pluck('name')->all()));
    }
}
