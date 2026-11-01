<?php

namespace App\Models;

use App\Enums\PropertyType;
use Database\Factories\HomeTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A home type the admin manages, e.g. "Duplex". Its kind (house, condo, land…)
 * decides the drawing used without photos and the details shown for land.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property PropertyType $kind
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'kind', 'sort_order'])]
class HomeType extends Model
{
    /** @use HasFactory<HomeTypeFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PropertyType::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<Listing, $this>
     */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class, 'property_type', 'slug');
    }

    /**
     * Types in the order the admin chose.
     *
     * @param  Builder<HomeType>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Every type, keyed by slug, loaded once per request.
     *
     * @return Collection<array-key, HomeType>
     */
    public static function cachedBySlug(): Collection
    {
        return once(fn () => HomeType::query()->ordered()->get()->keyBy('slug'));
    }

    /**
     * Type names keyed by slug, for selects and filters.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return self::cachedBySlug()->map(fn (HomeType $type) => $type->name)->all();
    }

    /**
     * A web-safe key for a new type name, unique among types.
     */
    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name, '_') ?: 'type';
        $slug = $base;
        $counter = 2;

        while (static::query()->where('slug', $slug)->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'_'.$counter++;
        }

        return $slug;
    }
}
