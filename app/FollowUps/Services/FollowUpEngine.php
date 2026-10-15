<?php

namespace App\FollowUps\Services;

use App\Conversations\Message;
use App\Conversations\MessageChannel;
use App\Conversations\MessageDirection;
use App\Conversations\MessageOrigin;
use App\Conversations\MessageStatus;
use App\FollowUps\AutomationRule;
use App\FollowUps\FollowUpAction;
use App\FollowUps\FollowUpTask;
use App\FollowUps\FollowUpTaskStatus;
use App\FollowUps\FollowUpTrigger;
use App\Leads\Actions\RecordActivity;
use App\Leads\ActivityEvent;
use App\Leads\Lead;
use App\Leads\LeadStageHistory;
use App\Models\User;
use App\Security\Role;
use App\Settings\DisplayClock;
use App\WhatsApp\Services\MessagingGuard;
use App\WhatsApp\Services\SendWhatsAppMessage;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FollowUpEngine
{
    public function __construct(
        private DisplayClock $clock,
        private MessagingGuard $guard,
        private SendWhatsAppMessage $sender,
        private CancelFollowUpsOnReply $cancellations,
        private RecordActivity $activity,
    ) {}

    public function run(): void
    {
        AutomationRule::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->each(function (AutomationRule $rule): void {
                $this->schedule($rule);
            });

        $this->cancelInactiveRules();
        $this->cancelClosedLeads();
        $this->executeDue();
    }

    private function schedule(AutomationRule $rule): void
    {
        match ($rule->trigger) {
            FollowUpTrigger::NoResponse => $this->scheduleNoResponse($rule),
            FollowUpTrigger::FollowUpDue => $this->scheduleFollowUpDue($rule),
            FollowUpTrigger::StageDelay => $this->scheduleStageDelay($rule),
        };
    }

    private function scheduleNoResponse(AutomationRule $rule): void
    {
        $hours = $rule->delay_hours;

        if ($hours === null || $hours < 1) {
            return;
        }

        foreach ($this->openLeads($rule) as $lead) {
            $outbound = $this->lastOutbound($lead);
            $replied = $outbound !== null && $this->repliedAfter($lead, $outbound->sent_at);
            $key = $outbound === null || $replied
                ? null
                : 'no-response:'.$rule->id.':'.$lead->id.':'.$outbound->id;

            $this->supersede($rule, $lead, $key);

            if ($outbound === null || $replied || $key === null) {
                continue;
            }

            $this->createTask(
                $rule,
                $lead,
                $key,
                $outbound->sent_at->copy()->addHours($hours),
                $outbound->sent_at,
                true,
            );
        }
    }

    private function scheduleFollowUpDue(AutomationRule $rule): void
    {
        if (! is_string($rule->local_time) || preg_match('/^\d{2}:\d{2}$/', $rule->local_time) !== 1) {
            return;
        }

        foreach ($this->openLeads($rule) as $lead) {
            $date = $lead->next_follow_up_on?->toDateString();
            $key = $date === null ? null : 'follow-up-due:'.$rule->id.':'.$lead->id.':'.$date;

            $this->supersede($rule, $lead, $key);

            if ($date === null || $key === null) {
                continue;
            }

            $due = Carbon::parse($date.' '.$rule->local_time, $this->clock->timezone())->utc();
            $this->createTask($rule, $lead, $key, $due, null, false);
        }
    }

    private function scheduleStageDelay(AutomationRule $rule): void
    {
        foreach ($this->openLeads($rule) as $lead) {
            $lead->loadMissing('currentStage');
            $hours = $lead->currentStage->default_follow_up_delay_hours;
            $history = LeadStageHistory::query()
                ->where('lead_id', $lead->id)
                ->where('to_stage_id', $lead->current_stage_id)
                ->orderByDesc('id')
                ->first();

            if ($hours === null || $hours < 1 || $history === null || $history->created_at === null) {
                continue;
            }

            $anchor = $history->created_at;
            $replied = $this->repliedAfter($lead, $anchor);
            $key = $replied ? null : 'stage-delay:'.$rule->id.':'.$lead->id.':'.$history->id;
            $this->supersede($rule, $lead, $key);

            if ($replied || $key === null) {
                continue;
            }

            $this->createTask(
                $rule,
                $lead,
                $key,
                $anchor->copy()->addHours($hours),
                $anchor,
                true,
            );
        }
    }

    private function createTask(
        AutomationRule $rule,
        Lead $lead,
        string $key,
        CarbonInterface $due,
        ?CarbonInterface $anchor,
        bool $cancelOnReply,
    ): void {
        $task = FollowUpTask::query()->firstOrCreate(
            ['idempotency_key' => $key],
            [
                'lead_id' => $lead->id,
                'automation_rule_id' => $rule->id,
                'action' => $rule->action,
                'body' => $rule->body,
                'due_at' => $due,
                'anchor_at' => $anchor,
                'cancel_on_reply' => $cancelOnReply,
                'status' => FollowUpTaskStatus::Pending,
            ],
        );

        if (! $task->wasRecentlyCreated) {
            return;
        }

        $this->activity->handle(ActivityEvent::FollowUpScheduled, $lead, $lead->contact, null, [
            'task_id' => $task->id,
            'rule' => $rule->name,
            'due_at' => $due->toIso8601String(),
        ]);
    }

    private function supersede(AutomationRule $rule, Lead $lead, ?string $currentKey): void
    {
        FollowUpTask::query()
            ->where('lead_id', $lead->id)
            ->where('automation_rule_id', $rule->id)
            ->where('status', FollowUpTaskStatus::Pending)
            ->when($currentKey !== null, fn ($query) => $query->where('idempotency_key', '!=', $currentKey))
            ->get()
            ->each(fn (FollowUpTask $task) => $this->cancellations->cancel(
                $task,
                'A newer follow-up replaced this task.',
            ));
    }

    private function cancelInactiveRules(): void
    {
        FollowUpTask::query()
            ->where('status', FollowUpTaskStatus::Pending)
            ->whereHas('rule', fn ($query) => $query->where('is_active', false))
            ->get()
            ->each(fn (FollowUpTask $task) => $this->cancellations->cancel(
                $task,
                'The automation rule is inactive.',
            ));
    }

    private function cancelClosedLeads(): void
    {
        FollowUpTask::query()
            ->where('status', FollowUpTaskStatus::Pending)
            ->whereHas('lead', fn ($query) => $query->whereNotNull('closed_at'))
            ->get()
            ->each(fn (FollowUpTask $task) => $this->cancellations->cancel(
                $task,
                'The lead is closed.',
            ));
    }

    private function executeDue(): void
    {
        $actor = User::query()->where('role', Role::Owner)->orderBy('id')->first()
            ?? User::query()->orderBy('id')->first();

        FollowUpTask::query()
            ->where('status', FollowUpTaskStatus::Pending)
            ->where('action', FollowUpAction::SendWhatsApp)
            ->where('due_at', '<=', now())
            ->orderBy('id')
            ->each(function (FollowUpTask $task) use ($actor): void {
                $this->execute($task, $actor);
            });
    }

    private function execute(FollowUpTask $task, ?User $actor): void
    {
        $task->load('lead.contact', 'lead.conversation');
        $lead = $task->lead;

        if ($lead->closed_at !== null) {
            $this->cancellations->cancel($task, 'The lead is closed.');

            return;
        }

        if ($task->cancel_on_reply && $task->anchor_at !== null && $this->repliedAfter($lead, $task->anchor_at)) {
            $this->cancellations->cancel($task, 'The contact replied, so this no-response follow-up was cancelled.');

            return;
        }

        $pause = $this->guard->pauseReason($lead);

        if ($pause !== null) {
            $this->hold($task, $lead, $pause);

            return;
        }

        if ($actor === null) {
            $this->hold($task, $lead, 'No desk user is available to record the send.');

            return;
        }

        $task->held_reason = null;
        $task->save();

        try {
            $result = $this->sender->send(
                $lead,
                $this->render($task->body, $lead),
                $actor,
                MessageOrigin::Automatic,
                'follow-up-task-'.$task->id,
            );
        } catch (ValidationException $exception) {
            $this->settle($task, $lead, FollowUpTaskStatus::Refused, $this->validationMessage($exception), null);

            return;
        }

        $message = $result->message;

        if ($message->status === MessageStatus::Failed) {
            $this->settle($task, $lead, FollowUpTaskStatus::Failed, 'WhatsApp did not accept the follow-up.', $message->id);

            return;
        }

        $this->settle($task, $lead, FollowUpTaskStatus::Sent, null, $message->id);
    }

    private function hold(FollowUpTask $task, Lead $lead, string $reason): void
    {
        if ($task->held_reason === $reason) {
            return;
        }

        $task->held_reason = $reason;
        $task->save();
        $this->activity->handle(ActivityEvent::FollowUpHeld, $lead, $lead->contact, null, [
            'task_id' => $task->id,
            'reason' => $reason,
        ]);
    }

    private function settle(FollowUpTask $task, Lead $lead, FollowUpTaskStatus $status, ?string $reason, ?int $messageId): void
    {
        DB::transaction(function () use ($task, $lead, $status, $reason, $messageId): void {
            $locked = FollowUpTask::query()->lockForUpdate()->find($task->id);

            if ($locked === null || $locked->status !== FollowUpTaskStatus::Pending) {
                return;
            }

            $locked->status = $status;
            $locked->failure_reason = $reason;
            $locked->held_reason = null;
            $locked->message_id = $messageId;
            $locked->executed_at = now();
            $locked->save();

            $event = match ($status) {
                FollowUpTaskStatus::Sent => ActivityEvent::FollowUpSent,
                FollowUpTaskStatus::Refused => ActivityEvent::FollowUpRefused,
                FollowUpTaskStatus::Failed => ActivityEvent::FollowUpRefused,
                default => ActivityEvent::FollowUpCancelled,
            };

            $this->activity->handle($event, $lead, $lead->contact, null, [
                'task_id' => $locked->id,
                'reason' => $reason,
            ]);
        });
    }

    private function render(string $body, Lead $lead): string
    {
        return strtr($body, [
            '{contact}' => $lead->contact->full_name,
            '{business}' => $lead->contact->business_name ?? 'your business',
            '{action}' => $lead->next_action ?? 'the next step',
        ]);
    }

    private function validationMessage(ValidationException $exception): string
    {
        $message = collect($exception->errors())->flatten()->first();

        return is_string($message) && $message !== '' ? $message : 'The follow-up was refused.';
    }

    /**
     * @return Collection<int, Lead>
     */
    private function openLeads(AutomationRule $rule): Collection
    {
        return Lead::query()
            ->with(['contact', 'conversation', 'currentStage'])
            ->whereNull('closed_at')
            ->when($rule->product_id !== null, fn ($query) => $query->where('product_id', $rule->product_id))
            ->orderBy('id')
            ->get();
    }

    private function lastOutbound(Lead $lead): ?Message
    {
        $conversationId = $lead->conversation?->id;

        if ($conversationId === null) {
            return null;
        }

        return Message::query()
            ->where('conversation_id', $conversationId)
            ->where('channel', MessageChannel::WhatsApp)
            ->where('direction', MessageDirection::Outbound)
            ->whereIn('status', [
                MessageStatus::Queued,
                MessageStatus::Sent,
                MessageStatus::Delivered,
                MessageStatus::Read,
                MessageStatus::Unknown,
            ])
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->first();
    }

    private function repliedAfter(Lead $lead, CarbonInterface $anchor): bool
    {
        $conversationId = $lead->conversation?->id;

        if ($conversationId === null) {
            return false;
        }

        return Message::query()
            ->where('conversation_id', $conversationId)
            ->where('channel', MessageChannel::WhatsApp)
            ->where('direction', MessageDirection::Inbound)
            ->where('sent_at', '>=', $anchor)
            ->exists();
    }
}
