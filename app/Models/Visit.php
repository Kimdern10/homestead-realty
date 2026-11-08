<?php

namespace App\Models;

use Database\Factories\VisitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Page views per day and where they came from ("Google", "Facebook", "Direct"…).
 * Only counts are kept: no IP addresses or cookies, so no consent banner is needed.
 *
 * @property int $id
 * @property Carbon $day
 * @property string $source
 * @property int $views
 */
#[Fillable(['day', 'source', 'views'])]
class Visit extends Model
{
    /** @use HasFactory<VisitFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day' => 'date',
            'views' => 'integer',
        ];
    }

    /**
     * Add one view for today from this source.
     */
    public static function record(string $source): void
    {
        DB::table('visits')->upsert(
            [['day' => today()->toDateString(), 'source' => $source, 'views' => 1]],
            ['day', 'source'],
            ['views' => DB::raw('views + 1')],
        );
    }

    /**
     * Turn a referring web address into a friendly source name.
     */
    public static function sourceFrom(?string $referrer, string $ownHost): string
    {
        $host = strtolower((string) parse_url((string) $referrer, PHP_URL_HOST));
        $host = (string) preg_replace('/^(www|m|l|lm)\./', '', $host);

        if ($host === '' || $host === strtolower($ownHost)) {
            return 'Direct or bookmark';
        }

        $shortLinks = ['fb.com' => 'Facebook', 't.co' => 'X (Twitter)', 'x.com' => 'X (Twitter)'];

        if (isset($shortLinks[$host])) {
            return $shortLinks[$host];
        }

        foreach (self::KNOWN_SOURCES as $part => $name) {
            if (str_contains($host, $part)) {
                return $name;
            }
        }

        return mb_substr($host, 0, 80);
    }

    /**
     * Parts of a web address and the friendly name for them.
     *
     * @var array<string, string>
     */
    private const array KNOWN_SOURCES = [
        'google.' => 'Google',
        'bing.' => 'Bing',
        'yahoo.' => 'Other search engines',
        'duckduckgo.' => 'Other search engines',
        'facebook.' => 'Facebook',
        'instagram.' => 'Instagram',
        'twitter.' => 'X (Twitter)',
        'linkedin.' => 'LinkedIn',
        'whatsapp.' => 'WhatsApp',
    ];
}
