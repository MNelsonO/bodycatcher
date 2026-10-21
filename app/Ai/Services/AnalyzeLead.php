<?php

namespace App\Ai\Services;

use App\Ai\AnalysisDispatcher;
use App\Ai\AnalysisStatus;
use App\Ai\DraftStatus;
use App\Ai\KnowledgeArticle;
use App\Ai\LeadAnalysis;
use App\Ai\LlmClient;
use App\Ai\LlmConfiguration;
use App\Ai\ReplyDraft;
use App\Conversations\Message;
use App\Leads\Actions\RecordActivity;
use App\Leads\ActivityEvent;
use App\Leads\Lead;
use App\Models\User;
use App\SalesPipelines\PipelineStage;
use App\Settings\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class AnalyzeLead
{
    public function __construct(
        private LlmConfiguration $configuration,
        private LlmClient $client,
        private AnalysisParser $parser,
        private KnowledgeRetriever $knowledge,
        private AnalysisDispatcher $dispatcher,
        private RecordActivity $activity,
    ) {}

    public function request(Lead $lead, User $actor): LeadAnalysis
    {
        $queued = LeadAnalysis::query()
            ->where('lead_id', $lead->id)
            ->where('status', AnalysisStatus::Queued)
            ->first();

        if ($queued !== null) {
            return $queued;
        }

        if (! $this->configuration->ready()) {
            $analysis = LeadAnalysis::query()->create([
                'lead_id' => $lead->id,
                'requested_by_user_id' => $actor->id,
                'status' => AnalysisStatus::Unavailable,
                'error' => 'The language model is not configured.',
            ]);
            $this->failedActivity($analysis, $actor);

            return $analysis;
        }

        $analysis = LeadAnalysis::query()->create([
            'lead_id' => $lead->id,
            'requested_by_user_id' => $actor->id,
            'status' => AnalysisStatus::Queued,
        ]);

        try {
            $this->dispatcher->dispatch($analysis->id);
        } catch (Throwable) {
            // The row stays queued so `php artisan ai:retry` can run it.
        }

        return $analysis->refresh();
    }

    public function run(int $analysisId): void
    {
        $analysis = LeadAnalysis::query()->find($analysisId);

        if ($analysis === null || $analysis->status !== AnalysisStatus::Queued) {
            return;
        }

        try {
            $this->complete($analysis);
        } catch (Throwable $exception) {
            $this->fail($analysis, $exception->getMessage());
        }
    }

    private function complete(LeadAnalysis $analysis): void
    {
        $lead = Lead::query()->findOrFail($analysis->lead_id);
        $lead->load(['contact.notes', 'interactions', 'product', 'currentStage.outgoingTransitions.toStage', 'conversation.messages']);
        $articles = $this->knowledge->forLead($lead);
        $permitted = $this->permittedStages($lead);
        $products = $this->activeProducts();
        $raw = $this->client->complete(
            $this->systemPrompt(),
            $this->userPrompt($lead, $articles, $permitted, $products),
        );
        $parsed = $this->parser->parse($raw, $this->stageIds($permitted), $this->productIds($products));

        DB::transaction(function () use ($analysis, $lead, $articles, $parsed): void {
            $locked = LeadAnalysis::query()->lockForUpdate()->find($analysis->id);

            if ($locked === null || $locked->status !== AnalysisStatus::Queued) {
                return;
            }

            $locked->fill([
                'status' => AnalysisStatus::Completed,
                'summary' => $parsed->summary,
                'extraction' => $parsed->extraction,
                'score' => $parsed->score,
                'score_reasons' => $parsed->scoreReasons,
                'suggested_stage_id' => $parsed->suggestedStageId,
                'stage_reason' => $parsed->stageReason,
                'stage_rejected_reason' => $parsed->stageRejectedReason,
                'knowledge_article_ids' => $articles->map(fn (KnowledgeArticle $article): int => $article->id)->values()->all(),
                'error' => null,
                'completed_at' => now(),
            ]);
            $locked->save();

            if ($parsed->replyDraft !== null) {
                ReplyDraft::query()->create([
                    'lead_id' => $lead->id,
                    'lead_analysis_id' => $locked->id,
                    'original_body' => $parsed->replyDraft,
                    'body' => $parsed->replyDraft,
                    'status' => DraftStatus::Pending,
                ]);
            }

            $this->activity->handle(ActivityEvent::AnalysisCompleted, $lead, $lead->contact, $locked->requester, [
                'analysis_id' => $locked->id,
                'score' => $locked->score,
            ]);
        });
    }

    private function fail(LeadAnalysis $analysis, string $message): void
    {
        DB::transaction(function () use ($analysis, $message): void {
            $locked = LeadAnalysis::query()->lockForUpdate()->find($analysis->id);

            if ($locked === null || $locked->status !== AnalysisStatus::Queued) {
                return;
            }

            $locked->status = AnalysisStatus::Failed;
            $locked->error = mb_substr(trim($message) === '' ? 'The analysis failed.' : trim($message), 0, 500);
            $locked->save();
            $locked->load('lead.contact', 'requester');
            $this->failedActivity($locked, $locked->requester);
        });
    }

    private function failedActivity(LeadAnalysis $analysis, ?User $actor): void
    {
        $analysis->loadMissing('lead.contact');

        $this->activity->handle(ActivityEvent::AnalysisFailed, $analysis->lead, $analysis->lead->contact, $actor, [
            'analysis_id' => $analysis->id,
            'reason' => $analysis->error,
        ]);
    }

    /**
     * @return Collection<int, PipelineStage>
     */
    private function permittedStages(Lead $lead): Collection
    {
        /** @var Collection<int, PipelineStage> $stages */
        $stages = $lead->currentStage->outgoingTransitions
            ->map(fn ($transition) => $transition->toStage)
            ->filter(fn ($stage): bool => $stage instanceof PipelineStage && $stage->archived_at === null)
            ->values();

        return $stages;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You help a sales desk read one lead. Reply with one JSON object and no other text.
Keys:
summary: string, a short brief of the conversation.
extraction: object with only facts the contact stated. Allowed keys are business_problem, current_solution, branch_count, budget_note, decision_maker_status (unknown, yes, or no), purchase_timeline_note, next_action, next_follow_up_on (YYYY-MM-DD), suggested_product_id (active product id from the list), product_reason (why that product fits). Omit keys you cannot support from the conversation.
score: integer from 0 to 100 for how ready this lead is to buy.
score_reasons: array of short strings that explain the score.
suggested_stage_id: one permitted stage id, or null.
stage_reason: why that stage fits, or null.
suggested_product_id: optional active product id when a different catalog product fits better than the assigned one, or null.
product_reason: why that product fits, or null.
reply_draft: a short WhatsApp reply in plain language, or null if a reply would be wrong.
Do not invent prices, dates, branch counts, or stages that were not provided.
PROMPT;
    }

    /**
     * @param  Collection<int, KnowledgeArticle>  $articles
     * @param  Collection<int, PipelineStage>  $stages
     * @param  Collection<int, Product>  $products
     */
    private function userPrompt(Lead $lead, Collection $articles, Collection $stages, Collection $products): string
    {
        $stageLines = $stages->map(fn (PipelineStage $stage): string => $stage->id.' '.$stage->name.' ('.$stage->kind->value.')')->implode("\n");
        $productLines = $products->map(fn (Product $product): string => $product->id.' '.$product->name.($product->slug ? ' ('.$product->slug.')' : ''))->implode("\n");
        $knowledge = $articles->map(function (KnowledgeArticle $article): string {
            return 'Article '.$article->id.' — '.$article->title."\n".mb_substr($article->body, 0, 1500);
        })->implode("\n\n");

        $messages = $lead->conversation?->messages;
        $transcript = $messages
            ?->sortBy('id')
            ->take(-30)
            ->map(fn (Message $message): string => $message->direction->value.': '.mb_substr($message->body, 0, 1000))
            ->implode("\n");

        $clientContext = $lead->contact->aiContextBlock();
        $touches = $lead->interactions
            ->sortByDesc('occurred_at')
            ->take(10)
            ->map(function ($row): string {
                return $row->type->label().' @ '.($row->occurred_at?->toDateTimeString() ?? '?').': '.mb_substr(trim($row->body), 0, 500);
            })
            ->implode("\n");

        return implode("\n\n", array_values(array_filter([
            'Contact: '.$lead->contact->full_name,
            'Phone: '.$lead->contact->primary_phone,
            'Business: '.($lead->contact->business_name ?? 'unknown'),
            $clientContext !== '' ? $clientContext : null,
            $touches !== '' ? "Desk touches:\n".$touches : null,
            'Next conversation: '.($lead->next_conversation_at?->toDateTimeString() ?? 'none'),
            'Conversations stopped: '.($lead->conversations_stopped ? 'yes' : 'no'),
            'Assigned product: '.$lead->product->name.' (id '.$lead->product->id.')',
            'Product notes: '.($lead->product->description ?? 'none'),
            "Active products (use id for suggested_product_id):\n".($productLines !== '' ? $productLines : 'none'),
            'Current stage: '.$lead->currentStage->name,
            "Permitted next stages:\n".($stageLines !== '' ? $stageLines : 'none'),
            'Known problem: '.($lead->business_problem ?? 'none'),
            'Known solution: '.($lead->current_solution ?? 'none'),
            'Branches: '.($lead->branch_count === null ? 'unknown' : (string) $lead->branch_count),
            'Budget note: '.($lead->budget_note ?? 'none'),
            'Decision maker: '.$lead->decision_maker_status->value,
            'Timeline note: '.($lead->purchase_timeline_note ?? 'none'),
            "Knowledge:\n".($knowledge !== '' ? $knowledge : 'none'),
            "Conversation:\n".($transcript !== null && $transcript !== '' ? $transcript : 'none'),
        ], fn ($line): bool => $line !== null)));
    }

    /**
     * @return Collection<int, Product>
     */
    private function activeProducts(): Collection
    {
        return Product::query()
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

    /**
     * @param  Collection<int, PipelineStage>  $stages
     * @return list<int>
     */
    private function stageIds(Collection $stages): array
    {
        $ids = [];

        foreach ($stages as $stage) {
            $ids[] = $stage->id;
        }

        return $ids;
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return list<int>
     */
    private function productIds(Collection $products): array
    {
        $ids = [];

        foreach ($products as $product) {
            $ids[] = $product->id;
        }

        return $ids;
    }
}
