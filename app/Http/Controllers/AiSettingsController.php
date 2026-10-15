<?php

namespace App\Http\Controllers;

use App\Ai\AutoReplyRule;
use App\Ai\KnowledgeArticle;
use App\Ai\LlmConfiguration;
use App\Http\Requests\AutoReplyRuleRequest;
use App\Http\Requests\KnowledgeArticleRequest;
use App\Models\User;
use App\SalesPipelines\PipelineStage;
use App\Settings\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiSettingsController extends Controller
{
    public function index(Request $request, LlmConfiguration $configuration): Response
    {
        $this->authorize('viewAny', KnowledgeArticle::class);
        $user = $request->user();

        return Inertia::render('ai/index', [
            'variables' => $configuration->variables(),
            'configured' => $configuration->ready(),
            'can_manage' => $user instanceof User && $user->isOwner(),
            'products' => Product::query()
                ->where('is_active', true)
                ->whereNull('archived_at')
                ->orderBy('name')
                ->get(['id', 'name']),
            'articles' => KnowledgeArticle::query()
                ->with('product:id,name')
                ->orderByDesc('id')
                ->get()
                ->map(fn (KnowledgeArticle $article): array => [
                    'id' => $article->id,
                    'title' => $article->title,
                    'body' => $article->body,
                    'product_id' => $article->product_id,
                    'product_name' => $article->product?->name,
                    'is_active' => $article->is_active,
                ]),
            'stages' => PipelineStage::query()
                ->with('pipeline:id,name')
                ->orderBy('position')
                ->get()
                ->map(fn (PipelineStage $stage): array => [
                    'id' => $stage->id,
                    'name' => $stage->pipeline->name.' · '.$stage->name,
                ]),
            'auto_reply_rules' => AutoReplyRule::query()
                ->with('stage:id,name')
                ->orderBy('priority')
                ->orderBy('id')
                ->get()
                ->map(fn (AutoReplyRule $rule): array => [
                    'id' => $rule->id,
                    'name' => $rule->name,
                    'is_active' => $rule->is_active,
                    'priority' => $rule->priority,
                    'match_type' => $rule->match_type,
                    'match_value' => $rule->match_value,
                    'pipeline_stage_id' => $rule->pipeline_stage_id,
                    'stage_name' => $rule->stage?->name,
                    'reply_mode' => $rule->reply_mode,
                    'template_body' => $rule->template_body,
                    'require_open_window' => $rule->require_open_window,
                ]),
        ]);
    }

    public function storeRule(AutoReplyRuleRequest $request): RedirectResponse
    {
        AutoReplyRule::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Auto-reply rule saved.']);

        return redirect()->route('ai.settings');
    }

    public function updateRule(AutoReplyRuleRequest $request, AutoReplyRule $rule): RedirectResponse
    {
        $rule->fill($request->validated());
        $rule->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Auto-reply rule updated.']);

        return redirect()->route('ai.settings');
    }

    public function destroyRule(Request $request, AutoReplyRule $rule): RedirectResponse
    {
        abort_unless($request->user()?->isOwner() === true, 403);
        $rule->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Auto-reply rule removed.']);

        return redirect()->route('ai.settings');
    }

    public function store(KnowledgeArticleRequest $request): RedirectResponse
    {
        KnowledgeArticle::query()->create([
            'title' => $request->string('title')->toString(),
            'body' => $request->string('body')->toString(),
            'product_id' => $request->integer('product_id') ?: null,
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Knowledge article saved.']);

        return redirect()->route('ai.settings');
    }

    public function update(KnowledgeArticleRequest $request, KnowledgeArticle $article): RedirectResponse
    {
        $article->fill([
            'title' => $request->string('title')->toString(),
            'body' => $request->string('body')->toString(),
            'product_id' => $request->integer('product_id') ?: null,
            'is_active' => $request->boolean('is_active'),
        ]);
        $article->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Knowledge article updated.']);

        return redirect()->route('ai.settings');
    }
}
