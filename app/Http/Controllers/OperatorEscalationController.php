<?php

namespace App\Http\Controllers;

use App\Ai\OperatorEscalation;
use App\Ai\Services\ApplyOperatorCorrection;
use App\Conversations\MessageOrigin;
use App\Http\Requests\ResolveOperatorEscalationRequest;
use App\Leads\Lead;
use App\Models\User;
use App\WhatsApp\Services\SendWhatsAppMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Throwable;

class OperatorEscalationController extends Controller
{
    public function resolve(
        ResolveOperatorEscalationRequest $request,
        Lead $lead,
        OperatorEscalation $escalation,
        ApplyOperatorCorrection $corrections,
        SendWhatsAppMessage $sender,
    ): RedirectResponse {
        abort_unless($escalation->lead_id === $lead->id, 404);

        /** @var User $actor */
        $actor = $request->user();
        $correction = $request->string('correction')->toString();
        $resume = $request->boolean('resume_automation', true);
        $sendBody = $request->input('send_reply');
        $sendReply = is_string($sendBody) ? trim($sendBody) : '';

        $corrections->handle($escalation, $actor, $correction, $resume, $sendReply !== '' ? $sendReply : null);

        if ($sendReply !== '') {
            try {
                $sender->send(
                    $lead->fresh() ?? $lead,
                    $sendReply,
                    $actor,
                    MessageOrigin::Manual,
                    'escalation-reply-'.$escalation->id.'-'.Str::uuid()->toString(),
                );
            } catch (Throwable $exception) {
                report($exception);
                Inertia::flash('toast', [
                    'type' => 'warning',
                    'message' => 'Correction saved, but WhatsApp send failed: '.$exception->getMessage(),
                ]);

                return redirect()->route('leads.show', $lead);
            }
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $resume
                ? 'Correction saved. Automation can run again on this lead.'
                : 'Correction saved. Automation stays paused until you resume.',
        ]);

        return redirect()->route('leads.show', $lead);
    }
}
