<?php

namespace App\Http\Controllers;

use App\Ai\LlmConfiguration;
use App\Leads\Lead;
use App\Models\User;
use App\WhatsApp\Labels\LabelCampaign;
use App\WhatsApp\Labels\WhatsAppLabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppLabelController extends Controller
{
    public function index(Request $request, LabelCampaign $campaign, LlmConfiguration $llm): Response
    {
        $this->authorize('viewAny', Lead::class);
        $selected = $request->string('label')->toString();
        $label = $selected !== '' ? WhatsAppLabel::query()->where('label_id', $selected)->first() : null;

        return Inertia::render('labels/index', [
            'labels' => $campaign->labels(),
            'selected' => $label === null ? null : [
                'label_id' => $label->label_id,
                'name' => $label->name,
                'color' => $label->hex(),
                'members' => $campaign->members($label->label_id)->all(),
            ],
            'ai_ready' => $llm->ready(),
            'limits' => [
                'per_day' => (int) config('whatsapp.safety.campaign_per_day', 30),
                'gap_seconds' => (int) config('whatsapp.safety.campaign_gap_seconds', 150),
                'quiet_hours' => (int) config('whatsapp.safety.campaign_quiet_hours', 12),
            ],
        ]);
    }

    public function drafts(Request $request, string $labelId, LabelCampaign $campaign): JsonResponse
    {
        $this->authorize('create', Lead::class);

        $data = $request->validate([
            'goal' => ['nullable', 'string', 'max:500'],
            'template' => ['nullable', 'string', 'max:600'],
        ]);

        if (trim((string) ($data['goal'] ?? '')) === '' && trim((string) ($data['template'] ?? '')) === '') {
            return response()->json(['message' => 'Write a goal for the AI or a message template.'], 422);
        }

        return response()->json([
            'drafts' => $campaign->drafts($labelId, (string) ($data['goal'] ?? ''), (string) ($data['template'] ?? '')),
        ]);
    }

    public function send(Request $request, string $labelId, LabelCampaign $campaign): RedirectResponse
    {
        $this->authorize('create', Lead::class);

        $data = $request->validate([
            'messages' => ['required', 'array', 'min:1', 'max:60'],
            'messages.*.lead_id' => ['required', 'integer', 'exists:leads,id'],
            'messages.*.body' => ['required', 'string', 'max:600'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $result = $campaign->schedule(array_map(fn ($m) => ['lead_id' => (int) $m['lead_id'], 'body' => (string) $m['body']], $data['messages']), $actor);

        Inertia::flash('toast', [
            'type' => $result['scheduled'] > 0 ? 'success' : 'warning',
            'message' => $result['scheduled'] > 0
                ? "Scheduled {$result['scheduled']} messages, spaced out from {$result['first_at']} to {$result['last_at']}. A reply from someone cancels their message."
                : 'Nothing was scheduled; those chats are not safe to message right now.',
        ]);

        return redirect()->route('labels.index', ['label' => $labelId]);
    }
}
