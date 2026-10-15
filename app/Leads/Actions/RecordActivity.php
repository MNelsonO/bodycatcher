<?php

namespace App\Leads\Actions;

use App\Contacts\Contact;
use App\Leads\ActivityEvent;
use App\Leads\ActivityLog;
use App\Leads\Lead;
use App\Models\User;

class RecordActivity
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function handle(
        ActivityEvent $event,
        ?Lead $lead,
        ?Contact $contact,
        ?User $actor,
        array $properties = [],
    ): ActivityLog {
        $contactId = null;

        if ($contact !== null) {
            $contactId = $contact->id;
        } elseif ($lead !== null) {
            $contactId = $lead->contact_id;
        }

        return ActivityLog::query()->create([
            'event' => $event,
            'lead_id' => $lead?->id,
            'contact_id' => $contactId,
            'actor_user_id' => $actor?->id,
            'properties' => $properties,
        ]);
    }
}
