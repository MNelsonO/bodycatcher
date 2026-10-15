<?php

namespace App\Calendar;

use App\Leads\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
