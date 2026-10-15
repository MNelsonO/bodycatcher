<?php

namespace App\Reports\Services;

use App\Ai\AnalysisStatus;
use App\Ai\LeadAnalysis;
use App\Billing\Invoice;
use App\Billing\InvoiceStatus;
use App\Expenses\Expense;
use App\Leads\Lead;
use App\SalesPipelines\StageKind;
use App\Settings\DisplayClock;
use App\Settings\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DeskReports
{
    /** @var list<string> */
    private const HIGH_INTEREST_STAGES = [
        'Interested',
        'Demo Requested',
        'Demo Scheduled',
        'Demo Completed',
        'Proposal / Price Sent',
        'Negotiation',
    ];

    public function __construct(private DisplayClock $clock) {}

    /**
     * @return array<string, mixed>
     */
    public function build(?string $from, ?string $to): array
    {
        [$fromDate, $toDate] = $this->resolveRange($from, $to);
        [$fromUtc, $toUtc] = $this->utcBounds($fromDate, $toDate);

        $products = $this->productRows($fromUtc, $toUtc);
        $daily = $this->dailySeries($fromDate, $toDate);
        $spend = $this->spendBlock($fromDate, $toDate);

        $leadsTotal = array_sum(array_column($products, 'leads'));
        $wonTotal = array_sum(array_column($products, 'won'));
        $interestTotal = array_sum(array_column($products, 'high_interest'));

        return [
            'filters' => [
                'from' => $fromDate,
                'to' => $toDate,
            ],
            'summary' => [
                'leads' => $leadsTotal,
                'won' => $wonTotal,
                'conversion_rate' => $leadsTotal > 0 ? round(($wonTotal / $leadsTotal) * 100, 1) : 0.0,
                'high_interest' => $interestTotal,
                'spend' => $spend['total'],
                'bought_revenue' => $this->boughtRevenue($fromUtc, $toUtc),
                'currency' => $spend['currency'],
            ],
            'products' => $products,
            'daily' => $daily,
            'spend' => $spend,
            'timezone' => $this->clock->timezone(),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveRange(?string $from, ?string $to): array
    {
        $today = Carbon::parse($this->clock->today(), $this->clock->timezone())->startOfDay();
        $toDate = filled($to) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) === 1
            ? Carbon::parse($to, $this->clock->timezone())->startOfDay()
            : $today->copy();
        $fromDate = filled($from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) === 1
            ? Carbon::parse($from, $this->clock->timezone())->startOfDay()
            : $toDate->copy()->subDays(29);

        if ($fromDate->greaterThan($toDate)) {
            [$fromDate, $toDate] = [$toDate->copy(), $fromDate->copy()];
        }

        // Cap range length to keep reports snappy.
        if ($fromDate->diffInDays($toDate) > 365) {
            $fromDate = $toDate->copy()->subDays(365);
        }

        return [$fromDate->toDateString(), $toDate->toDateString()];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function utcBounds(string $fromDate, string $toDate): array
    {
        $fromUtc = Carbon::parse($fromDate, $this->clock->timezone())->startOfDay()->utc();
        $toUtc = Carbon::parse($toDate, $this->clock->timezone())->endOfDay()->utc();

        return [$fromUtc, $toUtc];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function productRows(Carbon $fromUtc, Carbon $toUtc): array
    {
        $products = Product::query()->orderBy('name')->get(['id', 'name']);

        $leadCounts = Lead::query()
            ->selectRaw('product_id, count(*) as total')
            ->whereBetween('created_at', [$fromUtc, $toUtc])
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

        $wonCounts = Lead::query()
            ->selectRaw('leads.product_id, count(*) as total')
            ->join('pipeline_stages', 'pipeline_stages.id', '=', 'leads.current_stage_id')
            ->where('pipeline_stages.kind', StageKind::Won->value)
            ->whereBetween('leads.closed_at', [$fromUtc, $toUtc])
            ->groupBy('leads.product_id')
            ->pluck('total', 'product_id');

        $lostCounts = Lead::query()
            ->selectRaw('leads.product_id, count(*) as total')
            ->join('pipeline_stages', 'pipeline_stages.id', '=', 'leads.current_stage_id')
            ->where('pipeline_stages.kind', StageKind::Lost->value)
            ->whereBetween('leads.closed_at', [$fromUtc, $toUtc])
            ->groupBy('leads.product_id')
            ->pluck('total', 'product_id');

        $interestCounts = Lead::query()
            ->selectRaw('leads.product_id, count(*) as total')
            ->join('pipeline_stages', 'pipeline_stages.id', '=', 'leads.current_stage_id')
            ->whereBetween('leads.created_at', [$fromUtc, $toUtc])
            ->where(function ($query): void {
                $query->whereIn('pipeline_stages.name', self::HIGH_INTEREST_STAGES)
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
            })
            ->groupBy('leads.product_id')
            ->pluck('total', 'product_id');

        $avgScores = LeadAnalysis::query()
            ->selectRaw('leads.product_id, avg(lead_analyses.score) as avg_score')
            ->join('leads', 'leads.id', '=', 'lead_analyses.lead_id')
            ->where('lead_analyses.status', AnalysisStatus::Completed->value)
            ->whereNotNull('lead_analyses.score')
            ->whereBetween('leads.created_at', [$fromUtc, $toUtc])
            ->whereRaw('lead_analyses.id = (
                select max(la2.id) from lead_analyses la2
                where la2.lead_id = lead_analyses.lead_id
                  and la2.status = ?
            )', [AnalysisStatus::Completed->value])
            ->groupBy('leads.product_id')
            ->pluck('avg_score', 'product_id');

        return $products->map(function (Product $product) use ($leadCounts, $wonCounts, $lostCounts, $interestCounts, $avgScores): array {
            $leads = (int) ($leadCounts[$product->id] ?? 0);
            $won = (int) ($wonCounts[$product->id] ?? 0);
            $lost = (int) ($lostCounts[$product->id] ?? 0);
            $highInterest = (int) ($interestCounts[$product->id] ?? 0);
            $avgScore = isset($avgScores[$product->id]) ? round((float) $avgScores[$product->id], 1) : null;

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'leads' => $leads,
                'won' => $won,
                'lost' => $lost,
                'high_interest' => $highInterest,
                'conversion_rate' => $leads > 0 ? round(($won / $leads) * 100, 1) : 0.0,
                'avg_score' => $avgScore,
            ];
        })->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dailySeries(string $fromDate, string $toDate): array
    {
        $cursor = Carbon::parse($fromDate, $this->clock->timezone())->startOfDay();
        $end = Carbon::parse($toDate, $this->clock->timezone())->startOfDay();
        $rows = [];

        while ($cursor->lessThanOrEqualTo($end)) {
            $day = $cursor->toDateString();
            [$start, $finish] = $this->utcBounds($day, $day);

            $opened = Lead::query()->whereBetween('created_at', [$start, $finish])->count();
            $won = Lead::query()
                ->whereHas('currentStage', fn ($q) => $q->where('kind', StageKind::Won->value))
                ->whereBetween('closed_at', [$start, $finish])
                ->count();
            $spend = (float) Expense::query()->whereDate('spent_on', $day)->sum('amount')
                + (float) Expense::query()->whereDate('spent_on', $day)->sum('tax_amount');

            $rows[] = [
                'date' => $day,
                'label' => $cursor->format('d M'),
                'leads' => $opened,
                'won' => $won,
                'spend' => round($spend, 2),
            ];

            $cursor->addDay();
        }

        return $rows;
    }

    /**
     * @return array{total: float, currency: string, by_category: list<array{category: string, gross: float, rows: int}>}
     */
    private function spendBlock(string $fromDate, string $toDate): array
    {
        $total = (float) Expense::query()->whereBetween('spent_on', [$fromDate, $toDate])->sum('amount')
            + (float) Expense::query()->whereBetween('spent_on', [$fromDate, $toDate])->sum('tax_amount');

        $byCategory = Expense::query()
            ->select('category')
            ->selectRaw('coalesce(sum(amount), 0) as amount_sum')
            ->selectRaw('coalesce(sum(tax_amount), 0) as tax_sum')
            ->selectRaw('count(*) as rows')
            ->whereBetween('spent_on', [$fromDate, $toDate])
            ->groupBy('category')
            ->orderByDesc('amount_sum')
            ->get()
            ->map(fn ($row): array => [
                'category' => (string) $row->category,
                'gross' => round((float) $row->amount_sum + (float) $row->tax_sum, 2),
                'rows' => (int) $row->rows,
            ])
            ->all();

        $currency = Expense::query()->value('currency')
            ?? Invoice::query()->value('currency')
            ?? 'KES';

        return [
            'total' => round($total, 2),
            'currency' => (string) $currency,
            'by_category' => $byCategory,
        ];
    }

    private function boughtRevenue(Carbon $fromUtc, Carbon $toUtc): float
    {
        return round((float) Invoice::query()
            ->where('status', InvoiceStatus::Paid->value)
            ->whereBetween('paid_at', [$fromUtc, $toUtc])
            ->sum('total'), 2);
    }
}
