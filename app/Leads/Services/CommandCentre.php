<?php

namespace App\Leads\Services;

use App\Leads\Lead;
use App\SalesPipelines\StageKind;
use App\Settings\DisplayClock;
use Illuminate\Support\Collection;

class CommandCentre
{
    public function __construct(
        private DisplayClock $clock,
        private FollowUpQuery $followUps,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function metrics(): array
    {
        [$start, $end] = $this->clock->todayUtcRange();
        $today = $this->clock->today();

        $byStage = Lead::query()
            ->join('pipeline_stages', 'pipeline_stages.id', '=', 'leads.current_stage_id')
            ->join('pipelines', 'pipelines.id', '=', 'leads.pipeline_id')
            ->selectRaw('pipeline_stages.id as stage_id, pipeline_stages.name as stage_name, pipelines.name as pipeline_name, pipeline_stages.kind as kind, count(*) as total')
            ->groupBy('pipeline_stages.id', 'pipeline_stages.name', 'pipelines.name', 'pipeline_stages.kind', 'pipeline_stages.position')
            ->orderBy('pipelines.name')
            ->orderBy('pipeline_stages.position')
            ->get()
            ->map(fn (Lead $row): array => [
                'stage_id' => (int) $row->getAttribute('stage_id'),
                'stage_name' => (string) $row->getAttribute('stage_name'),
                'pipeline_name' => (string) $row->getAttribute('pipeline_name'),
                'kind' => (string) $row->getAttribute('kind'),
                'total' => (int) $row->getAttribute('total'),
            ])
            ->all();

        $followUpRows = $this->followUpRows($today);

        return [
            'total_leads' => Lead::query()->count(),
            'new_leads_today' => Lead::query()->whereBetween('created_at', [$start, $end])->count(),
            'follow_ups_due_today' => $this->followUps->apply(Lead::query(), 'due_today')->count(),
            'overdue_follow_ups' => $this->followUps->apply(Lead::query(), 'overdue')->count(),
            'won_count' => $this->countByKind(StageKind::Won),
            'lost_count' => $this->countByKind(StageKind::Lost),
            'leads_by_stage' => $byStage,
            'follow_up_rows' => $followUpRows,
            'today' => $today,
            'timezone' => $this->clock->timezone(),
        ];
    }

    private function countByKind(StageKind $kind): int
    {
        return Lead::query()
            ->whereHas('currentStage', fn (mixed $query) => $query->where('kind', $kind->value))
            ->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function followUpRows(string $today): array
    {
        /** @var Collection<int, Lead> $leads */
        $leads = Lead::query()
            ->with(['contact', 'currentStage', 'product'])
            ->whereNotNull('next_follow_up_on')
            ->whereDate('next_follow_up_on', '<=', $today)
            ->orderBy('next_follow_up_on')
            ->limit(20)
            ->get();

        return $leads->map(function (Lead $lead) use ($today): array {
            $date = $lead->next_follow_up_on?->toDateString();
            $status = $date === $today ? 'due_today' : 'overdue';

            return [
                'id' => $lead->id,
                'contact_name' => $lead->contact->full_name,
                'business_name' => $lead->contact->business_name,
                'product_name' => $lead->product->name,
                'stage_name' => $lead->currentStage->name,
                'next_action' => $lead->next_action,
                'follow_up_on' => $date,
                'follow_up_label' => $this->clock->formatDate($lead->next_follow_up_on),
                'status' => $status,
                'reason' => $status === 'due_today' ? 'Due today' : 'Overdue',
            ];
        })->all();
    }
}
