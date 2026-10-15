<?php

namespace App\Leads\Services;

use App\Ai\AnalysisStatus;
use App\Leads\Lead;
use App\SalesPipelines\DefaultKemetStages;
use App\SalesPipelines\PipelineStage;
use App\SalesPipelines\StageKind;
use App\Settings\DisplayClock;
use App\Settings\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CommandCentreBoard
{
    /** @return list<string> */
    public static function hotStages(): array
    {
        return DefaultKemetStages::hotStages();
    }

    public function __construct(
        private DisplayClock $clock,
        private FollowUpQuery $followUps,
    ) {}

    /**
     * @param  array{
     *     kind?: string,
     *     stage_id?: string|int,
     *     product_id?: string|int,
     *     hot?: string|bool,
     *     follow_up?: string,
     *     timeline?: string,
     *     from?: string|null,
     *     to?: string|null,
     *     q?: string
     * }  $filters
     * @return array{
     *     filters: array<string, mixed>,
     *     options: array<string, mixed>,
     *     range: array{from: string|null, to: string|null, label: string},
     *     utc_bounds: array{0: Carbon, 1: Carbon}|null,
     *     summary: array<string, int>,
     *     leads: list<array<string, mixed>>
     * }
     */
    public function build(array $filters): array
    {
        $normalized = $this->normalize($filters);
        [$fromDate, $toDate, $rangeLabel] = $this->resolveDates($normalized);
        $utcBounds = $fromDate !== null && $toDate !== null
            ? $this->utcBounds($fromDate, $toDate)
            : null;

        $query = Lead::query()
            ->with(['contact', 'product', 'currentStage', 'pipeline']);

        $this->applyFilters($query, $normalized, $utcBounds);

        if (in_array($normalized['follow_up'], ['ranked', 'chat'], true)) {
            $query->reorder()->orderByDesc('follow_up_rank')->orderBy('next_follow_up_on');
        } else {
            $query->orderByDesc('updated_at');
        }

        $summaryQuery = Lead::query();
        $this->applyFilters($summaryQuery, $normalized, $utcBounds);

        $leads = (clone $query)->limit(40)->get();

        return [
            'filters' => $normalized,
            'options' => $this->options(),
            'range' => [
                'from' => $fromDate,
                'to' => $toDate,
                'label' => $rangeLabel,
            ],
            'utc_bounds' => $utcBounds,
            'summary' => [
                'matched' => (clone $summaryQuery)->count(),
                'open' => (clone $summaryQuery)->whereNull('closed_at')
                    ->whereHas('currentStage', fn (Builder $stage) => $stage->where('kind', StageKind::Active->value))
                    ->count(),
                'hot' => (clone $summaryQuery)->where(function (Builder $hot): void {
                    $this->constrainHot($hot);
                })->count(),
                'won' => (clone $summaryQuery)->whereHas('currentStage', fn (Builder $stage) => $stage->where('kind', StageKind::Won->value))->count(),
                'lost' => (clone $summaryQuery)->whereHas('currentStage', fn (Builder $stage) => $stage->where('kind', StageKind::Lost->value))->count(),
            ],
            'leads' => $leads->map(fn (Lead $lead): array => $this->row($lead))->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{kind: string, stage_id: string, product_id: string, hot: bool, follow_up: string, timeline: string, from: string, to: string, q: string}
     */
    public function normalize(array $filters): array
    {
        $kind = trim((string) ($filters['kind'] ?? ''));
        $kinds = array_map(fn (StageKind $case): string => $case->value, StageKind::cases());
        if ($kind === '' || $kind === '__all__' || ! in_array($kind, $kinds, true)) {
            $kind = '';
        }

        $stageId = isset($filters['stage_id']) && is_numeric($filters['stage_id'])
            ? (string) (int) $filters['stage_id']
            : '';
        if ($stageId === '0') {
            $stageId = '';
        }

        $productId = isset($filters['product_id']) && is_numeric($filters['product_id'])
            ? (string) (int) $filters['product_id']
            : '';
        if ($productId === '0') {
            $productId = '';
        }

        $hotRaw = $filters['hot'] ?? '';
        $hot = $hotRaw === true || $hotRaw === 1 || $hotRaw === '1' || $hotRaw === 'true' || $hotRaw === 'hot';

        $followUp = trim((string) ($filters['follow_up'] ?? ''));
        if (! in_array($followUp, ['due_today', 'overdue', 'upcoming', 'none'], true)) {
            $followUp = '';
        }

        $timeline = trim((string) ($filters['timeline'] ?? ''));
        if ($timeline === '__all__') {
            $timeline = '';
        }
        if ($timeline !== '' && $timeline !== 'custom' && $this->clock->periodUtcRange($timeline) === null) {
            $timeline = '';
        }

        $from = trim((string) ($filters['from'] ?? ''));
        $to = trim((string) ($filters['to'] ?? ''));
        if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) !== 1) {
            $from = '';
        }
        if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) !== 1) {
            $to = '';
        }

        return [
            'kind' => $kind,
            'stage_id' => $stageId,
            'product_id' => $productId,
            'hot' => $hot,
            'follow_up' => $followUp,
            'timeline' => $timeline,
            'from' => $from,
            'to' => $to,
            'q' => trim((string) ($filters['q'] ?? '')),
        ];
    }

    /**
     * @param  array{kind: string, stage_id: string, product_id: string, hot: bool, follow_up: string, timeline: string, from: string, to: string, q: string}  $filters
     * @return array{0: string|null, 1: string|null, 2: string}
     */
    private function resolveDates(array $filters): array
    {
        if ($filters['from'] !== '' || $filters['to'] !== '') {
            $to = $filters['to'] !== '' ? $filters['to'] : $this->clock->today();
            $from = $filters['from'] !== '' ? $filters['from'] : $to;
            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }

            return [$from, $to, 'Custom range'];
        }

        return match ($filters['timeline']) {
            'today' => [$this->clock->today(), $this->clock->today(), 'Today'],
            'this_week' => [
                Carbon::parse($this->clock->today(), $this->clock->timezone())->startOfWeek()->toDateString(),
                Carbon::parse($this->clock->today(), $this->clock->timezone())->endOfWeek()->toDateString(),
                'This week',
            ],
            'this_month' => [
                Carbon::parse($this->clock->today(), $this->clock->timezone())->startOfMonth()->toDateString(),
                Carbon::parse($this->clock->today(), $this->clock->timezone())->endOfMonth()->toDateString(),
                'This month',
            ],
            'last_7_days' => [
                Carbon::parse($this->clock->today(), $this->clock->timezone())->subDays(6)->toDateString(),
                $this->clock->today(),
                'Last 7 days',
            ],
            'last_30_days' => [
                Carbon::parse($this->clock->today(), $this->clock->timezone())->subDays(29)->toDateString(),
                $this->clock->today(),
                'Last 30 days',
            ],
            'next_week' => [
                Carbon::parse($this->clock->today(), $this->clock->timezone())->addWeek()->startOfWeek()->toDateString(),
                Carbon::parse($this->clock->today(), $this->clock->timezone())->addWeek()->endOfWeek()->toDateString(),
                'Next week',
            ],
            default => [null, null, 'All time'],
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function utcBounds(string $fromDate, string $toDate): array
    {
        return [
            Carbon::parse($fromDate, $this->clock->timezone())->startOfDay()->utc(),
            Carbon::parse($toDate, $this->clock->timezone())->endOfDay()->utc(),
        ];
    }

    /**
     * @param  Builder<Lead>  $query
     * @param  array{kind: string, stage_id: string, product_id: string, hot: bool, follow_up: string, timeline: string, from: string, to: string, q: string}  $filters
     * @param  array{0: Carbon, 1: Carbon}|null  $utcBounds
     */
    private function applyFilters(Builder $query, array $filters, ?array $utcBounds): void
    {
        if ($filters['kind'] !== '') {
            $query->whereHas('currentStage', fn (Builder $stage) => $stage->where('kind', $filters['kind']));
        }

        if ($filters['stage_id'] !== '') {
            $query->where('current_stage_id', (int) $filters['stage_id']);
        }

        if ($filters['product_id'] !== '') {
            $query->where('product_id', (int) $filters['product_id']);
        }

        if ($filters['hot']) {
            $query->where(function (Builder $hot): void {
                $this->constrainHot($hot);
            });
        }

        if ($filters['follow_up'] !== '') {
            $this->followUps->apply($query, $filters['follow_up']);
        }

        if ($utcBounds !== null) {
            [$start, $end] = $utcBounds;
            $query->where(function (Builder $timeline) use ($start, $end): void {
                $timeline->whereBetween('created_at', [$start, $end])
                    ->orWhereBetween('updated_at', [$start, $end])
                    ->orWhereBetween('closed_at', [$start, $end])
                    ->orWhereBetween('next_conversation_at', [$start, $end])
                    ->orWhere(function (Builder $follow) use ($start, $end): void {
                        $follow->whereNotNull('next_follow_up_on')
                            ->whereDate('next_follow_up_on', '>=', $start->timezone($this->clock->timezone())->toDateString())
                            ->whereDate('next_follow_up_on', '<=', $end->timezone($this->clock->timezone())->toDateString());
                    });
            });
        }

        if ($filters['q'] !== '') {
            $needle = '%'.mb_strtolower($filters['q']).'%';
            $query->where(function (Builder $match) use ($needle): void {
                $match->whereHas('contact', function (Builder $contact) use ($needle): void {
                    $contact->whereRaw('lower(full_name) like ?', [$needle])
                        ->orWhereRaw('lower(coalesce(business_name, \'\')) like ?', [$needle]);
                })->orWhereRaw('lower(coalesce(business_problem, \'\')) like ?', [$needle])
                    ->orWhereRaw('lower(coalesce(next_action, \'\')) like ?', [$needle]);
            });
        }
    }

    /**
     * @param  Builder<Lead>  $query
     */
    private function constrainHot(Builder $query): void
    {
        $query->where(function (Builder $hot): void {
            $hot->whereHas('currentStage', fn (Builder $stage) => $stage->whereIn('name', self::hotStages()))
                ->orWhereExists(function ($sub): void {
                    $sub->select(DB::raw(1))
                        ->from('lead_analyses')
                        ->whereColumn('lead_analyses.lead_id', 'leads.id')
                        ->where('lead_analyses.status', AnalysisStatus::Completed->value)
                        ->where('lead_analyses.score', '>=', 70)
                        ->whereRaw('lead_analyses.id = (
                            select max(la2.id) from lead_analyses la2 where la2.lead_id = leads.id
                        )');
                });
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'kinds' => [
                ['value' => '__all__', 'label' => 'All statuses'],
                ['value' => StageKind::Active->value, 'label' => 'Open / active'],
                ['value' => StageKind::Won->value, 'label' => 'Won'],
                ['value' => StageKind::Lost->value, 'label' => 'Lost'],
                ['value' => StageKind::ArchivedNurture->value, 'label' => 'Nurture / archived'],
            ],
            'stages' => PipelineStage::query()
                ->with('pipeline:id,name')
                ->whereNull('archived_at')
                ->orderBy('position')
                ->get()
                ->map(fn (PipelineStage $stage): array => [
                    'value' => (string) $stage->id,
                    'label' => ($stage->pipeline?->name ? $stage->pipeline->name.' · ' : '').$stage->name,
                ])
                ->all(),
            'products' => Product::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Product $product): array => [
                    'value' => (string) $product->id,
                    'label' => $product->name,
                ])
                ->all(),
            'follow_ups' => [
                ['value' => '__all__', 'label' => 'Any follow-up'],
                ['value' => 'ranked', 'label' => 'Ranked follow-ups'],
                ['value' => 'chat', 'label' => 'From chat dates'],
                ['value' => 'due_today', 'label' => 'Due today'],
                ['value' => 'overdue', 'label' => 'Overdue'],
                ['value' => 'upcoming', 'label' => 'Upcoming'],
                ['value' => 'none', 'label' => 'No follow-up date'],
            ],
            'timelines' => [
                ['value' => '__all__', 'label' => 'All time'],
                ['value' => 'today', 'label' => 'Today'],
                ['value' => 'this_week', 'label' => 'This week'],
                ['value' => 'next_week', 'label' => 'Next week'],
                ['value' => 'this_month', 'label' => 'This month'],
                ['value' => 'last_7_days', 'label' => 'Last 7 days'],
                ['value' => 'last_30_days', 'label' => 'Last 30 days'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Lead $lead): array
    {
        $latestScore = DB::table('lead_analyses')
            ->where('lead_id', $lead->id)
            ->where('status', AnalysisStatus::Completed->value)
            ->whereNotNull('score')
            ->orderByDesc('id')
            ->value('score');

        $isHot = in_array($lead->currentStage->name, self::hotStages(), true)
            || (is_numeric($latestScore) && (int) $latestScore >= 70);

        return [
            'id' => $lead->id,
            'contact_name' => $lead->contact->full_name,
            'business_name' => $lead->contact->business_name,
            'product_name' => $lead->product->name,
            'stage_name' => $lead->currentStage->name,
            'stage_kind' => $lead->currentStage->kind->value,
            'source' => $lead->source,
            'intent' => $lead->business_problem,
            'next_action' => $lead->next_action,
            'follow_up_label' => $this->clock->formatDate($lead->next_follow_up_on),
            'next_talk_label' => $this->clock->formatDateTime($lead->next_conversation_at),
            'follow_up_rank' => (int) $lead->follow_up_rank,
            'follow_up_rank_reason' => $lead->follow_up_rank_reason,
            'follow_up_source' => $lead->follow_up_source,
            'ai_score' => is_numeric($latestScore) ? (int) $latestScore : null,
            'hot' => $isHot,
            'updated_label' => $this->clock->formatDateTime($lead->updated_at),
        ];
    }
}
