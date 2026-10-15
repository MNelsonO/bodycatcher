<?php

namespace App\Leads;

use App\Settings\DisplayClock;

class LeadPageData
{
    public function __construct(private DisplayClock $clock) {}

    /**
     * @return array<string, mixed>
     */
    public function detail(Lead $lead): array
    {
        $lead->load([
            'contact.phones',
            'product',
            'pipeline',
            'currentStage',
            'stageHistory.fromStage',
            'stageHistory.toStage',
            'stageHistory.actor',
            'notes.author',
            'conversation.messages.loggedBy',
        ]);

        $permitted = $lead->currentStage->outgoingTransitions()
            ->with('toStage')
            ->get()
            ->map(fn ($transition) => $transition->toStage)
            ->filter(fn ($stage) => $stage !== null && $stage->archived_at === null)
            ->values();

        $activities = ActivityLog::query()
            ->with('actor')
            ->where(function ($query) use ($lead): void {
                $query->where('lead_id', $lead->id)
                    ->orWhere(function ($contactQuery) use ($lead): void {
                        $contactQuery->where('contact_id', $lead->contact_id)
                            ->whereIn('event', [
                                ActivityEvent::ContactCreated->value,
                                ActivityEvent::ContactMerged->value,
                            ]);
                    });
            })
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return [
            'id' => $lead->id,
            'outcome' => $lead->outcome(),
            'source' => $lead->source,
            'business_problem' => $lead->business_problem,
            'current_solution' => $lead->current_solution,
            'branch_count' => $lead->branch_count,
            'budget_note' => $lead->budget_note,
            'decision_maker_status' => $lead->decision_maker_status->value,
            'purchase_timeline_note' => $lead->purchase_timeline_note,
            'next_action' => $lead->next_action,
            'next_follow_up_on' => $lead->next_follow_up_on?->toDateString(),
            'next_follow_up_label' => $this->clock->formatDate($lead->next_follow_up_on),
            'lost_reason' => $lead->lost_reason,
            'created_at_label' => $this->clock->formatDateTime($lead->created_at),
            'contact' => [
                'id' => $lead->contact->id,
                'full_name' => $lead->contact->full_name,
                'business_name' => $lead->contact->business_name,
                'business_type' => $lead->contact->business_type,
                'location' => $lead->contact->location,
                'email' => $lead->contact->email,
                'primary_phone' => $lead->contact->primary_phone,
                'additional_phones' => $lead->contact->phones
                    ->where('is_primary', false)
                    ->pluck('phone')
                    ->values()
                    ->all(),
                'preferred_channel' => $lead->contact->preferred_channel->value,
                'consent_status' => $lead->contact->consent_status->value,
                'opted_out_at_label' => $this->clock->formatDateTime($lead->contact->opted_out_at),
                'lead_source' => $lead->contact->lead_source,
                'created_at_label' => $this->clock->formatDateTime($lead->contact->created_at),
            ],
            'product' => [
                'id' => $lead->product->id,
                'name' => $lead->product->name,
            ],
            'pipeline' => [
                'id' => $lead->pipeline->id,
                'name' => $lead->pipeline->name,
            ],
            'stage' => [
                'id' => $lead->currentStage->id,
                'name' => $lead->currentStage->name,
                'kind' => $lead->currentStage->kind->value,
                'default_follow_up_delay_hours' => $lead->currentStage->default_follow_up_delay_hours,
            ],
            'permitted_stages' => $permitted->map(fn ($stage): array => [
                'id' => $stage->id,
                'name' => $stage->name,
                'kind' => $stage->kind->value,
            ])->all(),
            'history' => $lead->stageHistory->map(fn (LeadStageHistory $row): array => [
                'id' => $row->id,
                'from_stage' => $row->fromStage?->name,
                'to_stage' => $row->toStage->name,
                'reason' => $row->reason,
                'actor' => $row->actor->name,
                'at' => $this->clock->formatDateTime($row->created_at),
            ])->all(),
            'messages' => $lead->conversation?->messages->map(fn ($message): array => [
                'id' => $message->id,
                'direction' => $message->direction->value,
                'body' => $message->body,
                'sent_at_label' => $this->clock->formatDateTime($message->sent_at),
                'logged_by' => $message->loggedBy->name,
            ])->all() ?? [],
            'notes' => $lead->notes->map(fn (LeadNote $note): array => [
                'id' => $note->id,
                'body' => $note->body,
                'author' => $note->author->name,
                'at' => $this->clock->formatDateTime($note->created_at),
            ])->all(),
            'activities' => $activities->map(fn (ActivityLog $row): array => [
                'id' => $row->id,
                'event' => $row->event->value,
                'label' => $this->activityLabel($row),
                'actor' => $row->actor?->name,
                'at' => $this->clock->formatDateTime($row->created_at),
            ])->all(),
        ];
    }

    private function activityLabel(ActivityLog $row): string
    {
        $properties = $row->properties ?? [];

        return match ($row->event) {
            ActivityEvent::ContactCreated => 'Contact created',
            ActivityEvent::LeadCreated => 'Lead created',
            ActivityEvent::StageChanged => 'Stage changed'
                .(isset($properties['from_stage'], $properties['to_stage'])
                    ? ': '.$properties['from_stage'].' → '.$properties['to_stage']
                    : ''),
            ActivityEvent::MessageLogged => 'Message logged'
                .(isset($properties['direction']) ? ' ('.$properties['direction'].')' : ''),
            ActivityEvent::NoteAdded => 'Note added',
            ActivityEvent::ContactMerged => 'Contact merged'
                .(isset($properties['merged_name'], $properties['survivor_name'])
                    ? ': '.$properties['merged_name'].' into '.$properties['survivor_name']
                    : ''),
            ActivityEvent::FollowUpChanged => 'Follow-up date changed'
                .(array_key_exists('to', $properties)
                    ? ': '.($properties['from'] ?: 'none').' → '.($properties['to'] ?: 'none')
                    : ''),
        };
    }
}
