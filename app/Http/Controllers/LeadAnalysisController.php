<?php

namespace App\Http\Controllers;

use App\Ai\LeadAnalysis;
use App\Ai\ReplyDraft;
use App\Ai\Services\AnalyzeLead;
use App\Ai\Services\ApplyLeadExtraction;
use App\Ai\Services\ApplySuggestedStage;
use App\Ai\Services\ApproveReplyDraft;
use App\Http\Requests\ApplyExtractionRequest;
use App\Http\Requests\ReplyDraftRequest;
use App\Leads\Lead;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LeadAnalysisController extends Controller
{
    public function store(Request $request, Lead $lead, AnalyzeLead $analyzer): RedirectResponse
    {
        $this->authorize('update', $lead);
        /** @var User $actor */
        $actor = $request->user();
        $analyzer->request($lead, $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Analysis recorded.']);

        return redirect()->route('leads.show', $lead);
    }

    public function apply(ApplyExtractionRequest $request, Lead $lead, LeadAnalysis $analysis, ApplyLeadExtraction $apply): RedirectResponse
    {
        $this->ownedAnalysis($lead, $analysis);
        /** @var User $actor */
        $actor = $request->user();
        /** @var list<string> $fields */
        $fields = $request->validated('fields');
        $apply->apply($analysis, $actor, $fields);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Selected fields applied.']);

        return redirect()->route('leads.show', $lead);
    }

    public function stage(Request $request, Lead $lead, LeadAnalysis $analysis, ApplySuggestedStage $apply): RedirectResponse
    {
        $this->authorize('update', $lead);
        $this->ownedAnalysis($lead, $analysis);
        /** @var User $actor */
        $actor = $request->user();
        $apply->apply($analysis, $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Suggested stage applied.']);

        return redirect()->route('leads.show', $lead);
    }

    public function draft(ReplyDraftRequest $request, Lead $lead, ReplyDraft $draft, ApproveReplyDraft $drafts): RedirectResponse
    {
        $this->ownedDraft($lead, $draft);
        /** @var User $actor */
        $actor = $request->user();
        $body = $request->string('body')->toString();

        if ($request->string('intent')->toString() === 'send') {
            $drafts->send($draft, $body, $actor);
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Reply handed to WhatsApp.']);
        } else {
            $drafts->save($draft, $body, $actor);
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Draft saved.']);
        }

        return redirect()->route('leads.show', $lead);
    }

    private function ownedAnalysis(Lead $lead, LeadAnalysis $analysis): void
    {
        abort_unless($analysis->lead_id === $lead->id, 404);
    }

    private function ownedDraft(Lead $lead, ReplyDraft $draft): void
    {
        abort_unless($draft->lead_id === $lead->id, 404);
    }
}
