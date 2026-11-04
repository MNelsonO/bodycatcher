<?php

namespace App\Calendar;

use App\Leads\Lead;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property CarbonInterface|null $activity_on
 * @property string $title
 * @property string|null $notes
 * @property string $activity_type
 * @property string $color
 * @property int|null $lead_id
 * @property string|null $external_calendar_id
 * @property string|null $start_time
 * @property int|null $duration_minutes
 * @property string|null $location
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class CalendarActivity extends Model
{
    protected $fillable = [
        'user_id',
        'activity_on',
        'title',
        'notes',
        'activity_type',
        'color',
        'lead_id',
        'external_calendar_id',
        'start_time',
        'duration_minutes',
        'location',
    ];

    protected function casts(): array
    {
        return [
            'activity_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
