<?php

namespace App\Http\Controllers;

use App\FollowUps\AutomationRule;
use App\FollowUps\FollowUpAction;
use App\FollowUps\FollowUpTask;
use App\FollowUps\FollowUpTaskStatus;
use App\FollowUps\Services\CancelFollowUpsOnReply;
use App\Http\Requests\AutomationRuleRequest;
use App\Leads\Actions\RecordActivity;
use App\Leads\ActivityEvent;
use App\Models\User;
use App\Settings\DisplayClock;
use App\Settings\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FollowUpController extends Controller
{
    public function index(Request $request, DisplayClock $clock): Response
    {
        $this->authorize('viewAny', AutomationRule::class);
        $user = $request->user();

        return Inertia::render('follow-ups/index', [
            'can_manage' => $user instanceof User && $user->isOwner(),
            'products' => Product::query()
                ->where('is_active', true)
                ->whereNull('archived_at')
                ->orderBy('name')
                ->get(['id', 'name']),
            'rules' => AutomationRule::query()
                ->with('product:id,name')
                ->orderByDesc('id')
                ->get()
                ->map(fn (AutomationRule $rule): array => [
                    'id' => $rule->id,
                    'name' => $rule->name,
                    'trigger' => $rule->trigger->value,
                    'action' => $rule->action->value,
                    'delay_hours' => $rule->delay_hours,
                    'local_time' => $rule->local_time,
                    'body' => $rule->body,
                    'product_id' => $rule->product_id,
                    'product_name' => $rule->product?->name,
                    'is_active' => $rule->is_active,
                ]),
            'tasks' => FollowUpTask::query()
                ->with(['rule:id,name', 'lead.contact:id,full_name'])
                ->orderByDesc('id')
                ->limit(40)
                ->get()
                ->map(fn (FollowUpTask $task): array => [
                    'id' => $task->id,
                    'lead_id' => $task->lead_id,
                    'contact_name' => $task->lead->contact->full_name,
                    'rule' => $task->rule?->name,
                    'action' => $task->action->value,
                    'status' => $task->status->value,
                    'due_label' => $clock->formatDateTime($task->due_at),
                    'held_reason' => $task->held_reason,
                    'failure_reason' => $task->failure_reason,
                ]),
        ]);
    }

    public function store(AutomationRuleRequest $request): RedirectResponse
    {
        AutomationRule::query()->create($this->attributes($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Follow-up rule saved.']);

        return redirect()->route('follow-ups.index');
    }

    public function update(AutomationRuleRequest $request, AutomationRule $rule): RedirectResponse
    {
        $rule->fill($this->attributes($request));
        $rule->save();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Follow-up rule updated.']);

        return redirect()->route('follow-ups.index');
    }

    public function cancel(Request $request, FollowUpTask $task, CancelFollowUpsOnReply $cancellations): RedirectResponse
    {
        $task->load('lead');
        $this->authorize('update', $task->lead);
        /** @var User $actor */
        $actor = $request->user();
        $cancellations->cancel($task, 'Cancelled from the desk.', $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Follow-up cancelled.']);

        return back();
    }

    public function complete(Request $request, FollowUpTask $task, RecordActivity $activity): RedirectResponse
    {
        $task->load('lead.contact');
        $this->authorize('update', $task->lead);

        if ($task->status !== FollowUpTaskStatus::Pending || $task->action !== FollowUpAction::OperatorTask) {
            throw ValidationException::withMessages([
                'task' => 'Only a pending desk task can be marked done.',
            ]);
        }

        /** @var User $actor */
        $actor = $request->user();
        $task->status = FollowUpTaskStatus::Done;
        $task->executed_at = now();
        $task->held_reason = null;
        $task->save();
        $activity->handle(ActivityEvent::FollowUpCompleted, $task->lead, $task->lead->contact, $actor, [
            'task_id' => $task->id,
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Follow-up marked done.']);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(AutomationRuleRequest $request): array
    {
        $productId = $request->input('product_id');

        return [
            'name' => $request->string('name')->toString(),
            'trigger' => $request->string('trigger')->toString(),
            'action' => $request->string('action')->toString(),
            'delay_hours' => $request->input('delay_hours'),
            'local_time' => $request->input('local_time'),
            'body' => $request->string('body')->toString(),
            'product_id' => is_numeric($productId) ? (int) $productId : null,
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
