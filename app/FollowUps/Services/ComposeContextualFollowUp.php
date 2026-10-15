<?php

namespace App\FollowUps\Services;

use App\Ai\LlmClient;
use App\Ai\LlmConfiguration;
use App\Ai\Services\EscalateToOperator;
use App\Ai\Services\LeadConversationMemory;
use App\Ai\Services\ReplyGroundingGate;
use App\Conversations\Conversation;
use App\FollowUps\AutomationRule;
use App\FollowUps\FollowUpTask;
use App\FollowUps\FollowUpTrigger;
use App\Leads\Lead;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writes a WhatsApp follow-up from this lead's chat history + RAG memory.
 * Falls back to the rule template when the model is off or fails.
 */
class ComposeContextualFollowUp
{
    public function __construct(
        private LeadConversationMemory $memory,
        private LlmClient $llm,
        private LlmConfiguration $llmConfig,
        private EscalateToOperator $escalate,
        private ReplyGroundingGate $grounding,
    ) {}

    /**
     * Returns the WhatsApp body, or null when the model escalated to a human
     * (automation is paused — do not send).
     */
    public function compose(FollowUpTask $task, Lead $lead, string $templateBody): ?string
    {
        $fallback = $this->renderTemplate($templateBody, $lead);
        $rule = $task->rule;

        if (! $this->shouldUseMemory($rule) || ! $this->llmConfig->ready()) {
            return $fallback;
        }

        $grounding = $this->grounding->evaluate($lead);

        if (! $grounding['ok']) {
            $this->escalate->handle(
                $lead,
                $grounding['reason'],
                'follow_up',
                null,
                $fallback,
            );

            return null;
        }

        $context = $this->memory->asPromptBlock($lead);
        $trigger = $this->triggerLabel($rule, $task);
        $lessons = $grounding['lessons']
            ->map(fn ($article): string => '- ['.($article->category ?? 'fact').'] '.$article->title.': '.mb_substr(trim($article->body), 0, 500))
            ->implode("\n");

        $system = <<<'PROMPT'
You are a Kemet Tech sales person sending a WhatsApp follow-up — sound human, not corporate-bot.
Continue THIS person's conversation using history + solid lessons only.
Never invent prices, discounts, contracts, or meetings.
No markdown. 1–4 short natural sentences.
If history is thin or lessons do not cover what you would say, escalate instead of sending vague filler.
PROMPT;

        $user = <<<PROMPT
Trigger: {$trigger}
Template fallback (only if still faithful to the chat — otherwise escalate):
{$fallback}

Solid lessons:
{$lessons}

{$context}

If grounded:
REPLY: <human WhatsApp text>
MEMORY: <one updated sentence about how to deal with this person next time>

If not grounded:
ESCALATE: <why>
SUGGESTED_REPLY: <optional draft>
PROMPT;

        try {
            $raw = $this->llm->complete($system, $user);
        } catch (Throwable $exception) {
            Log::warning('Contextual follow-up LLM failed.', [
                'lead_id' => $lead->id,
                'task_id' => $task->id,
                'error' => $exception->getMessage(),
            ]);

            return $fallback;
        }

        $parsed = $this->parse($raw);

        if ($parsed['memory'] !== '') {
            $this->storeMemory($lead, $parsed['memory']);
        }

        if ($parsed['escalate'] !== '') {
            $this->escalate->handle(
                $lead,
                $parsed['escalate'],
                'follow_up',
                null,
                $parsed['suggested'] !== '' ? $parsed['suggested'] : $fallback,
            );

            return null;
        }

        $reply = $parsed['reply'] !== '' ? $parsed['reply'] : $fallback;

        return mb_substr($reply, 0, 1500);
    }

    private function shouldUseMemory(?AutomationRule $rule): bool
    {
        if ($rule === null) {
            return true;
        }

        return (bool) $rule->use_conversation_memory;
    }

    private function triggerLabel(?AutomationRule $rule, FollowUpTask $task): string
    {
        $name = $rule?->name ?? 'Follow-up';
        $trigger = $rule?->trigger;

        $kind = match ($trigger) {
            FollowUpTrigger::NoResponse => 'No reply after our last WhatsApp',
            FollowUpTrigger::FollowUpDue => 'Follow-up date is due',
            FollowUpTrigger::StageDelay => 'Stage follow-up delay elapsed',
            default => 'Scheduled follow-up',
        };

        return $name.' — '.$kind.' (task #'.$task->id.')';
    }

    private function renderTemplate(string $body, Lead $lead): string
    {
        $lead->loadMissing('contact');

        return strtr($body, [
            '{contact}' => $lead->contact->full_name,
            '{business}' => $lead->contact->business_name ?? 'your business',
            '{action}' => $lead->next_action ?? 'the next step',
        ]);
    }

    /**
     * @return array{reply: string, memory: string, escalate: string, suggested: string}
     */
    private function parse(string $raw): array
    {
        $reply = '';
        $memory = '';
        $escalate = '';
        $suggested = '';

        foreach (preg_split("/\r\n|\n|\r/", $raw) ?: [] as $line) {
            $line = trim($line);
            $upper = mb_strtoupper($line);

            if (str_starts_with($upper, 'ESCALATE:')) {
                $escalate = trim(mb_substr($line, 9));
            } elseif (str_starts_with($upper, 'SUGGESTED_REPLY:')) {
                $suggested = trim(mb_substr($line, 16));
            } elseif (str_starts_with($upper, 'REPLY:')) {
                $reply = trim(mb_substr($line, 6));
            } elseif (str_starts_with($upper, 'MEMORY:')) {
                $memory = trim(mb_substr($line, 7));
            }
        }

        if ($reply === '' && $escalate === '' && ! str_contains(mb_strtoupper($raw), 'REPLY:')) {
            $reply = trim($raw);
        }

        return [
            'reply' => $reply,
            'memory' => mb_substr($memory, 0, 500),
            'escalate' => $escalate,
            'suggested' => $suggested,
        ];
    }

    private function storeMemory(Lead $lead, string $summary): void
    {
        $conversation = $lead->conversation ?? Conversation::query()->create([
            'lead_id' => $lead->id,
            'contact_id' => $lead->contact_id,
            'focused_product_id' => $lead->product_id,
        ]);

        $conversation->memory_summary = $summary;
        $conversation->save();
    }
}
