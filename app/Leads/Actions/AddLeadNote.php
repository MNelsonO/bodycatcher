<?php

namespace App\Leads\Actions;

use App\Leads\ActivityEvent;
use App\Leads\Lead;
use App\Leads\LeadNote;
use App\Models\User;

class AddLeadNote
{
    public function __construct(private RecordActivity $activity) {}

    public function handle(Lead $lead, string $body, User $actor): LeadNote
    {
        $note = LeadNote::query()->create([
            'lead_id' => $lead->id,
            'contact_id' => $lead->contact_id,
            'user_id' => $actor->id,
            'body' => trim($body),
        ]);

        $this->activity->handle(
            ActivityEvent::NoteAdded,
            $lead,
            $lead->contact,
            $actor,
            ['note_id' => $note->id],
        );

        return $note;
    }
}
