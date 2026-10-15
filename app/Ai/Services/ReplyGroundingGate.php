<?php

namespace App\Ai\Services;

use App\Ai\KnowledgeArticle;
use App\Conversations\Message;
use App\Conversations\MessageDirection;
use App\Leads\Lead;
use Illuminate\Support\Collection;

/**
 * Decides whether the desk may auto-reply: needs a readable conversation
 * history plus solid lessons/facts — not vague placeholder knowledge.
 */
class ReplyGroundingGate
{
    public function __construct(private KnowledgeRetriever $knowledge) {}

    /**
     * @return array{
     *     ok: bool,
     *     reason: string,
     *     message_count: int,
     *     inbound_count: int,
     *     lessons: Collection<int, KnowledgeArticle>,
     *     has_memory: bool
     * }
     */
    public function evaluate(Lead $lead, string $inboundBody = ''): array
    {
        $lead->loadMissing(['conversation.messages', 'contact', 'product', 'currentStage']);

        $messages = $lead->conversation?->messages ?? collect();
        /** @var Collection<int, Message> $messages */
        $messageCount = $messages->count();
        $inboundCount = $messages->where('direction', MessageDirection::Inbound)->count();

        $lessons = $this->knowledge->solidLessonsFor($lead, $inboundBody, 5);
        $memory = trim((string) ($lead->conversation?->memory_summary ?? ''));
        $hasMemory = $memory !== ''
            && ! str_starts_with(mb_strtolower($memory), 'no rolling memory')
            && mb_strlen($memory) >= 20;

        if ($messageCount < 2 && $inboundCount < 1) {
            return $this->fail('Too little conversation history to reply safely.', $messageCount, $inboundCount, $lessons, $hasMemory);
        }

        $asksSomething = $this->looksLikeQuestionOrClaim($inboundBody);

        if ($asksSomething && $lessons->isEmpty() && ! $hasMemory) {
            return $this->fail(
                'No solid lesson/fact/session knowledge for this ask — need operator input before auto-reply.',
                $messageCount,
                $inboundCount,
                $lessons,
                $hasMemory,
            );
        }

        if ($asksSomething && $lessons->isEmpty() && $hasMemory && $messageCount < 3) {
            return $this->fail(
                'Conversation is thin and knowledge is empty for this ask.',
                $messageCount,
                $inboundCount,
                $lessons,
                $hasMemory,
            );
        }

        return [
            'ok' => true,
            'reason' => 'Grounded',
            'message_count' => $messageCount,
            'inbound_count' => $inboundCount,
            'lessons' => $lessons,
            'has_memory' => $hasMemory,
        ];
    }

    /**
     * @param  Collection<int, KnowledgeArticle>  $lessons
     * @return array{ok: bool, reason: string, message_count: int, inbound_count: int, lessons: Collection<int, KnowledgeArticle>, has_memory: bool}
     */
    private function fail(string $reason, int $messageCount, int $inboundCount, Collection $lessons, bool $hasMemory): array
    {
        return [
            'ok' => false,
            'reason' => $reason,
            'message_count' => $messageCount,
            'inbound_count' => $inboundCount,
            'lessons' => $lessons,
            'has_memory' => $hasMemory,
        ];
    }

    private function looksLikeQuestionOrClaim(string $text): bool
    {
        $text = trim($text);

        if ($text === '') {
            return false;
        }

        $lower = mb_strtolower($text);

        if (str_contains($text, '?')) {
            return true;
        }

        return preg_match(
            '/\b(how|what|when|where|why|which|who|price|cost|pricing|discount|quote|demo|train|session|install|support|warranty|integrate|api|can you|do you|is there)\b/u',
            $lower,
        ) === 1;
    }
}
