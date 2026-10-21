<?php

namespace App\Ai\Services;

use App\Ai\LlmClient;
use App\Ai\LlmConfiguration;
use App\Contacts\Actions\CaptureChatContactName;
use App\Conversations\Conversation;
use App\Conversations\MessageOrigin;
use App\Leads\Lead;
use App\Models\User;
use App\Security\Role;
use App\Settings\Product;
use App\WhatsApp\DeskSetting;
use App\WhatsApp\Services\MessagingGuard;
use App\WhatsApp\Services\SendWhatsAppMessage;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Live webhook assistant. Knows the product catalog, the lead's product,
 * recent messages, and the running topic. Replies only when the desk has
 * live_assistant_enabled and LLM_* is configured.
 */
class LiveConversationAssistant
{
    public function __construct(
        private SendWhatsAppMessage $sender,
        private MessagingGuard $guard,
        private LlmClient $llm,
        private LlmConfiguration $llmConfig,
        private EscalateToOperator $escalate,
        private ReplyGroundingGate $grounding,
        private LeadConversationMemory $memory,
        private CaptureChatContactName $captureName,
    ) {}

    public function enabled(): bool
    {
        return DeskSetting::current()->live_assistant_enabled
            && $this->llmConfig->ready();
    }

    public function handle(Lead $lead, string $inboundBody): void
    {
        if (! $this->enabled()) {
            return;
        }

        $lead->loadMissing(['contact.notes', 'interactions', 'product', 'currentStage', 'conversation.messages']);

        if ($this->guard->pauseReason($lead) !== null) {
            return;
        }

        $body = trim($inboundBody);

        if ($body === '') {
            return;
        }

        $conversation = $lead->conversation ?? Conversation::query()->create([
            'lead_id' => $lead->id,
            'contact_id' => $lead->contact_id,
            'focused_product_id' => $lead->product_id,
        ]);

        if ($conversation->focused_product_id === null) {
            $conversation->focused_product_id = $lead->product_id;
            $conversation->save();
        }

        $grounding = $this->grounding->evaluate($lead->fresh(['conversation.messages', 'product', 'contact', 'currentStage', 'interactions']) ?? $lead, $body);

        if (! $grounding['ok']) {
            $this->escalate->handle(
                $lead,
                $grounding['reason'],
                'live_assistant',
                $body,
                null,
            );

            return;
        }

        $products = Product::query()
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description', 'pricing_notes']);

        $catalog = $products->map(function (Product $product): string {
            $bits = [
                "- {$product->name} (slug: {$product->slug})",
            ];

            if (filled($product->description)) {
                $bits[] = '  description: '.$product->description;
            }

            if (filled($product->pricing_notes)) {
                $bits[] = '  pricing notes: '.$product->pricing_notes;
            }

            return implode("\n", $bits);
        })->implode("\n");

        $lessons = $grounding['lessons']
            ->map(function ($article): string {
                $kind = (string) ($article->category ?? 'fact');

                return '- ['.$kind.'] '.$article->title.': '.mb_substr(trim($article->body), 0, 600);
            })
            ->implode("\n");
        $lessonsBlock = $lessons !== ''
            ? $lessons
            : 'No solid lesson matched — only greet / acknowledge; escalate if they ask for facts.';

        $historyLimit = (int) config('ai.live_history_limit', 40);
        $memoryBlock = $this->memory->asPromptBlock($lead, $historyLimit);
        $provider = (string) config('ai.provider', 'openai');
        $productName = $lead->product->name;
        $contactName = $lead->contact->full_name;
        $stageName = $lead->currentStage->name;

        $system = <<<PROMPT
You are a Meta Business-style WhatsApp sales agent for Kemet Tech — one conversation only (this lead), not a generic chatbot.
Provider: {$provider}.
Write valuable, short WhatsApp replies: warm, clear, natural Kenyan business English. No markdown, no bullet walls, no "As an AI".
Treat knowledge notes / solid lessons as training — prefer them over guesses.
Never invent prices, discounts, contracts, dates, features, or personal facts.
If you are unsure, history is thin, or lessons do not cover the ask — ESCALATE. Never bluff.
Stay on THIS conversation only. Do not change the lead's pipeline stage.
Keep the lead on its current stage: {$stageName}.

Contact (may be a placeholder): {$contactName}
Assigned product: {$productName}

Solid lessons (RAG — trust these):
{$lessonsBlock}

Product catalog (slugs for PRODUCT_SLUG only — do not change stage):
{$catalog}

{$memoryBlock}
PROMPT;

        $user = <<<PROMPT
Latest customer message:
{$body}

If you understand and have solid grounding:
1) REPLY: <human WhatsApp text, 1–4 short sentences>
2) TOPIC: <short topic>
3) TOPIC_SUMMARY: <one sentence>
4) MEMORY: <how to deal with this person next time>
5) CONTACT_NAME: <only if they clearly gave their name in chat; else omit>
6) PRODUCT_SLUG: <optional catalog slug if they clearly want a different product; never change stage>

If anything is unclear, thin, or not covered by solid lessons:
1) ESCALATE: <why>
2) SUGGESTED_REPLY: <optional draft for the human — not sent>
3) MEMORY: <optional>
PROMPT;

        try {
            $raw = $this->llm->complete($system, $user);
        } catch (Throwable $exception) {
            Log::warning('Live assistant LLM failed.', [
                'lead_id' => $lead->id,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        $parsed = $this->parse($raw);

        if ($parsed['contact_name'] !== '') {
            $this->captureName->applyName($lead, $parsed['contact_name']);
        }

        // PRODUCT_SLUG may appear in the model reply for awareness; never auto-change stage or product.

        if ($parsed['memory'] !== '') {
            $conversation->memory_summary = mb_substr($parsed['memory'], 0, 500);
            $conversation->topic_updated_at = now();
            $conversation->save();
        }

        if ($parsed['escalate'] !== '') {
            $this->escalate->handle(
                $lead,
                $parsed['escalate'],
                'live_assistant',
                $body,
                $parsed['suggested'] !== '' ? $parsed['suggested'] : null,
            );

            return;
        }

        $reply = $parsed['reply'];

        if ($reply === '') {
            return;
        }

        if ($parsed['topic'] !== '') {
            $conversation->topic = mb_substr($parsed['topic'], 0, 160);
        }

        if ($parsed['topic_summary'] !== '') {
            $conversation->topic_summary = mb_substr($parsed['topic_summary'], 0, 1000);
        }

        $conversation->topic_updated_at = now();
        $conversation->save();

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
                'live-assistant-'.$lead->id.'-'.hash('sha256', $body.'|'.$conversation->id),
            );
        } catch (Throwable $exception) {
            Log::warning('Live assistant send failed.', [
                'lead_id' => $lead->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array{reply: string, topic: string, topic_summary: string, memory: string, escalate: string, suggested: string, contact_name: string, product_slug: string}
     */
    private function parse(string $raw): array
    {
        $reply = '';
        $topic = '';
        $summary = '';
        $memory = '';
        $escalate = '';
        $suggested = '';
        $contactName = '';
        $productSlug = '';
        $mode = null;

        foreach (preg_split("/\r\n|\n|\r/", trim($raw)) ?: [] as $line) {
            $trimmed = trim($line);
            $upper = mb_strtoupper($trimmed);

            if (str_starts_with($upper, 'ESCALATE:')) {
                $mode = 'escalate';
                $escalate = trim(substr($trimmed, 9));

                continue;
            }

            if (str_starts_with($upper, 'SUGGESTED_REPLY:')) {
                $mode = 'suggested';
                $suggested = trim(substr($trimmed, 16));

                continue;
            }

            if (str_starts_with($upper, 'REPLY:')) {
                $mode = 'reply';
                $reply = trim(substr($trimmed, 6));

                continue;
            }

            if (str_starts_with($upper, 'TOPIC_SUMMARY:')) {
                $mode = 'summary';
                $summary = trim(substr($trimmed, 14));

                continue;
            }

            if (str_starts_with($upper, 'TOPIC:')) {
                $mode = 'topic';
                $topic = trim(substr($trimmed, 6));

                continue;
            }

            if (str_starts_with($upper, 'MEMORY:')) {
                $mode = 'memory';
                $memory = trim(substr($trimmed, 7));

                continue;
            }

            if (str_starts_with($upper, 'CONTACT_NAME:')) {
                $mode = 'contact_name';
                $contactName = trim(substr($trimmed, 13));

                continue;
            }

            if (str_starts_with($upper, 'PRODUCT_SLUG:')) {
                $mode = 'product_slug';
                $productSlug = trim(substr($trimmed, 13));

                continue;
            }

            if ($mode === 'reply') {
                $reply = trim($reply."\n".$trimmed);
            } elseif ($mode === 'topic') {
                $topic = trim($topic.' '.$trimmed);
            } elseif ($mode === 'summary') {
                $summary = trim($summary.' '.$trimmed);
            } elseif ($mode === 'memory') {
                $memory = trim($memory.' '.$trimmed);
            } elseif ($mode === 'escalate') {
                $escalate = trim($escalate.' '.$trimmed);
            } elseif ($mode === 'suggested') {
                $suggested = trim($suggested."\n".$trimmed);
            } elseif ($mode === 'contact_name') {
                $contactName = trim($contactName.' '.$trimmed);
            } elseif ($mode === 'product_slug') {
                $productSlug = trim($productSlug.' '.$trimmed);
            }
        }

        if ($reply === '' && $topic === '' && $summary === '' && $memory === '' && $escalate === '') {
            $reply = trim($raw);
        }

        return [
            'reply' => trim($reply),
            'topic' => trim($topic),
            'topic_summary' => trim($summary),
            'memory' => trim($memory),
            'escalate' => trim($escalate),
            'suggested' => trim($suggested),
            'contact_name' => trim($contactName),
            'product_slug' => trim($productSlug),
        ];
    }
}
