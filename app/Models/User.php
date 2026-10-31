<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Notifications\EmailVerificationCode;
use App\Support\EmailVerificationCodes;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property UserRole $role
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Agent|null $agent
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    /**
     * Email a 6-digit code (instead of a link) that the visitor types in to confirm their email.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new EmailVerificationCode(app(EmailVerificationCodes::class)->create($this)));
    }

    /**
     * The agent profile of an agent login.
     *
     * @return HasOne<Agent, $this>
     */
    public function agent(): HasOne
    {
        return $this->hasOne(Agent::class);
    }

    /**
     * Homes the visitor has saved.
     *
     * @return BelongsToMany<Listing, $this>
     */
    public function favorites(): BelongsToMany
    {
        return $this->belongsToMany(Listing::class, 'favorites')->withTimestamps();
    }

    /**
     * Searches the visitor has saved.
     *
     * @return HasMany<SavedSearch, $this>
     */
    public function savedSearches(): HasMany
    {
        return $this->hasMany(SavedSearch::class);
    }

    /**
     * Whether the user runs the whole website.
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Whether the user is an agent with a profile.
     */
    public function isAgent(): bool
    {
        return $this->role === UserRole::Agent;
    }

    /**
     * Whether the user may open the dashboard (admins and agents).
     */
    public function isStaff(): bool
    {
        return $this->isAdmin() || $this->isAgent();
    }

    /**
     * The ids of the homes this user has saved, loaded once per request.
     *
     * @return list<int>
     */
    public function favoriteListingIds(): array
    {
        return once(fn (): array => array_values($this->favorites()->pluck('listings.id')->map(fn ($id): int => (int) $id)->all()));
    }
}
