<?php

namespace App\Http\Controllers;

use App\Http\Requests\PipelineRequest;
use App\Http\Requests\ReorderStagesRequest;
use App\Http\Requests\StageRequest;
use App\Http\Requests\TransitionMapRequest;
use App\Leads\Lead;
use App\SalesPipelines\Actions\ReorderStages;
use App\SalesPipelines\Actions\SavePipelineTransitions;
use App\SalesPipelines\Pipeline;
use App\SalesPipelines\PipelineStage;
use App\SalesPipelines\StageKind;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PipelineController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Pipeline::class);

        return Inertia::render('pipelines/index', [
            'pipelines' => Pipeline::query()
                ->withCount('stages')
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get()
                ->map(fn (Pipeline $pipeline): array => [
                    'id' => $pipeline->id,
                    'name' => $pipeline->name,
                    'description' => $pipeline->description,
                    'is_default' => $pipeline->is_default,
                    'archived' => $pipeline->archived_at !== null,
                    'stages_count' => $pipeline->stages_count,
                ]),
        ]);
    }

    public function store(PipelineRequest $request): RedirectResponse
    {
        $pipeline = Pipeline::query()->create([
            'name' => $request->string('name')->toString(),
            'description' => $request->input('description'),
            'is_default' => Pipeline::query()->doesntExist(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pipeline created. Add stages next.']);

        return redirect()->route('pipelines.show', $pipeline);
    }

    public function show(Pipeline $pipeline): Response
    {
        $this->authorize('view', $pipeline);

        $pipeline->load(['stages.outgoingTransitions', 'transitions']);

        return Inertia::render('pipelines/show', [
            'pipeline' => [
                'id' => $pipeline->id,
                'name' => $pipeline->name,
                'description' => $pipeline->description,
                'is_default' => $pipeline->is_default,
                'archived' => $pipeline->archived_at !== null,
            ],
            'stages' => $pipeline->stages->map(fn (PipelineStage $stage): array => [
                'id' => $stage->id,
                'name' => $stage->name,
                'purpose' => $stage->purpose,
                'kind' => $stage->kind->value,
                'position' => $stage->position,
                'default_follow_up_delay_hours' => $stage->default_follow_up_delay_hours,
                'archived' => $stage->archived_at !== null,
                'targets' => $stage->outgoingTransitions->pluck('to_stage_id')->all(),
            ]),
            'kinds' => collect(StageKind::cases())->map(fn (StageKind $kind): array => [
                'value' => $kind->value,
                'label' => match ($kind) {
                    StageKind::Active => 'Active',
                    StageKind::Won => 'Won',
                    StageKind::Lost => 'Lost',
                    StageKind::ArchivedNurture => 'Archived nurture',
                },
            ])->all(),
        ]);
    }

    public function update(PipelineRequest $request, Pipeline $pipeline): RedirectResponse
    {
        $pipeline->update([
            'name' => $request->string('name')->toString(),
            'description' => $request->input('description'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pipeline updated.']);

        return redirect()->route('pipelines.show', $pipeline);
    }

    public function destroy(Pipeline $pipeline): RedirectResponse
    {
        $this->authorize('delete', $pipeline);

        if (Lead::query()->where('pipeline_id', $pipeline->id)->exists()) {
            throw ValidationException::withMessages([
                'pipeline' => 'This pipeline still has leads. Archive it instead of deleting it.',
            ]);
        }

        $pipeline->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pipeline deleted.']);

        return redirect()->route('pipelines.index');
    }

    public function archive(Pipeline $pipeline): RedirectResponse
    {
        $this->authorize('update', $pipeline);

        $pipeline->update(['archived_at' => $pipeline->archived_at ?? now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pipeline archived.']);

        return redirect()->route('pipelines.index');
    }

    public function storeStage(StageRequest $request, Pipeline $pipeline): RedirectResponse
    {
        $position = (int) $pipeline->stages()->max('position') + 1;

        $pipeline->stages()->create([
            'name' => $request->string('name')->toString(),
            'purpose' => $request->input('purpose') ?: null,
            'kind' => $request->string('kind')->toString(),
            'position' => $position,
            'default_follow_up_delay_hours' => $request->input('default_follow_up_delay_hours'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Stage added.']);

        return redirect()->route('pipelines.show', $pipeline);
    }

    public function updateStage(StageRequest $request, Pipeline $pipeline, PipelineStage $stage): RedirectResponse
    {
        abort_unless($stage->pipeline_id === $pipeline->id, 404);

        $stage->update([
            'name' => $request->string('name')->toString(),
            'purpose' => $request->input('purpose') ?: null,
            'kind' => $request->string('kind')->toString(),
            'default_follow_up_delay_hours' => $request->input('default_follow_up_delay_hours') === ''
                ? null
                : $request->input('default_follow_up_delay_hours'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Stage saved.']);

        return redirect()->route('pipelines.show', $pipeline);
    }

    public function archiveStage(Pipeline $pipeline, PipelineStage $stage): RedirectResponse
    {
        $this->authorize('update', $pipeline);
        abort_unless($stage->pipeline_id === $pipeline->id, 404);

        $stage->update(['archived_at' => $stage->archived_at ?? now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Stage archived.']);

        return redirect()->route('pipelines.show', $pipeline);
    }

    public function reorder(ReorderStagesRequest $request, Pipeline $pipeline, ReorderStages $action): RedirectResponse
    {
        /** @var list<int> $ids */
        $ids = array_map('intval', $request->validated('stage_ids'));
        $action->handle($pipeline, $ids);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Stage order saved.']);

        return redirect()->route('pipelines.show', $pipeline);
    }

    public function transitions(TransitionMapRequest $request, Pipeline $pipeline, SavePipelineTransitions $action): RedirectResponse
    {
        /** @var array<int|string, array<int, int|string>> $map */
        $map = $request->validated('transitions');
        $action->handle($pipeline, $map);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Permitted transitions saved.']);

        return redirect()->route('pipelines.show', $pipeline);
    }
}
