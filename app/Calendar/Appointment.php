<?php

namespace App\Calendar;

use App\Contacts\Contact;
use App\Leads\Lead;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $title
 * @property AppointmentKind $kind
 * @property AppointmentStatus $status
 * @property CarbonInterface $starts_at
 * @property CarbonInterface $ends_at
 * @property string|null $location
 * @property string|null $notes
 * @property int|null $contact_id
 * @property int|null $lead_id
 * @property int|null $created_by_user_id
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Contact|null $contact
 * @property-read Lead|null $lead
 * @property-read User|null $createdBy
 */
#[Fillable([
    'title',
    'kind',
    'status',
    'starts_at',
    'ends_at',
    'location',
    'notes',
    'contact_id',
    'lead_id',
    'created_by_user_id',
])]
class Appointment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => AppointmentKind::class,
            'status' => AppointmentStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
