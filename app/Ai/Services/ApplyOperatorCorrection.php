<?php

namespace App\Ai\Services;

use App\Ai\KnowledgeArticle;
use App\Ai\OperatorEscalation;
use App\Conversations\Conversation;
use App\Leads\Actions\RecordActivity;
use App\Leads\ActivityEvent;
use App\Leads\Lead;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Operator teaches the desk how to handle this person, then can resume automation.
 */
class ApplyOperatorCorrection
{
    public function __construct(private RecordActivity $activity) {}

    public function handle(
        OperatorEscalation $escalation,
        User $actor,
        string $correction,
        bool $resumeAutomation = true,
        ?string $sendAsReply = null,
    ): OperatorEscalation {
        if (! $escalation->isOpen()) {
            throw ValidationException::withMessages([
                'correction' => 'This escalation is already closed.',
            ]);
        }

        $correction = trim($correction);

        if ($correction === '') {
            throw ValidationException::withMessages([
                'correction' => 'Write what the desk should learn or say.',
            ]);
        }

        $lead = Lead::query()->with(['contact', 'conversation', 'product'])->findOrFail($escalation->lead_id);

        $escalation->operator_correction = mb_substr($correction, 0, 4000);
        $escalation->status = 'resolved';
        $escalation->resolved_by_user_id = $actor->id;
        $escalation->resolved_at = now();
        $escalation->save();

        $conversation = $lead->conversation ?? Conversation::query()->create([
            'lead_id' => $lead->id,
            'contact_id' => $lead->contact_id,
            'focused_product_id' => $lead->product_id,
        ]);

        $memory = trim((string) $conversation->memory_summary);
        $lesson = 'Operator correction: '.$correction;
        $conversation->memory_summary = mb_substr(
            $memory === '' ? $lesson : $memory."\n".$lesson,
            0,
            2000,
        );
        $conversation->save();

        KnowledgeArticle::query()->create([
            'title' => 'Correction · '.$lead->contact->full_name.' · '.mb_substr($escalation->reason, 0, 60),
            'body' => "When talking to {$lead->contact->full_name}"
                .($lead->contact->business_name ? " ({$lead->contact->business_name})" : '')
                .":\n{$correction}\n\nOriginal pause reason: {$escalation->reason}",
            'product_id' => $lead->product_id,
            'is_active' => true,
            'source' => 'operator_correction',
            'external_id' => 'escalation-'.$escalation->id,
            'synced_at' => now(),
        ]);

        $lead->needs_personal_attention = false;

        if ($resumeAutomation) {
            $lead->automation_paused = false;
            $lead->taken_over = false;
        }

        $lead->save();

        $this->activity->handle(ActivityEvent::OperatorCorrectionApplied, $lead, $lead->contact, $actor, [
            'escalation_id' => $escalation->id,
            'resumed' => $resumeAutomation,
            'had_suggested_send' => $sendAsReply !== null && trim($sendAsReply) !== '',
        ]);

        return $escalation->refresh();
    }
}
