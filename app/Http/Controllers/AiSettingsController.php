<?php

namespace App\Http\Controllers;

use App\Ai\KnowledgeArticle;
use App\Ai\LlmConfiguration;
use App\Http\Requests\KnowledgeArticleRequest;
use App\Models\User;
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
        ]);
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
