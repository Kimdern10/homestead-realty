<?php

namespace App\Models;

use Database\Factories\AgentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $slug
 * @property string|null $title
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $license_number
 * @property int|null $years_experience
 * @property string|null $languages
 * @property string|null $bio
 * @property string|null $photo_path
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'name', 'slug', 'title', 'phone', 'email', 'license_number', 'years_experience', 'languages', 'bio', 'photo_path', 'is_active'])]
class Agent extends Model
{
    /** @use HasFactory<AgentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'years_experience' => 'integer',
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
     * The login the agent uses for the dashboard.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Listing, $this>
     */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /**
     * @return HasMany<Enquiry, $this>
     */
    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class);
    }

    /**
     * Agents shown on the website.
     *
     * @param  Builder<Agent>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * The email enquiries are sent to: the profile email, else the login email.
     */
    public function contactEmail(): string
    {
        return $this->email ?: $this->user->email;
    }

    /**
     * Two-letter initials for the avatar when there is no photo.
     */
    public function initials(): string
    {
        $words = preg_split('/\s+/', trim($this->name)) ?: [];

        return Str::upper(Str::substr($words[0] ?? '', 0, 1).Str::substr(count($words) > 1 ? end($words) : '', 0, 1));
    }

    /**
     * Get the public URL of the agent photo, if there is one.
     */
    public function photoUrl(): ?string
    {
        return filled($this->photo_path) ? Storage::disk('public')->url($this->photo_path) : null;
    }

    /**
     * A phone number suitable for a tel: link.
     */
    public function phoneLink(): ?string
    {
        return filled($this->phone) ? 'tel:'.preg_replace('/[^0-9+]/', '', $this->phone) : null;
    }
}
