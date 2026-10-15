<?php

namespace App\Contacts\Actions;

use App\Contacts\Contact;
use App\Contacts\ContactNote;
use App\Leads\Actions\RecordActivity;
use App\Leads\ActivityEvent;
use App\Models\User;

class AddContactNote
{
    public function __construct(private RecordActivity $activity) {}

    public function handle(Contact $contact, string $body, User $actor): ContactNote
    {
        $note = ContactNote::query()->create([
            'contact_id' => $contact->id,
            'user_id' => $actor->id,
            'body' => trim($body),
        ]);

        $this->activity->handle(
            ActivityEvent::ContactNoteAdded,
            null,
            $contact,
            $actor,
            ['note_id' => $note->id],
        );

        return $note;
    }
}
