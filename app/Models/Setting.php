<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Key/value store for the business details the owner can edit in the dashboard.
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    /**
     * Values used until the owner saves their own.
     *
     * @var array<string, string>
     */
    public const array DEFAULTS = [
        'business_name' => 'Homestead Realty',
        'tagline' => 'Find the home that fits your life.',
        'phone' => '(512) 555-0142',
        'email' => 'hello@homesteadrealty.example',
        'address' => '600 Congress Ave, Suite 1400, Austin, TX 78701',
        'office_hours' => 'Mon – Sat, 9am – 6pm',
        'broker_license' => '',
        'facebook_url' => '',
        'instagram_url' => '',
        'x_url' => '',
        'linkedin_url' => '',
        'youtube_url' => '',
        'hero_title' => 'Find the home that fits your life',
        'hero_text' => 'Search homes for sale and rent across the country, save your favorites and tour with a local agent.',
        'seo_title' => 'Homes for sale and rent',
        'seo_description' => 'Search homes for sale and rent with Homestead Realty. Photos, prices, maps, mortgage estimates and local agents in cities across the USA.',
        'mortgage_rate' => '6.5',
        // Live homes from RentCast are off until the admin switches them on.
        'live_feed_enabled' => '0',
        'privacy_policy' => '',
    ];

    private const string CACHE_KEY = 'site-settings';

    /**
     * Get every stored value, falling back to the defaults.
     *
     * @return array<string, string>
     */
    public static function values(): array
    {
        // Only the saved values are cached; defaults are added on every read so
        // settings introduced by a later update are never missing from an old cache.
        return array_merge(self::DEFAULTS, Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => static::query()->pluck('value', 'key')->map(fn (?string $value): string => (string) $value)->all(),
        ));
    }

    /**
     * Get one value.
     */
    public static function get(string $key): string
    {
        return self::values()[$key] ?? '';
    }

    /**
     * Save several values at once.
     *
     * @param  array<string, string|null>  $values
     */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            static::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Get the privacy policy text (Markdown): the owner's own, or a starter version.
     */
    public static function privacyPolicy(): string
    {
        $saved = trim(self::get('privacy_policy'));

        if ($saved !== '') {
            return $saved;
        }

        $values = self::values();
        $business = $values['business_name'];
        $email = $values['email'];
        $phone = $values['phone'];

        return <<<MARKDOWN
            This page explains what personal information {$business} collects through this website, why, and the choices you have.

            ## What we collect

            - **When you contact an agent or request a tour:** your name, email address, phone number (if you give it), your message and the date you would like to tour.
            - **When you create an account:** your name, email address and a securely hashed password, plus the homes and searches you save.
            - **Automatically:** your IP address, to protect our forms from spam, and a session cookie that keeps you signed in.

            ## How we use it

            - To pass your message or tour request to the agent for that home, so they can reply.
            - To show you your saved homes and searches.
            - To keep the website secure and prevent abuse.

            We do **not** sell your personal information.

            ## Who can see it

            The agent you contact and the {$business} office staff. Our hosting provider stores the data securely on our behalf.

            ## How long we keep it

            We keep enquiries for up to two years after our last contact. You can delete your account and saved homes at any time from your account settings.

            ## Your choices

            You can ask us to show, correct or delete the information we hold about you. Contact us at {$email} or call {$phone}. If you live in California or another state with a privacy law, you have the rights that law gives you, and we will not treat you differently for using them.

            ## Map and photos

            Maps are provided by OpenStreetMap. When a map loads, your browser fetches map tiles from OpenStreetMap's servers, which can see your IP address.

            ## Changes

            We may update this page. The latest version is always shown here.
            MARKDOWN;
    }
}
