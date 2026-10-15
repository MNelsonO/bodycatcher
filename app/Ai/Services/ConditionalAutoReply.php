<?php

namespace App\Ai\Services;

use App\Ai\AutoReplyRule;
use App\Ai\LlmClient;
use App\Ai\LlmConfiguration;
use App\Conversations\MessageOrigin;
use App\Leads\Lead;
use App\Models\User;
use App\Security\Role;
use App\WhatsApp\Services\MessagingGuard;
use App\WhatsApp\Services\SendWhatsAppMessage;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Replies only when an owner-defined rule matches. No rule means silence.
 * LLM mode still needs LLM_* configured and only runs after a match.
 */
class ConditionalAutoReply
{
    public function __construct(
        private SendWhatsAppMessage $sender,
        private MessagingGuard $guard,
        private LlmClient $llm,
        private LlmConfiguration $llmConfig,
    ) {}

    public function handle(Lead $lead, string $inboundBody): void
    {
        $lead->loadMissing(['contact', 'currentStage']);

        if ($this->guard->pauseReason($lead) !== null) {
            return;
        }

        $body = trim($inboundBody);

        if ($body === '') {
            return;
        }

        $rule = $this->matchingRule($lead, $body);

        if ($rule === null) {
            return;
        }

        $reply = $this->compose($rule, $lead, $body);

        if ($reply === null || trim($reply) === '') {
            return;
        }

        $actor = User::query()->where('role', Role::Owner->value)->orderBy('id')->first();

        if ($actor === null) {
            return;
        }

        try {
            $this->sender->send(
                $lead,
                $reply,
                $actor,
                MessageOrigin::Automatic,
                'auto-reply-'.$rule->id.'-'.$lead->id.'-'.hash('sha256', $body),
            );
        } catch (Throwable $exception) {
            Log::warning('Conditional auto-reply failed.', [
                'lead_id' => $lead->id,
                'rule_id' => $rule->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function matchingRule(Lead $lead, string $body): ?AutoReplyRule
    {
        $rules = AutoReplyRule::query()
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        $lower = mb_strtolower($body);

        foreach ($rules as $rule) {
            if ($rule->pipeline_stage_id !== null && (int) $rule->pipeline_stage_id !== (int) $lead->current_stage_id) {
                continue;
            }

            $needle = mb_strtolower(trim((string) $rule->match_value));

            $matched = match ($rule->match_type) {
                'any' => true,
                'exact' => $needle !== '' && $lower === $needle,
                'contains' => $needle !== '' && str_contains($lower, $needle),
                default => false,
            };

            if ($matched) {
                return $rule;
            }
        }

        return null;
    }

    private function compose(AutoReplyRule $rule, Lead $lead, string $inboundBody): ?string
    {
        if ($rule->reply_mode === 'template') {
            $template = (string) $rule->template_body;

            return str_replace(
                ['{contact}', '{business}', '{message}'],
                [
                    $lead->contact->full_name,
                    (string) ($lead->contact->business_name ?? ''),
                    $inboundBody,
                ],
                $template,
            );
        }

        if ($rule->reply_mode !== 'llm' || ! $this->llmConfig->ready()) {
            return null;
        }

        try {
            $response = $this->llm->complete(
                'You write short WhatsApp replies for a Kenyan B2B sales desk. Stay factual. Do not invent prices or promises. Reply in plain text only.',
                "Contact: {$lead->contact->full_name}\nInbound message:\n{$inboundBody}\n\nWrite one short reply.",
            );
        } catch (Throwable) {
            return null;
        }

        return trim($response) !== '' ? trim($response) : null;
    }
}
