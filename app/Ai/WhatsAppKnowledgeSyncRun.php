<?php

namespace App\Ai;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppKnowledgeSyncRun extends Model
{
    protected $table = 'whatsapp_knowledge_syncs';

    protected $fillable = [
        'status',
        'articles_created',
        'messages_scanned',
        'summary',
        'error',
        'requested_by_user_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'articles_created' => 'integer',
            'messages_scanned' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
