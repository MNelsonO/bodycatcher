<?php

namespace App\Ai\Services;

use App\Ai\KnowledgeGap;
use App\Leads\Lead;
use Illuminate\Support\Str;

/**
 * When inbound chat asks something the desk knowledge cannot cover,
 * raise it so an operator can feed the fact / lesson / physical session.
 */
class RaiseUnfamiliarQuestion
{
    public function __construct(private KnowledgeRetriever $knowledge) {}

    public function handle(Lead $lead, string $inboundBody, string $source = 'inbound'): ?KnowledgeGap
    {
        $question = trim(preg_replace('/\s+/u', ' ', $inboundBody) ?? '');

        if ($question === '' || mb_strlen($question) < 8 || mb_strlen($question) > 500) {
            return null;
        }

        if (! $this->looksLikeQuestion($question)) {
            return null;
        }

        $lead->loadMissing(['contact', 'product', 'currentStage']);

        if ($this->knowledge->covers($lead, $question)) {
            return null;
        }

        $fingerprint = mb_strtolower(Str::limit($question, 180, ''));

        $existing = KnowledgeGap::query()
            ->where('status', 'open')
            ->where('lead_id', $lead->id)
            ->whereRaw('lower(question) = ?', [$fingerprint])
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $recentDup = KnowledgeGap::query()
            ->where('status', 'open')
            ->whereRaw('lower(question) = ?', [$fingerprint])
            ->where('created_at', '>=', now()->subDays(7))
            ->exists();

        if ($recentDup) {
            return null;
        }

        return KnowledgeGap::query()->create([
            'lead_id' => $lead->id,
            'contact_id' => $lead->contact_id,
            'product_id' => $lead->product_id,
            'status' => 'open',
            'source' => $source,
            'question' => mb_substr($question, 0, 500),
            'context_snippet' => mb_substr($question, 0, 500),
            'contact_name' => $lead->contact->full_name,
            'product_name' => $lead->product->name,
            'stage_name' => $lead->currentStage->name,
            'lead_status' => $lead->outcome(),
        ]);
    }

    public function fromEscalation(Lead $lead, string $reason, ?string $snippet): ?KnowledgeGap
    {
        $text = trim(($snippet ?: '').' '.$reason);

        if ($text === '') {
            return null;
        }

        return $this->handle($lead, $text, 'escalation');
    }

    private function looksLikeQuestion(string $text): bool
    {
        $lower = mb_strtolower($text);

        if (str_contains($text, '?')) {
            return true;
        }

        return preg_match(
            '/\b(how|what|when|where|why|which|who|can you|do you|does|is there|are there|how much|how many|price|cost|pricing|support|install|demo|session|training|warranty|integrate|api)\b/u',
            $lower,
        ) === 1;
    }
}
