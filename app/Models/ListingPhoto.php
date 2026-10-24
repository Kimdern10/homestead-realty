<?php

namespace App\Models;

use Database\Factories\ListingPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $listing_id
 * @property string $path
 * @property int|null $width
 * @property int|null $height
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['listing_id', 'path', 'width', 'height', 'sort_order'])]
class ListingPhoto extends Model
{
    /** @use HasFactory<ListingPhotoFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'sort_order' => 'integer',
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
     * The path of the small copy used on cards and thumbnails.
     */
    public function smallPath(): string
    {
        return Str::replaceLast('.webp', '-sm.webp', $this->path);
    }

    /**
     * Get the public URL of the large or small copy.
     */
    public function url(bool $small = false): string
    {
        return Storage::disk('public')->url($small ? $this->smallPath() : $this->path);
    }

    /**
     * Both stored files.
     *
     * @return list<string>
     */
    public function paths(): array
    {
        return [$this->path, $this->smallPath()];
    }
}
