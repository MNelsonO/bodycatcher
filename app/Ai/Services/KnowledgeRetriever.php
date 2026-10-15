<?php

namespace App\Ai\Services;

use App\Ai\KnowledgeArticle;
use App\Conversations\Message;
use App\Leads\Lead;
use Illuminate\Support\Collection;

class KnowledgeRetriever
{
    /**
     * True when active knowledge already covers this question well enough.
     */
    public function covers(Lead $lead, string $question, int $minScore = 2): bool
    {
        $lead->loadMissing(['product']);
        $tokens = $this->tokens($question);

        if ($tokens === []) {
            return false;
        }

        $best = 0;

        foreach ($this->activeArticles() as $article) {
            if (! $this->isSolidArticle($article)) {
                continue;
            }

            $overlap = $this->tokenOverlap($article, $tokens);

            if ($overlap === 0) {
                continue;
            }

            if ($article->product_id !== null && $article->product_id === $lead->product_id) {
                $overlap += 1;
            }

            $best = max($best, $overlap);
        }

        return $best >= $minScore;
    }

    /**
     * Solid lessons/facts/sessions grounded enough to drive a reply.
     *
     * @return Collection<int, KnowledgeArticle>
     */
    public function solidLessonsFor(Lead $lead, string $focus = '', int $limit = 5): Collection
    {
        $lead->loadMissing(['product', 'conversation.messages']);
        $tokens = $this->tokens(trim($focus.' '.$this->queryText($lead)));

        return $this->rank($lead, $tokens, $limit, solidOnly: true, minScore: 2);
    }

    /**
     * @return Collection<int, KnowledgeArticle>
     */
    public function forLead(Lead $lead, int $limit = 5): Collection
    {
        $lead->loadMissing(['product', 'conversation.messages']);
        $tokens = $this->tokens($this->queryText($lead));

        return $this->rank($lead, $tokens, $limit, solidOnly: true, minScore: 1);
    }

    /**
     * @param  list<string>  $tokens
     * @return Collection<int, KnowledgeArticle>
     */
    private function rank(Lead $lead, array $tokens, int $limit, bool $solidOnly, int $minScore): Collection
    {
        return $this->activeArticles()
            ->map(function (KnowledgeArticle $article) use ($lead, $tokens, $solidOnly): ?array {
                if ($solidOnly && ! $this->isSolidArticle($article)) {
                    return null;
                }

                $score = $this->scoreArticle($article, $lead, $tokens);

                return $score > 0 ? ['article' => $article, 'score' => $score] : null;
            })
            ->filter()
            ->filter(fn (array $row): bool => $row['score'] >= $minScore)
            ->sort(function (array $left, array $right): int {
                $byScore = $right['score'] <=> $left['score'];

                return $byScore !== 0 ? $byScore : $left['article']->id <=> $right['article']->id;
            })
            ->take($limit)
            ->map(fn (array $row): KnowledgeArticle => $row['article'])
            ->values();
    }

    /**
     * @return Collection<int, KnowledgeArticle>
     */
    private function activeArticles(): Collection
    {
        return KnowledgeArticle::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
    }

    public function isSolidArticle(KnowledgeArticle $article): bool
    {
        $body = trim($article->body);
        $lower = $this->normalize($article->title.' '.$body);

        if (mb_strlen($body) < 40) {
            return false;
        }

        foreach ([
            'needs an operator',
            'draft a grounded reply',
            'placeholder',
            'todo:',
            'coming soon',
        ] as $needle) {
            if (str_contains($lower, $needle)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $tokens
     */
    private function tokenOverlap(KnowledgeArticle $article, array $tokens): int
    {
        $overlap = 0;
        $haystack = ' '.$this->normalize($article->title.' '.$article->body).' ';

        foreach ($tokens as $token) {
            if (str_contains($haystack, ' '.$token.' ')) {
                $overlap++;
            }
        }

        return $overlap;
    }

    /**
     * @param  list<string>  $tokens
     */
    private function scoreArticle(KnowledgeArticle $article, Lead $lead, array $tokens): int
    {
        $overlap = $this->tokenOverlap($article, $tokens);

        if ($overlap === 0) {
            return 0;
        }

        $score = $overlap;

        if ($article->product_id !== null && $article->product_id === $lead->product_id) {
            $score += 3;
        }

        $category = (string) ($article->category ?? 'fact');

        $score += match ($category) {
            'session' => 3,
            'lesson' => 2,
            'fact' => 2,
            default => 0,
        };

        if (in_array((string) ($article->source ?? ''), ['operator_fact_feed', 'operator_correction', 'manual'], true)) {
            $score += 2;
        }

        return $score;
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
