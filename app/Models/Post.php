<?php

namespace App\Models;

use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A guide or blog post written by the admin, e.g. "Best areas in Austin for families".
 *
 * @property int $id
 * @property int|null $city_id
 * @property int|null $user_id
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string $body
 * @property string|null $cover_path
 * @property bool $is_published
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read City|null $city
 */
#[Fillable(['city_id', 'user_id', 'title', 'slug', 'excerpt', 'body', 'cover_path', 'is_published', 'published_at'])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
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
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Guides visitors may read, newest first.
     *
     * @param  Builder<Post>  $query
     */
    #[Scope]
    protected function live(Builder $query): void
    {
        $query->where('is_published', true)->where('published_at', '<=', now())->latest('published_at');
    }

    public function coverUrl(bool $small = false): ?string
    {
        if (blank($this->cover_path)) {
            return null;
        }

        return Storage::disk('public')->url($small ? Str::replaceLast('.webp', '-sm.webp', $this->cover_path) : $this->cover_path);
    }

    /**
     * About how long the guide takes to read.
     */
    public function readingMinutes(): int
    {
        return max(1, (int) round(str_word_count(strip_tags($this->body)) / 220));
    }

    /**
     * A short summary: the excerpt, or the start of the text.
     */
    public function summary(): string
    {
        return $this->excerpt ?: Str::limit(trim(preg_replace('/[#*_>\[\]()`-]/', '', $this->body) ?? ''), 160);
    }
}
