<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Database\Factories\ServiceBookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

/**
 * A visitor's paid booking of a service, e.g. a home valuation.
 *
 * @property int $id
 * @property int|null $service_id
 * @property int|null $user_id
 * @property string $service_name
 * @property string $name
 * @property string $email
 * @property string $phone
 * @property string|null $address
 * @property Carbon|null $preferred_date
 * @property string|null $notes
 * @property ApplicationStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Service|null $service
 * @property-read Payment|null $payment
 */
#[Fillable(['service_id', 'user_id', 'service_name', 'name', 'email', 'phone', 'address', 'preferred_date', 'notes', 'status'])]
class ServiceBooking extends Model
{
    /** @use HasFactory<ServiceBookingFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'preferred_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return MorphOne<Payment, $this>
     */
    public function payment(): MorphOne
    {
        return $this->morphOne(Payment::class, 'payable');
    }
}
