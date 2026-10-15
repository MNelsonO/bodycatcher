<?php

namespace App\Ai\Services;

use App\Ai\OperatorEscalation;
use App\Calendar\CalendarActivity;
use App\Integrations\Services\GoogleCalendarSync;
use App\Leads\Actions\RecordActivity;
use App\Leads\ActivityEvent;
use App\Leads\Lead;
use App\Mail\OperatorEscalationMail;
use App\Models\User;
use App\Security\Role;
use App\Settings\DisplayClock;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Stops automation on a tricky lead, asks for operator input, and pings
 * via email + Google Calendar so you can follow through off WhatsApp.
 */
class EscalateToOperator
{
    public function __construct(
        private RecordActivity $activity,
        private GoogleCalendarSync $google,
        private DisplayClock $clock,
        private RaiseUnfamiliarQuestion $unfamiliar,
    ) {}

    public function handle(
        Lead $lead,
        string $reason,
        string $source = 'live_assistant',
        ?string $inboundSnippet = null,
        ?string $suggestedReply = null,
    ): OperatorEscalation {
        $lead->loadMissing(['contact', 'product', 'currentStage', 'conversation']);

        $open = OperatorEscalation::query()
            ->where('lead_id', $lead->id)
            ->where('status', 'open')
            ->first();

        if ($open !== null) {
            $open->reason = mb_substr(trim($reason), 0, 2000);
            if ($inboundSnippet) {
                $open->inbound_snippet = mb_substr(trim($inboundSnippet), 0, 2000);
            }
            if ($suggestedReply) {
                $open->suggested_reply = mb_substr(trim($suggestedReply), 0, 2000);
            }
            $open->save();

            return $open;
        }

        $lead->needs_personal_attention = true;
        $lead->automation_paused = true;
        $lead->save();

        $escalation = OperatorEscalation::query()->create([
            'lead_id' => $lead->id,
            'conversation_id' => $lead->conversation?->id,
            'status' => 'open',
            'source' => $source,
            'reason' => mb_substr(trim($reason), 0, 2000),
            'inbound_snippet' => $inboundSnippet ? mb_substr(trim($inboundSnippet), 0, 2000) : null,
            'suggested_reply' => $suggestedReply ? mb_substr(trim($suggestedReply), 0, 2000) : null,
        ]);

        $owner = User::query()->where('role', Role::Owner->value)->orderBy('id')->first()
            ?? User::query()->orderBy('id')->first();

        if ($owner !== null) {
            $activity = $this->calendarPing($owner, $lead, $escalation);
            if ($activity !== null) {
                $escalation->calendar_activity_id = $activity->id;
                $escalation->save();
            }

            $this->emailOwner($owner, $lead, $escalation);
            $escalation->notified_at = now();
            $escalation->save();
        }

        $this->unfamiliar->fromEscalation($lead, $escalation->reason, $escalation->inbound_snippet);

        $this->activity->handle(ActivityEvent::EscalatedToOperator, $lead, $lead->contact, $owner, [
            'escalation_id' => $escalation->id,
            'reason' => $escalation->reason,
            'source' => $source,
            'contact_name' => $lead->contact->full_name,
            'product_name' => $lead->product->name,
            'stage_name' => $lead->currentStage->name,
            'lead_status' => $lead->outcome(),
        ]);

        return $escalation;
    }

    private function calendarPing(User $owner, Lead $lead, OperatorEscalation $escalation): ?CalendarActivity
    {
        $when = $this->clock->now()->addMinutes(10);
        $status = $lead->outcome();
        $title = sprintf(
            'Desk needs you: %s · %s · %s',
            $lead->contact->full_name,
            $lead->product->name,
            $lead->currentStage->name,
        );
        $notes = implode("\n", array_filter([
            'AI paused automation — your input is required.',
            'Name: '.$lead->contact->full_name,
            'Status: '.$status,
            'System interested in: '.$lead->product->name,
            'Current position: '.$lead->currentStage->name,
            'Phone: '.$lead->contact->primary_phone,
            $lead->contact->business_name ? 'Business: '.$lead->contact->business_name : null,
            'Reason: '.$escalation->reason,
            $escalation->inbound_snippet ? 'Chat: '.$escalation->inbound_snippet : null,
            $escalation->suggested_reply ? 'Suggested (not sent): '.$escalation->suggested_reply : null,
            'Lead: '.url('/leads/'.$lead->id),
        ]));

        $activity = CalendarActivity::query()->create([
            'user_id' => $owner->id,
            'activity_on' => $when->toDateString(),
            'title' => $title,
            'notes' => $notes,
            'activity_type' => 'escalation',
            'color' => 'red',
            'lead_id' => $lead->id,
        ]);

        try {
            $this->google->pushTimed($activity, $when, $when->copy()->addMinutes(15));
        } catch (Throwable $exception) {
            Log::warning('Escalation calendar ping failed.', ['error' => $exception->getMessage()]);
        }

        return $activity;
    }

    private function emailOwner(User $owner, Lead $lead, OperatorEscalation $escalation): void
    {
        if (! filled($owner->email)) {
            return;
        }

        try {
            Mail::to($owner->email)->send(new OperatorEscalationMail(
                $escalation,
                $lead,
                url('/leads/'.$lead->id),
            ));
        } catch (Throwable $exception) {
            Log::warning('Escalation email failed.', [
                'user_id' => $owner->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
