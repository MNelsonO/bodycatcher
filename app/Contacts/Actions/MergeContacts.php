<?php

namespace App\Contacts\Actions;

use App\Contacts\Contact;
use App\Contacts\ContactMerge;
use App\Contacts\ContactPhone;
use App\Conversations\Conversation;
use App\Leads\Actions\RecordActivity;
use App\Leads\ActivityEvent;
use App\Leads\Lead;
use App\Leads\LeadNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MergeContacts
{
    public function __construct(private RecordActivity $activity) {}

    public function handle(Contact $survivor, Contact $merged, User $actor): Contact
    {
        $survivor = $survivor->survivor();
        $merged = $merged->survivor();

        if ($survivor->id === $merged->id) {
            throw ValidationException::withMessages([
                'merged_contact_id' => 'Choose a different contact to merge.',
            ]);
        }

        return DB::transaction(function () use ($survivor, $merged, $actor): Contact {
            /** @var Contact $survivor */
            $survivor = Contact::query()->whereKey($survivor->id)->lockForUpdate()->firstOrFail();
            /** @var Contact $merged */
            $merged = Contact::query()->whereKey($merged->id)->lockForUpdate()->firstOrFail();

            $openProducts = Lead::query()
                ->where('contact_id', $survivor->id)
                ->whereNull('closed_at')
                ->pluck('product_id');

            $conflict = Lead::query()
                ->where('contact_id', $merged->id)
                ->whereNull('closed_at')
                ->whereIn('product_id', $openProducts)
                ->exists();

            if ($conflict) {
                throw ValidationException::withMessages([
                    'merged_contact_id' => 'Both contacts have an open lead for the same product. Close one of those leads before merging.',
                ]);
            }

            $leadIds = Lead::query()->where('contact_id', $merged->id)->pluck('id');

            Lead::query()->where('contact_id', $merged->id)->update(['contact_id' => $survivor->id]);
            LeadNote::query()->where('contact_id', $merged->id)->update(['contact_id' => $survivor->id]);
            Conversation::query()->where('contact_id', $merged->id)->update(['contact_id' => $survivor->id]);

            $merged->load('phones');

            foreach ($merged->phones as $phone) {
                $alreadyKept = ContactPhone::query()
                    ->where('phone', $phone->phone)
                    ->where('contact_id', $survivor->id)
                    ->exists();

                if ($alreadyKept) {
                    $phone->delete();

                    continue;
                }

                $phone->update([
                    'contact_id' => $survivor->id,
                    'is_primary' => false,
                ]);
            }

            $merged->update(['merged_into_contact_id' => $survivor->id]);

            ContactMerge::query()->create([
                'survivor_contact_id' => $survivor->id,
                'merged_contact_id' => $merged->id,
                'actor_user_id' => $actor->id,
                'snapshot' => [
                    'survivor_name' => $survivor->full_name,
                    'merged_name' => $merged->full_name,
                    'merged_phone' => $merged->primary_phone,
                    'lead_ids' => $leadIds->all(),
                ],
            ]);

            foreach ($leadIds as $leadId) {
                if (! is_int($leadId) && ! (is_string($leadId) && ctype_digit($leadId))) {
                    continue;
                }

                $lead = Lead::query()->whereKey((int) $leadId)->first();

                if ($lead === null) {
                    continue;
                }

                $this->activity->handle(
                    ActivityEvent::ContactMerged,
                    $lead,
                    $survivor,
                    $actor,
                    [
                        'survivor_name' => $survivor->full_name,
                        'merged_name' => $merged->full_name,
                    ],
                );
            }

            return $survivor->refresh();
        });
    }
}
