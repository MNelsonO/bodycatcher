<?php

namespace App\Http\Controllers;

use App\Conversations\MessageOrigin;
use App\Http\Requests\LeadMessagingRequest;
use App\Http\Requests\WhatsAppSendRequest;
use App\Leads\Actions\RecordActivity;
use App\Leads\ActivityEvent;
use App\Leads\Lead;
use App\Models\User;
use App\WhatsApp\SendOutcomeToast;
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

        SendOutcomeToast::flash($outcome);

        return redirect()->route('leads.show', $lead);
    }

    public function update(LeadMessagingRequest $request, Lead $lead, RecordActivity $activity): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $action = $request->string('action')->toString();

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
