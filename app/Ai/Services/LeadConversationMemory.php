<?php

namespace App\Ai\Services;

use App\Conversations\Conversation;
use App\Conversations\Message;
use App\Conversations\MessageDirection;
use App\Leads\Lead;
use App\SalesPipelines\DefaultKemetStages;
use App\Settings\DisplayClock;
use Illuminate\Support\Collection;

/**
 * Per-lead chat memory: transcript history, standing notes, RAG snippets,
 * and a rolling summary so follow-ups know how to deal with this person.
 */
class LeadConversationMemory
{
    public function __construct(
        private KnowledgeRetriever $knowledge,
        private DisplayClock $clock,
    ) {}

    /**
     * @return array{
     *     profile: string,
     *     transcript: string,
     *     rag: string,
     *     memory_summary: string,
     *     topic: string,
     *     touches: string
     * }
     */
    public function build(Lead $lead, ?int $historyLimit = null): array
    {
        $limit = $historyLimit ?? (int) config('ai.follow_up_history_limit', 40);
        $lead->loadMissing([
            'contact.notes',
            'interactions',
            'product',
            'currentStage',
            'conversation.messages',
        ]);

        $conversation = $lead->conversation;
        $transcript = $this->transcript($conversation, $limit);
        $rag = $this->ragBlock($lead);
        $touches = $this->touchesBlock($lead);
        $clientContext = $lead->contact->aiContextBlock();
        $hot = in_array($lead->currentStage->name, DefaultKemetStages::hotStages(), true) ? 'yes' : 'no';

        $profile = implode("\n", array_values(array_filter([
            'Contact: '.$lead->contact->full_name,
            'Phone: '.$lead->contact->primary_phone,
            'Business: '.($lead->contact->business_name ?? 'unknown'),
            'Product: '.$lead->product->name,
            'Stage: '.$lead->currentStage->name.' (hot: '.$hot.')',
            'Next action: '.($lead->next_action ?? 'none'),
            'Follow-up date: '.($lead->next_follow_up_on?->toDateString() ?? 'none'),
            'Scheduled talk: '.($this->clock->formatDateTime($lead->next_conversation_at) ?? 'none'),
            'Follow-up source: '.($lead->follow_up_source ?? 'none'),
            'Rank: '.(string) $lead->follow_up_rank.($lead->follow_up_rank_reason ? ' ('.$lead->follow_up_rank_reason.')' : ''),
            $clientContext !== '' ? "Client notes:\n".$clientContext : null,
            'Problem: '.($lead->business_problem ?? 'unknown'),
            'Timeline: '.($lead->purchase_timeline_note ?? 'unknown'),
        ])));

        return [
            'profile' => $profile,
            'transcript' => $transcript !== '' ? $transcript : 'No prior WhatsApp messages yet.',
            'rag' => $rag !== '' ? $rag : 'No matching knowledge articles.',
            'memory_summary' => (string) ($conversation?->memory_summary ?: 'No rolling memory yet.'),
            'topic' => trim((string) ($conversation?->topic ?? '').' / '.(string) ($conversation?->topic_summary ?? ''), ' /'),
            'touches' => $touches !== '' ? $touches : 'No desk touches logged.',
        ];
    }

    public function asPromptBlock(Lead $lead, ?int $historyLimit = null): string
    {
        $parts = $this->build($lead, $historyLimit);

        return <<<TEXT
Standing memory:
{$parts['memory_summary']}

Topic: {$parts['topic']}

Who this person is:
{$parts['profile']}

Recent desk touches:
{$parts['touches']}

Product / FAQ knowledge (RAG):
{$parts['rag']}

Conversation history (oldest → newest):
{$parts['transcript']}
TEXT;
    }

    private function transcript(?Conversation $conversation, int $limit): string
    {
        if ($conversation === null) {
            return '';
        }

        /** @var Collection<int, Message> $messages */
        $messages = Message::query()
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->limit(max(5, $limit))
            ->get()
            ->sortBy([['sent_at', 'asc'], ['id', 'asc']])
            ->values();

        return $messages->map(function (Message $message): string {
            $who = $message->direction === MessageDirection::Inbound ? 'Customer' : 'Desk';
            $when = $this->clock->formatDateTime($message->sent_at) ?? '';

            return trim($who.($when !== '' ? ' ['.$when.']' : '').': '.mb_substr(trim((string) $message->body), 0, 800));
        })->implode("\n");
    }

    private function ragBlock(Lead $lead): string
    {
        return $this->knowledge->solidLessonsFor($lead, '', 6)
            ->map(function ($article): string {
                $kind = (string) ($article->category ?? 'fact');

                return '- ['.$kind.'] '.$article->title.': '.mb_substr(trim($article->body), 0, 500);
            })
            ->implode("\n");
    }

    private function touchesBlock(Lead $lead): string
    {
        return $lead->interactions
            ->sortByDesc('occurred_at')
            ->take(8)
            ->map(function ($row): string {
                $when = $this->clock->formatDateTime($row->occurred_at) ?? 'unknown';

                return '- '.$row->type->label().' @ '.$when.': '.mb_substr(trim($row->body), 0, 300);
            })
            ->implode("\n");
    }
}
