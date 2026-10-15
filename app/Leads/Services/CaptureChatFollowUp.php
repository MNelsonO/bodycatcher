<?php

namespace App\Leads\Services;

use App\Contacts\ConsentStatus;
use App\Leads\Actions\RecordActivity;
use App\Leads\Actions\ScheduleLeadConversation;
use App\Leads\Actions\SetFollowUp;
use App\Leads\ActivityEvent;
use App\Leads\Lead;
use App\Models\User;
use App\Security\Role;
use App\Settings\DisplayClock;

/**
 * Reads an inbound chat, places follow-up / talk dates, and re-ranks the lead.
 */
class CaptureChatFollowUp
{
    public function __construct(
        private ChatDateIntentExtractor $extractor,
        private SetFollowUp $followUps,
        private ScheduleLeadConversation $scheduler,
        private FollowUpRanker $ranker,
        private RecordActivity $activity,
        private DisplayClock $clock,
    ) {}

    public function handle(Lead $lead, string $inboundBody): bool
    {
        $lead->loadMissing(['contact', 'currentStage']);

        if ($lead->conversations_stopped || $lead->closed_at !== null) {
            return false;
        }

        if ($lead->contact->consent_status === ConsentStatus::OptedOut) {
            return false;
        }

        $hint = $this->extractor->extract($inboundBody);

        if ($hint === null || $hint->confidence < 70) {
            return false;
        }

        $actor = User::query()->where('role', Role::Owner->value)->orderBy('id')->first();

        if ($actor === null) {
            return false;
        }

        $followDate = $hint->followUpOn->toDateString();
        $existing = $lead->next_follow_up_on?->toDateString();
        $shouldReplaceFollowUp = $existing === null
            || $followDate <= $existing
            || $lead->follow_up_source === 'chat';

        if ($shouldReplaceFollowUp) {
            $this->followUps->handle($lead, $hint->action, $followDate, $actor, 'chat');
            $lead->refresh();
            $lead->follow_up_source = 'chat';
            $lead->chat_schedule_captured_at = $this->clock->now()->utc();
            $lead->save();
        } elseif ($lead->next_action === null || $lead->next_action === '') {
            $lead->next_action = $hint->action;
            $lead->save();
        }

        if ($hint->conversationAt !== null && ! $lead->conversations_stopped) {
            $existingTalk = $lead->next_conversation_at;
            $shouldSchedule = $existingTalk === null
                || $hint->conversationAt->lte($existingTalk)
                || $lead->follow_up_source === 'chat';

            if ($shouldSchedule) {
                $this->scheduler->handle(
                    $lead->refresh(),
                    $hint->conversationAt->utc()->toIso8601String(),
                    $hint->action,
                    $actor,
                );
            }
        }

        $lead = $this->ranker->refresh($lead->fresh(['currentStage', 'contact']) ?? $lead);

        $this->activity->handle(ActivityEvent::ChatFollowUpCaptured, $lead, $lead->contact, $actor, [
            'source' => 'chat',
            'follow_up_on' => $lead->next_follow_up_on?->toDateString(),
            'conversation_at' => $lead->next_conversation_at?->toIso8601String(),
            'snippet' => $hint->matchedSnippet,
            'rank' => $lead->follow_up_rank,
            'rank_reason' => $lead->follow_up_rank_reason,
        ]);

        return true;
    }
}
