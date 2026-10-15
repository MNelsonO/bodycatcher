<?php

namespace App\Ai\Services;

use App\Ai\KnowledgeArticle;
use App\Conversations\Message;
use App\Leads\Lead;
use Illuminate\Support\Collection;

class KnowledgeRetriever
{
    /**
     * @return Collection<int, KnowledgeArticle>
     */
    public function forLead(Lead $lead, int $limit = 5): Collection
    {
        $lead->loadMissing(['product', 'conversation.messages']);
        $tokens = $this->tokens($this->queryText($lead));

        /** @var Collection<int, KnowledgeArticle> $articles */
        $articles = KnowledgeArticle::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        return $articles
            ->map(function (KnowledgeArticle $article) use ($lead, $tokens): array {
                $overlap = 0;
                $haystack = ' '.$this->normalize($article->title.' '.$article->body).' ';

                foreach ($tokens as $token) {
                    if (str_contains($haystack, ' '.$token.' ')) {
                        $overlap++;
                    }
                }

                $score = $overlap;

                if ($article->product_id !== null && $article->product_id === $lead->product_id) {
                    $score += 3;
                }

                return ['article' => $article, 'score' => $score];
            })
            ->filter(fn (array $row): bool => $row['score'] > 0)
            ->sort(function (array $left, array $right): int {
                $byScore = $right['score'] <=> $left['score'];

                return $byScore !== 0 ? $byScore : $left['article']->id <=> $right['article']->id;
            })
            ->take($limit)
            ->map(fn (array $row): KnowledgeArticle => $row['article'])
            ->values();
    }

    private function queryText(Lead $lead): string
    {
        $parts = [
            $lead->product->name,
            $lead->business_problem,
            $lead->current_solution,
            $lead->purchase_timeline_note,
        ];

        $conversation = $lead->conversation;
        $messages = $conversation === null ? collect() : $conversation->messages;

        foreach ($messages->sortBy('id')->take(-20) as $message) {
            if ($message instanceof Message) {
                $parts[] = $message->body;
            }
        }

        return implode(' ', array_filter($parts, fn (?string $part): bool => is_string($part) && $part !== ''));
    }

    /**
     * @return list<string>
     */
    private function tokens(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]{4,}/u', $this->normalize($text), $matches);
        $stop = [
            'this', 'that', 'with', 'from', 'have', 'your', 'they', 'them', 'were', 'been',
            'will', 'what', 'when', 'where', 'which', 'their', 'about', 'would', 'could',
            'there', 'into', 'than', 'then', 'some', 'more',
        ];

        /** @var list<string> $words */
        $words = $matches[0];

        return array_values(array_unique(array_diff($words, $stop)));
    }

    private function normalize(string $text): string
    {
        return mb_strtolower($text);
    }
}
