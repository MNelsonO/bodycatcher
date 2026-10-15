<?php

namespace App\Http\Controllers;

use App\Conversations\MessageOrigin;
use App\Conversations\MessageStatus;
use App\Http\Requests\LeadMessagingRequest;
use App\Http\Requests\WhatsAppSendRequest;
use App\Leads\Actions\RecordActivity;
use App\Leads\Actions\StopLeadConversations;
use App\Leads\ActivityEvent;
use App\Leads\Lead;
use App\Models\User;
use App\WhatsApp\Services\SendWhatsAppMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LeadMessagingController extends Controller
{
    public function send(WhatsAppSendRequest $request, Lead $lead, SendWhatsAppMessage $sender): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $outcome = $sender->send(
            $lead,
            $request->string('body')->toString(),
            $actor,
            MessageOrigin::Manual,
            $request->string('idempotency_key')->toString(),
        );

        $message = $outcome->message;

        if ($outcome->duplicate) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => 'That send was already recorded. WhatsApp was not called again.',
            ]);
        } elseif ($message->status === MessageStatus::Sent) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'WhatsApp accepted the message. Delivery is not confirmed yet.',
            ]);
        } elseif ($message->status === MessageStatus::Unknown) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'The request timed out after it might have reached WhatsApp. It was not sent again.',
            ]);
        } elseif ($message->status === MessageStatus::Failed) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'WhatsApp rejected the message. It was not marked delivered.',
            ]);
        } elseif ($message->status === MessageStatus::Queued) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => 'WhatsApp did not accept the message. The same row can be retried.',
            ]);
        }

        return redirect()->route('leads.show', $lead);
    }

    public function update(
        LeadMessagingRequest $request,
        Lead $lead,
        RecordActivity $activity,
        StopLeadConversations $stopper,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $action = $request->string('action')->toString();

        if ($action === 'stop_conversations') {
            $stopper->stop($lead, $actor);
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Future conversations stopped. Pending follow-ups were cancelled.',
            ]);

            return redirect()->route('leads.show', $lead);
        }

        if ($action === 'allow_conversations') {
            $stopper->allow($lead, $actor);
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Future conversations allowed again.',
            ]);

            return redirect()->route('leads.show', $lead);
        }

        $label = match ($action) {
            'pause' => $this->flag($lead, 'automation_paused', true, 'Automation paused'),
            'resume' => $this->flag($lead, 'automation_paused', false, 'Automation resumed'),
            'take_over' => $this->flag($lead, 'taken_over', true, 'Lead taken over'),
            'release' => $this->flag($lead, 'taken_over', false, 'Takeover ended'),
            'attention' => $this->flag($lead, 'needs_personal_attention', true, 'Flagged for personal attention'),
            'clear_attention' => $this->flag($lead, 'needs_personal_attention', false, 'Personal attention cleared'),
            default => throw ValidationException::withMessages([
                'action' => 'Unknown messaging action.',
            ]),
        };

        $lead->save();

        $activity->handle(ActivityEvent::MessagingFlagChanged, $lead, $lead->contact, $actor, [
            'action' => $action,
            'label' => $label,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $label.'.']);

        return redirect()->route('leads.show', $lead);
    }

    private function flag(Lead $lead, string $attribute, bool $value, string $label): string
    {
        if ($attribute === 'automation_paused') {
            $lead->automation_paused = $value;
        } elseif ($attribute === 'taken_over') {
            $lead->taken_over = $value;
        } else {
            $lead->needs_personal_attention = $value;
        }

        return $label;
    }
}
