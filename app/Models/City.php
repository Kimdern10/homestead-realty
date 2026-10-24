<?php

namespace App\Models;

use Database\Factories\CityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $state
 * @property string $state_code
 * @property string|null $tagline
 * @property string|null $description
 * @property string|null $image_path
 * @property string $latitude
 * @property string $longitude
 * @property bool $is_featured
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'state', 'state_code', 'tagline', 'description', 'image_path', 'latitude', 'longitude', 'is_featured', 'sort_order'])]
class City extends Model
{
    /** @use HasFactory<CityFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Use the slug in web addresses.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<Listing, $this>
     */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /**
     * Cities in the order the owner chose.
     *
     * @param  Builder<City>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * "Austin, TX".
     */
    public function label(): string
    {
        return "{$this->name}, {$this->state_code}";
    }

    /**
     * Get the public URL of the city photo (large or small copy).
     */
    public function imageUrl(bool $small = false): ?string
    {
        if (blank($this->image_path)) {
            return null;
        }

        $path = $small ? str_replace('.webp', '-sm.webp', $this->image_path) : $this->image_path;

        return Storage::disk('public')->url($path);
    }
}
