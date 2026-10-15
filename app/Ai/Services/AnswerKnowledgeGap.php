<?php

namespace App\Ai\Services;

use App\Ai\KnowledgeArticle;
use App\Ai\KnowledgeGap;
use App\Conversations\Conversation;
use App\Leads\Actions\RecordActivity;
use App\Leads\ActivityEvent;
use App\Leads\Lead;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Operator feeds a fact / lesson / physical-session answer; desk saves it
 * as reusable knowledge and updates lead memory when tied to a chat.
 */
class AnswerKnowledgeGap
{
    public function __construct(private RecordActivity $activity) {}

    public function handle(
        KnowledgeGap $gap,
        User $actor,
        string $answer,
        string $category = 'fact',
        ?string $title = null,
    ): KnowledgeGap {
        if (! $gap->isOpen()) {
            throw ValidationException::withMessages([
                'answer' => 'This question was already answered.',
            ]);
        }

        $answer = trim($answer);
        $category = in_array($category, ['fact', 'lesson', 'session'], true) ? $category : 'fact';

        if ($answer === '') {
            throw ValidationException::withMessages([
                'answer' => 'Write the fact, lesson, or session detail to teach the desk.',
            ]);
        }

        $label = match ($category) {
            'lesson' => 'Lesson',
            'session' => 'Physical session',
            default => 'Fact',
        };

        $articleTitle = $title !== null && trim($title) !== ''
            ? trim($title)
            : $label.': '.mb_substr($gap->question, 0, 100);

        $article = KnowledgeArticle::query()->create([
            'title' => mb_substr($articleTitle, 0, 160),
            'body' => "Q: {$gap->question}\n\nA: {$answer}",
            'category' => $category,
            'product_id' => $gap->product_id,
            'is_active' => true,
            'source' => 'operator_fact_feed',
            'external_id' => 'gap-'.$gap->id,
            'synced_at' => now(),
        ]);

        $gap->status = 'answered';
        $gap->answer = mb_substr($answer, 0, 4000);
        $gap->lesson_category = $category;
        $gap->article_id = $article->id;
        $gap->answered_by_user_id = $actor->id;
        $gap->answered_at = now();
        $gap->save();

        if ($gap->lead_id !== null) {
            $lead = Lead::query()->with(['contact', 'conversation'])->find($gap->lead_id);

            if ($lead !== null) {
                $conversation = $lead->conversation ?? Conversation::query()->create([
                    'lead_id' => $lead->id,
                    'contact_id' => $lead->contact_id,
                    'focused_product_id' => $lead->product_id,
                ]);

                $memory = trim((string) $conversation->memory_summary);
                $line = "{$label} taught: {$gap->question} → {$answer}";
                $conversation->memory_summary = mb_substr(
                    $memory === '' ? $line : $memory."\n".$line,
                    0,
                    2000,
                );
                $conversation->save();

                $this->activity->handle(ActivityEvent::KnowledgeGapAnswered, $lead, $lead->contact, $actor, [
                    'gap_id' => $gap->id,
                    'category' => $category,
                    'article_id' => $article->id,
                ]);
            }
        }

        return $gap->refresh();
    }
}
