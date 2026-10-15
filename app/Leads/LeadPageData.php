<?php

namespace App\Leads;

use App\Ai\KnowledgeArticle;
use App\Ai\LeadAnalysis;
use App\Ai\LlmConfiguration;
use App\Ai\OperatorEscalation;
use App\Conversations\Message;
use App\Conversations\MessageAttachment;
use App\FollowUps\FollowUpTask;
use App\Settings\DisplayClock;
use App\WhatsApp\DeskSetting;
use App\WhatsApp\Services\ServiceWindow;

class LeadPageData
{
    public function __construct(
        private DisplayClock $clock,
        private ServiceWindow $window,
        private LlmConfiguration $llm,
    ) {}

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
            'interactions.author',
            'conversation.messages.loggedBy',
            'conversation.messages.attachments',
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
            'next_conversation_at' => $lead->next_conversation_at
                ? $this->clock->formatDateTimeLocal($lead->next_conversation_at)
                : null,
            'next_conversation_label' => $this->clock->formatDateTime($lead->next_conversation_at),
            'conversations_stopped' => $lead->conversations_stopped,
            'marked_on' => $lead->marked_on?->toDateString(),
            'marked_on_label' => $this->clock->formatDate($lead->marked_on),
            'marked_label' => $lead->marked_label,
            'conversation_topic' => $lead->conversation?->topic,
            'conversation_topic_summary' => $lead->conversation?->topic_summary,
            'lost_reason' => $lead->lost_reason,
            'created_at_label' => $this->clock->formatDateTime($lead->created_at),
            'contact' => [
                'id' => $lead->contact->id,
                'full_name' => $lead->contact->full_name,
                'business_name' => $lead->contact->business_name,
                'business_type' => $lead->contact->business_type,
                'location' => $lead->contact->location,
                'country_code' => $lead->contact->country_code,
                'country' => $lead->contact->country,
                'conversation_area' => $lead->contact->conversation_area,
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
                'actor' => $row->actor_user_id === null ? 'System' : $row->actor->name,
                'at' => $this->clock->formatDateTime($row->created_at),
            ])->all(),
            'messages' => $lead->conversation?->messages->map(fn (Message $message): array => [
                'id' => $message->id,
                'direction' => $message->direction->value,
                'channel' => $message->channel->value,
                'origin' => $message->origin->value,
                'status' => $message->status->value,
                'body' => $message->body,
                'sent_at_label' => $this->clock->formatDateTime($message->sent_at),
                'logged_by' => $message->loggedBy?->name,
                'refusal_reason' => $message->refusal_reason,
                'provider_message_id' => $message->provider_message_id,
                'attachments' => $message->attachments->map(fn (MessageAttachment $attachment): array => [
                    'id' => $attachment->id,
                    'provider_media_id' => $attachment->provider_media_id,
                    'mime_type' => $attachment->mime_type,
                    'caption' => $attachment->caption,
                ])->all(),
            ])->all() ?? [],
            'whatsapp' => $this->whatsapp($lead),
            'escalation' => $this->openEscalation($lead),
            'intelligence' => $this->intelligence($lead),
            'follow_ups' => $this->followUps($lead),
            'notes' => $lead->notes->map(fn (LeadNote $note): array => [
                'id' => $note->id,
                'body' => $note->body,
                'author' => $note->author->name,
                'at' => $this->clock->formatDateTime($note->created_at),
            ])->all(),
            'interactions' => $lead->interactions->map(fn (LeadInteraction $row): array => [
                'id' => $row->id,
                'type' => $row->type->value,
                'type_label' => $row->type->label(),
                'direction' => $row->direction->value,
                'body' => $row->body,
                'occurred_at_label' => $this->clock->formatDateTime($row->occurred_at),
                'duration_minutes' => $row->duration_minutes,
                'outcome' => $row->outcome,
                'author' => $row->author->name,
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
            ActivityEvent::ContactNoteAdded => 'Client note added',
            ActivityEvent::InteractionRecorded => 'Interaction recorded'
                .(isset($properties['type']) && is_string($properties['type'])
                    ? ': '.str_replace('_', ' ', $properties['type'])
                    : ''),
            ActivityEvent::ConversationScheduled => 'Conversation scheduled'
                .(array_key_exists('to', $properties)
                    ? ': '.($properties['to'] ?: 'cleared')
                    : ''),
            ActivityEvent::ContactMerged => 'Contact merged'
                .(isset($properties['merged_name'], $properties['survivor_name'])
                    ? ': '.$properties['merged_name'].' into '.$properties['survivor_name']
                    : ''),
            ActivityEvent::FollowUpChanged => 'Follow-up date changed'
                .(array_key_exists('to', $properties)
                    ? ': '.($properties['from'] ?: 'none').' → '.($properties['to'] ?: 'none')
                    : ''),
            ActivityEvent::MessageRefused => 'WhatsApp send refused'
                .(isset($properties['reason']) && is_string($properties['reason'])
                    ? ': '.$properties['reason']
                    : ''),
            ActivityEvent::MessagingFlagChanged => is_string($properties['label'] ?? null)
                ? $properties['label']
                : 'Messaging controls changed',
            ActivityEvent::ContactOptedOut => 'Contact opted out of WhatsApp',
            ActivityEvent::AnalysisCompleted => 'Conversation analyzed'
                .(isset($properties['score']) && is_numeric($properties['score'])
                    ? ' · score '.$properties['score']
                    : ''),
            ActivityEvent::AnalysisFailed => 'Analysis did not run'
                .(isset($properties['reason']) && is_string($properties['reason'])
                    ? ': '.$properties['reason']
                    : ''),
            ActivityEvent::ExtractionApplied => 'Analysis fields applied',
            ActivityEvent::DraftEdited => 'Reply draft edited',
            ActivityEvent::DraftSent => 'Approved reply sent on WhatsApp',
            ActivityEvent::FollowUpScheduled => 'Follow-up scheduled'
                .(isset($properties['rule']) && is_string($properties['rule'])
                    ? ': '.$properties['rule']
                    : ''),
            ActivityEvent::FollowUpCancelled => 'Follow-up cancelled'
                .(isset($properties['reason']) && is_string($properties['reason'])
                    ? ': '.$properties['reason']
                    : ''),
            ActivityEvent::FollowUpHeld => 'Follow-up held'
                .(isset($properties['reason']) && is_string($properties['reason'])
                    ? ': '.$properties['reason']
                    : ''),
            ActivityEvent::FollowUpSent => 'Follow-up sent on WhatsApp',
            ActivityEvent::FollowUpRefused => 'Follow-up refused'
                .(isset($properties['reason']) && is_string($properties['reason'])
                    ? ': '.$properties['reason']
                    : ''),
            ActivityEvent::FollowUpCompleted => 'Follow-up marked done',
            ActivityEvent::ChatFollowUpCaptured => 'Follow-up date captured from chat',
            ActivityEvent::EscalatedToOperator => 'AI paused — needs your input'
                .(isset($properties['reason']) && is_string($properties['reason'])
                    ? ': '.$properties['reason']
                    : ''),
            ActivityEvent::OperatorCorrectionApplied => 'Operator correction saved'
                .(! empty($properties['resumed']) ? ' · automation resumed' : ''),
            ActivityEvent::KnowledgeGapAnswered => 'Unfamiliar question answered'
                .(isset($properties['category']) && is_string($properties['category'])
                    ? ' · '.$properties['category']
                    : ''),
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function openEscalation(Lead $lead): ?array
    {
        $row = OperatorEscalation::query()
            ->where('lead_id', $lead->id)
            ->where('status', 'open')
            ->orderByDesc('id')
            ->first();

        if ($row === null) {
            return null;
        }

        $lead->loadMissing(['contact', 'product', 'currentStage']);

        return [
            'id' => $row->id,
            'reason' => $row->reason,
            'inbound_snippet' => $row->inbound_snippet,
            'suggested_reply' => $row->suggested_reply,
            'source' => $row->source,
            'notified_at_label' => $this->clock->formatDateTime($row->notified_at),
            'contact_name' => $lead->contact->full_name,
            'lead_status' => $lead->outcome(),
            'product_name' => $lead->product->name,
            'stage_name' => $lead->currentStage->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function whatsapp(Lead $lead): array
    {
        $token = config('whatsapp.token');
        $phoneNumberId = config('whatsapp.phone_number_id');
        $lastInbound = $this->window->lastInboundAt($lead->contact);
        $open = $this->window->isOpen($lead->contact);
        $hours = (int) config('whatsapp.service_window_hours');
        $closesAt = $lastInbound?->copy()->addHours($hours);

        return [
            'configured' => is_string($token) && $token !== '' && is_string($phoneNumberId) && $phoneNumberId !== '',
            'window_open' => $open,
            'last_inbound_label' => $this->clock->formatDateTime($lastInbound),
            'window_closes_label' => $open ? $this->clock->formatDateTime($closesAt) : null,
            'automation_paused' => $lead->automation_paused,
            'taken_over' => $lead->taken_over,
            'needs_personal_attention' => $lead->needs_personal_attention,
            'conversations_stopped' => $lead->conversations_stopped,
            'automatic_sends_paused' => DeskSetting::current()->automatic_sends_paused,
            'opted_out' => $lead->contact->consent_status->value === 'opted_out',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function followUps(Lead $lead): array
    {
        $tasks = FollowUpTask::query()
            ->with('rule:id,name')
            ->where('lead_id', $lead->id)
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $rows = [];

        foreach ($tasks as $task) {
            $rows[] = [
                'id' => $task->id,
                'rule' => $task->rule?->name,
                'action' => $task->action->value,
                'body' => $task->body,
                'status' => $task->status->value,
                'cancel_on_reply' => $task->cancel_on_reply,
                'due_label' => $this->clock->formatDateTime($task->due_at),
                'held_reason' => $task->held_reason,
                'failure_reason' => $task->failure_reason,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function intelligence(Lead $lead): array
    {
        $configured = $this->llm->ready();
        $latest = LeadAnalysis::query()
            ->with(['draft', 'suggestedStage'])
            ->where('lead_id', $lead->id)
            ->orderByDesc('id')
            ->first();

        if ($latest === null) {
            return [
                'configured' => $configured,
                'latest' => null,
            ];
        }

        $articleIds = $latest->knowledge_article_ids ?? [];
        $articles = $articleIds === []
            ? collect()
            : KnowledgeArticle::query()->whereIn('id', $articleIds)->get(['id', 'title']);

        return [
            'configured' => $configured,
            'latest' => [
                'id' => $latest->id,
                'status' => $latest->status->value,
                'summary' => $latest->summary,
                'score' => $latest->score,
                'score_reasons' => $latest->score_reasons ?? [],
                'extraction' => $this->extractionFields($latest->extraction),
                'suggested_stage' => $latest->suggestedStage === null ? null : [
                    'id' => $latest->suggestedStage->id,
                    'name' => $latest->suggestedStage->name,
                ],
                'stage_reason' => $latest->stage_reason,
                'stage_rejected_reason' => $latest->stage_rejected_reason,
                'knowledge' => $articles->map(fn (KnowledgeArticle $article): array => [
                    'id' => $article->id,
                    'title' => $article->title,
                ])->values()->all(),
                'error' => $latest->error,
                'completed_at_label' => $this->clock->formatDateTime($latest->completed_at),
                'draft' => $latest->draft === null ? null : [
                    'id' => $latest->draft->id,
                    'original_body' => $latest->draft->original_body,
                    'body' => $latest->draft->body,
                    'status' => $latest->draft->status->value,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $extraction
     * @return list<array{key: string, label: string, value: string}>
     */
    private function extractionFields(?array $extraction): array
    {
        if ($extraction === null) {
            return [];
        }

        $labels = [
            'business_problem' => 'Business problem',
            'current_solution' => 'Current solution',
            'branch_count' => 'Branch count',
            'budget_note' => 'Budget note',
            'decision_maker_status' => 'Decision maker',
            'purchase_timeline_note' => 'Purchase timeline',
            'next_action' => 'Next action',
            'next_follow_up_on' => 'Follow-up date',
        ];
        $rows = [];

        foreach ($labels as $key => $label) {
            $value = $extraction[$key] ?? null;

            if ($value === null || $value === '' || ! is_scalar($value)) {
                continue;
            }

            $rows[] = [
                'key' => $key,
                'label' => $label,
                'value' => (string) $value,
            ];
        }

        return $rows;
    }
}
