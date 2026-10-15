<?php

namespace App\Http\Controllers;

use App\Contacts\Contact;
use App\Conversations\Actions\LogMessage;
use App\Conversations\MessageDirection;
use App\Calendar\CalendarActivity;
use App\Http\Requests\FollowUpRequest;
use App\Http\Requests\LeadMessageRequest;
use App\Http\Requests\LeadNoteRequest;
use App\Http\Requests\LeadRequest;
use App\Http\Requests\LeadStageRequest;
use App\Http\Requests\MarkDateRequest;
use App\Leads\Actions\AddLeadNote;
use App\Leads\Actions\CreateLead;
use App\Leads\Actions\SetFollowUp;
use App\Leads\DecisionMakerStatus;
use App\Leads\Lead;
use App\Leads\LeadPageData;
use App\Leads\Services\LeadIndexQuery;
use App\Leads\Services\SalesStageTransitionService;
use App\Models\User;
use App\SalesPipelines\Pipeline;
use App\SalesPipelines\PipelineStage;
use App\Settings\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function index(Request $request, LeadIndexQuery $query): Response
    {
        $this->authorize('viewAny', Lead::class);

        return Inertia::render('leads/index', [
            'leads' => $query->paginate($request),
            'filters' => [
                'q' => $request->string('q')->toString(),
                'product_id' => $request->string('product_id')->toString(),
                'stage_id' => $request->string('stage_id')->toString(),
                'source' => $request->string('source')->toString(),
                'follow_up' => $request->string('follow_up')->toString(),
                'kind' => $request->string('kind')->toString(),
                'created' => $request->string('created')->toString(),
            ],
            'products' => Product::query()->orderBy('name')->get(['id', 'name']),
            'stages' => PipelineStage::query()->with('pipeline:id,name')->orderBy('position')->get()->map(fn (PipelineStage $stage): array => [
                'id' => $stage->id,
                'name' => $stage->pipeline->name.' · '.$stage->name,
            ]),
            'sources' => Lead::query()->whereNotNull('source')->distinct()->orderBy('source')->pluck('source'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Lead::class);

        return Inertia::render('leads/create', [
            'selected_contact_id' => $request->integer('contact') ?: null,
            'contacts' => Contact::query()
                ->whereNull('merged_into_contact_id')
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'business_name', 'primary_phone']),
            'products' => Product::query()->where('is_active', true)->whereNull('archived_at')->orderBy('name')->get(['id', 'name']),
            'pipelines' => Pipeline::query()
                ->whereNull('archived_at')
                ->with(['stages' => fn ($query) => $query->whereNull('archived_at')->orderBy('position')])
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get()
                ->map(fn (Pipeline $pipeline): array => [
                    'id' => $pipeline->id,
                    'name' => $pipeline->name,
                    'is_default' => $pipeline->is_default,
                    'stages' => $pipeline->stages->map(fn (PipelineStage $stage): array => [
                        'id' => $stage->id,
                        'name' => $stage->name,
                        'kind' => $stage->kind->value,
                    ])->all(),
                ]),
            'decision_maker_options' => collect(DecisionMakerStatus::cases())->map(fn (DecisionMakerStatus $status): array => [
                'value' => $status->value,
                'label' => ucfirst($status->value),
            ])->all(),
        ]);
    }

    public function store(LeadRequest $request, CreateLead $action): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $lead = $action->handle($request->validated(), $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lead opened.']);

        return redirect()->route('leads.show', $lead);
    }

    public function show(Lead $lead, LeadPageData $page): Response
    {
        $this->authorize('view', $lead);

        return Inertia::render('leads/show', [
            'lead' => $page->detail($lead),
            'decision_maker_options' => collect(DecisionMakerStatus::cases())->map(fn (DecisionMakerStatus $status): array => [
                'value' => $status->value,
                'label' => ucfirst($status->value),
            ])->all(),
        ]);
    }

    public function update(LeadRequest $request, Lead $lead): RedirectResponse
    {
        $data = $request->safe()->only([
            'source',
            'business_problem',
            'current_solution',
            'branch_count',
            'budget_note',
            'decision_maker_status',
            'purchase_timeline_note',
        ]);

        $lead->fill($data);
        $lead->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lead details saved.']);

        return redirect()->route('leads.show', $lead);
    }

    public function stage(LeadStageRequest $request, Lead $lead, SalesStageTransitionService $transitions): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $stage = PipelineStage::query()->findOrFail($request->integer('stage_id'));
        $reason = $request->string('lost_reason')->toString();

        if ($reason === '') {
            $reason = $request->string('reason')->toString();
        }

        $transitions->transition($lead, $stage, $actor, $reason === '' ? null : $reason);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Stage updated.']);

        return redirect()->route('leads.show', $lead);
    }

    public function note(LeadNoteRequest $request, Lead $lead, AddLeadNote $action): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $action->handle($lead, $request->string('body')->toString(), $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Note added.']);

        return redirect()->route('leads.show', $lead);
    }

    public function message(LeadMessageRequest $request, Lead $lead, LogMessage $action): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $sentAt = $request->input('sent_at');

        $action->handle(
            $lead,
            MessageDirection::from($request->string('direction')->toString()),
            $request->string('body')->toString(),
            is_string($sentAt) ? $sentAt : null,
            $actor,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Message logged.']);

        return redirect()->route('leads.show', $lead);
    }

    public function followUp(FollowUpRequest $request, Lead $lead, SetFollowUp $action): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $date = $request->input('next_follow_up_on');
        $nextAction = $request->input('next_action');
        $action->handle(
            $lead,
            is_string($nextAction) ? $nextAction : null,
            is_string($date) ? $date : null,
            $actor,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Follow-up saved.']);

        return redirect()->route('leads.show', $lead);
    }

    public function markDate(MarkDateRequest $request, Lead $lead): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $date = $request->string('marked_on')->toString();
        $label = $request->string('marked_label')->toString();

        $lead->marked_on = $date;
        $lead->marked_label = $label === '' ? null : $label;
        $lead->save();

        if ($request->boolean('add_to_calendar')) {
            CalendarActivity::query()->create([
                'user_id' => $actor->id,
                'activity_on' => $date,
                'title' => ($label !== '' ? $label.' · ' : '').$lead->contact->full_name,
                'notes' => 'Marked from lead #'.$lead->id.' · '.$lead->product->name,
                'activity_type' => 'task',
                'color' => 'blue',
                'lead_id' => $lead->id,
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Date marked on the lead.']);

        return redirect()->route('leads.show', $lead);
    }
}
