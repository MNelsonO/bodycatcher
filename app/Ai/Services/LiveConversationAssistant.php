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

        $provider = (string) config('ai.provider', 'openai');
        $topic = (string) ($conversation->topic ?? 'General sales inquiry');
        $topicSummary = (string) ($conversation->topic_summary ?? 'No topic locked yet.');
        $productName = $lead->product->name;
        $contactName = $lead->contact->full_name;
        $stageName = $lead->currentStage->name;
        $marked = $lead->marked_on
            ? 'Marked date: '.$lead->marked_on->toDateString().($lead->marked_label ? ' ('.$lead->marked_label.')' : '')
            : 'No marked date yet.';
        $followUp = $lead->next_follow_up_on
            ? 'Follow-up date: '.$lead->next_follow_up_on->toDateString()
            : 'No follow-up date set.';
        $clientContext = $lead->contact->aiContextBlock();
        $clientContextBlock = $clientContext !== ''
            ? "\nClient market context:\n{$clientContext}\n"
            : "\n";
        $touchLines = $lead->interactions
            ->sortByDesc('occurred_at')
            ->take(8)
            ->map(function ($row): string {
                $when = $row->occurred_at?->toDateTimeString() ?? 'unknown time';

                return '- '.$row->type->label().' @ '.$when.': '.mb_substr(trim($row->body), 0, 400);
            })
            ->implode("\n");
        $touchesBlock = $touchLines !== ''
            ? "Recent desk touches (calls/meetings/notes):\n{$touchLines}\n"
            : '';
        $scheduleLine = $lead->next_conversation_at
            ? 'Next scheduled conversation: '.$lead->next_conversation_at->toDateTimeString()
            : 'No conversation scheduled.';
        $stoppedLine = $lead->conversations_stopped
            ? 'Future automated conversations are STOPPED on this lead.'
            : 'Automated conversations are allowed.';

        $system = <<<PROMPT
You are the live WhatsApp sales assistant for Kemet Tech.
Provider in use: {$provider}.
Stay on the conversation topic. Do not invent prices, discounts, or contracts.
Only discuss products from the catalog. If the customer asks about something else, steer back to the catalog and the current topic.
Respect the client's country and conversation area. Use desk notes and recorded touches as standing context.
Keep this lead on its current sales stage unless an operator moves it.
Reply in short plain WhatsApp text. No markdown. No bullet walls.

Current lead product: {$productName}
Contact: {$contactName}
Stage: {$stageName}
{$marked}
{$followUp}
{$scheduleLine}
{$stoppedLine}
{$clientContextBlock}{$touchesBlock}Running topic: {$topic}
Topic notes: {$topicSummary}

Product catalog:
{$catalog}
PROMPT;

        $user = <<<PROMPT
Conversation so far:
{$history}

Latest customer message:
{$body}

1) Write the WhatsApp reply only after a line that starts with REPLY:
2) Then update the topic on a line TOPIC:
3) Then a one-sentence topic summary on a line TOPIC_SUMMARY:
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
     * @return array{reply: string, topic: string, topic_summary: string}
     */
    private function parse(string $raw): array
    {
        $reply = '';
        $topic = '';
        $summary = '';
        $mode = null;

        foreach (preg_split("/\r\n|\n|\r/", trim($raw)) ?: [] as $line) {
            $trimmed = trim($line);

            if (str_starts_with(mb_strtoupper($trimmed), 'REPLY:')) {
                $mode = 'reply';
                $reply = trim(substr($trimmed, 6));

                continue;
            }

            if (str_starts_with(mb_strtoupper($trimmed), 'TOPIC_SUMMARY:')) {
                $mode = 'summary';
                $summary = trim(substr($trimmed, 14));

                continue;
            }

            if (str_starts_with(mb_strtoupper($trimmed), 'TOPIC:')) {
                $mode = 'topic';
                $topic = trim(substr($trimmed, 6));

                continue;
            }

            if ($mode === 'reply') {
                $reply = trim($reply."\n".$trimmed);
            } elseif ($mode === 'topic') {
                $topic = trim($topic.' '.$trimmed);
            } elseif ($mode === 'summary') {
                $summary = trim($summary.' '.$trimmed);
            }
        }

        if ($reply === '' && $topic === '' && $summary === '') {
            $reply = trim($raw);
        }

        return [
            'reply' => trim($reply),
            'topic' => trim($topic),
            'topic_summary' => trim($summary),
        ];
    }
}
