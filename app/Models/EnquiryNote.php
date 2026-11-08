<?php

namespace App\Models;

use Database\Factories\EnquiryNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An agent's private note on a lead, e.g. "Called, wants a second viewing".
 *
 * @property int $id
 * @property int $enquiry_id
 * @property int|null $user_id
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
#[Fillable(['enquiry_id', 'user_id', 'body'])]
class EnquiryNote extends Model
{
    /** @use HasFactory<EnquiryNoteFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
