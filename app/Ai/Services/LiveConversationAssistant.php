<?php

namespace App\Ai\Services;

use App\Ai\LlmClient;
use App\Ai\LlmConfiguration;
use App\Conversations\Conversation;
use App\Conversations\Message;
use App\Conversations\MessageDirection;
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

        $history = Message::query()
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->limit((int) config('ai.live_history_limit', 12))
            ->get()
            ->sortBy([['sent_at', 'asc'], ['id', 'asc']])
            ->values()
            ->map(function (Message $message): string {
                $who = $message->direction === MessageDirection::Inbound ? 'Customer' : 'Desk';

                return $who.': '.trim((string) $message->body);
            })
            ->implode("\n");

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

        $memoryPack = $this->memory->build($lead);
        $provider = (string) config('ai.provider', 'openai');
        $topic = (string) ($conversation->topic ?? 'General sales inquiry');
        $topicSummary = (string) ($conversation->topic_summary ?? 'No topic locked yet.');
        $memorySummary = $memoryPack['memory_summary'];
        $productName = $lead->product->name;
        $contactName = $lead->contact->full_name;
        $stageName = $lead->currentStage->name;

        $system = <<<PROMPT
You are a Kemet Tech sales person texting on WhatsApp — not a bot script.
Provider: {$provider}.
Write like a real human: warm, clear, short, natural Kenyan business WhatsApp English. No markdown, no bullet walls, no "As an AI".
Only reply when you understand THIS conversation from the history and solid lessons below.
Do not invent prices, discounts, contracts, dates, or features.
If history is thin, lessons are vague, or you are unsure — ESCALATE. Never bluff.
Use only catalog + solid lessons + memory. Prefer [lesson]/[fact]/[session] notes over guesses.
Stay on topic. Keep the lead on its current stage.

Contact: {$contactName}
Product: {$productName}
Stage: {$stageName}
Topic: {$topic}
Topic notes: {$topicSummary}
Memory: {$memorySummary}

Solid lessons (RAG — trust these):
{$lessonsBlock}

Product catalog:
{$catalog}

{$memoryPack['touches']}
PROMPT;

        $user = <<<PROMPT
Conversation history (must make sense before you reply):
{$history}

Latest customer message:
{$body}

If you understand and have solid grounding:
1) REPLY: <human WhatsApp text, 1–4 short sentences>
2) TOPIC: <short topic>
3) TOPIC_SUMMARY: <one sentence>
4) MEMORY: <how to deal with this person next time>

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
     * @return array{reply: string, topic: string, topic_summary: string, memory: string, escalate: string, suggested: string}
     */
    private function parse(string $raw): array
    {
        $reply = '';
        $topic = '';
        $summary = '';
        $memory = '';
        $escalate = '';
        $suggested = '';
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
        ];
    }
}
