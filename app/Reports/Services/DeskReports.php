<?php

namespace App\Reports\Services;

use App\Ai\AnalysisStatus;
use App\Ai\LeadAnalysis;
use App\Billing\BillingSubscription;
use App\Billing\BusinessProfile;
use App\Billing\Invoice;
use App\Billing\InvoiceStatus;
use App\Billing\TaxLedgerEntry;
use App\Contacts\Contact;
use App\Conversations\Message;
use App\Conversations\MessageDirection;
use App\Expenses\Expense;
use App\FollowUps\FollowUpTask;
use App\Integrations\IntegrationReport;
use App\Leads\Lead;
use App\Leads\LeadInteraction;
use App\Money\MoneyAccount;
use App\Money\MoneyDebt;
use App\Money\MoneyRecurring;
use App\SalesPipelines\DefaultKemetStages;
use App\SalesPipelines\StageKind;
use App\Settings\DisplayClock;
use App\Settings\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DeskReports
{
    /** @return list<string> */
    private function highInterestStages(): array
    {
        return DefaultKemetStages::highInterestStages();
    }

    public function __construct(private DisplayClock $clock) {}

    /**
     * @return array<string, mixed>
     */
    public function build(?string $from, ?string $to): array
    {
        [$fromDate, $toDate] = $this->resolveRange($from, $to);
        [$fromUtc, $toUtc] = $this->utcBounds($fromDate, $toDate);
        $currency = $this->currency();

        $products = $this->productRows($fromUtc, $toUtc);
        $daily = $this->dailySeries($fromDate, $toDate);
        $spend = $this->spendBlock($fromDate, $toDate);
        $billing = $this->billingSector($fromUtc, $toUtc, $fromDate, $toDate);
        $sales = $this->salesSector($fromUtc, $toUtc, $products);
        $messaging = $this->messagingSector($fromUtc, $toUtc);
        $ai = $this->aiSector($fromUtc, $toUtc);
        $followUps = $this->followUpSector($fromUtc, $toUtc);
        $contacts = $this->contactsSector();
        $subscriptions = $this->subscriptionsSector($fromDate, $toDate);
        $money = $this->moneySector();
        $integrations = $this->integrationsSector();

        $leadsTotal = (int) array_sum(array_column($products, 'leads'));
        $wonTotal = (int) array_sum(array_column($products, 'won'));
        $lostTotal = (int) array_sum(array_column($products, 'lost'));
        $interestTotal = (int) array_sum(array_column($products, 'high_interest'));
        $revenue = (float) $billing['paid_revenue'];
        $spendTotal = (float) $spend['total'];

        return [
            'filters' => [
                'from' => $fromDate,
                'to' => $toDate,
            ],
            'summary' => [
                'leads' => $leadsTotal,
                'won' => $wonTotal,
                'lost' => $lostTotal,
                'open' => $sales['open_leads'],
                'conversion_rate' => $leadsTotal > 0 ? round(($wonTotal / $leadsTotal) * 100, 1) : 0.0,
                'high_interest' => $interestTotal,
                'spend' => $spendTotal,
                'bought_revenue' => $revenue,
                'net_position' => round($revenue - $spendTotal, 2),
                'unpaid_invoices' => $billing['unpaid_total'],
                'messages' => $messaging['total'],
                'ai_reads' => $ai['completed'],
                'currency' => $currency,
            ],
            'products' => $products,
            'daily' => $daily,
            'spend' => $spend,
            'sectors' => [
                'sales' => $sales,
                'billing' => $billing,
                'subscriptions' => $subscriptions,
                'expenses' => $spend,
                'money' => $money,
                'messaging' => $messaging,
                'ai' => $ai,
                'follow_ups' => $followUps,
                'contacts' => $contacts,
                'integrations' => $integrations,
            ],
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

    private function currency(): string
    {
        return (string) (BusinessProfile::current()->currency
            ?: Expense::query()->value('currency')
            ?: Invoice::query()->value('currency')
            ?: 'KES');
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

        $openCounts = Lead::query()
            ->selectRaw('leads.product_id, count(*) as total')
            ->join('pipeline_stages', 'pipeline_stages.id', '=', 'leads.current_stage_id')
            ->where('pipeline_stages.kind', StageKind::Active->value)
            ->whereNull('leads.closed_at')
            ->groupBy('leads.product_id')
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
                $query->whereIn('pipeline_stages.name', $this->highInterestStages())
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

        return $products->map(function (Product $product) use ($leadCounts, $openCounts, $wonCounts, $lostCounts, $interestCounts, $avgScores): array {
            $leads = (int) ($leadCounts[$product->id] ?? 0);
            $won = (int) ($wonCounts[$product->id] ?? 0);
            $lost = (int) ($lostCounts[$product->id] ?? 0);
            $highInterest = (int) ($interestCounts[$product->id] ?? 0);
            $avgScore = isset($avgScores[$product->id]) ? round((float) $avgScores[$product->id], 1) : null;

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'leads' => $leads,
                'open' => (int) ($openCounts[$product->id] ?? 0),
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
        [$fromUtc, $toUtc] = $this->utcBounds($fromDate, $toDate);
        $tz = $this->clock->timezone();

        $leadsByDay = $this->countByDisplayDay(
            Lead::query()->whereBetween('created_at', [$fromUtc, $toUtc]),
            'created_at',
            $tz,
        );

        $wonByDay = $this->countByDisplayDay(
            Lead::query()
                ->whereHas('currentStage', fn ($q) => $q->where('kind', StageKind::Won->value))
                ->whereBetween('closed_at', [$fromUtc, $toUtc]),
            'closed_at',
            $tz,
        );

        $messagesByDay = $this->countByDisplayDay(
            Message::query()->whereBetween('sent_at', [$fromUtc, $toUtc]),
            'sent_at',
            $tz,
        );

        $revenueByDay = Invoice::query()
            ->where('status', InvoiceStatus::Paid->value)
            ->whereBetween('paid_at', [$fromUtc, $toUtc])
            ->get(['paid_at', 'total'])
            ->groupBy(fn (Invoice $invoice): string => Carbon::parse($invoice->paid_at)->timezone($tz)->toDateString())
            ->map(fn (Collection $rows): float => round((float) $rows->sum('total'), 2));

        $spendByDay = Expense::query()
            ->whereDate('spent_on', '>=', $fromDate)
            ->whereDate('spent_on', '<=', $toDate)
            ->get(['spent_on', 'amount', 'tax_amount'])
            ->groupBy(fn (Expense $expense): string => Carbon::parse($expense->spent_on)->toDateString())
            ->map(fn (Collection $rows): float => round(
                (float) $rows->sum('amount') + (float) $rows->sum('tax_amount'),
                2,
            ));

        $cursor = Carbon::parse($fromDate, $tz)->startOfDay();
        $end = Carbon::parse($toDate, $tz)->startOfDay();
        $rows = [];

        while ($cursor->lessThanOrEqualTo($end)) {
            $day = $cursor->toDateString();
            $rows[] = [
                'date' => $day,
                'label' => $cursor->format('d M'),
                'leads' => (int) ($leadsByDay[$day] ?? 0),
                'won' => (int) ($wonByDay[$day] ?? 0),
                'messages' => (int) ($messagesByDay[$day] ?? 0),
                'revenue' => (float) ($revenueByDay[$day] ?? 0),
                'spend' => (float) ($spendByDay[$day] ?? 0),
            ];
            $cursor->addDay();
        }

        return $rows;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Collection<string, int>
     */
    private function countByDisplayDay($query, string $column, string $tz): Collection
    {
        return $query->get([$column])
            ->groupBy(fn ($row): string => Carbon::parse($row->getAttribute($column))->timezone($tz)->toDateString())
            ->map(fn (Collection $rows): int => $rows->count());
    }

    /**
     * @return array{total: float, currency: string, by_category: list<array{category: string, gross: float, rows: int}>}
     */
    private function spendBlock(string $fromDate, string $toDate): array
    {
        $expenseRange = fn ($query) => $query
            ->whereDate('spent_on', '>=', $fromDate)
            ->whereDate('spent_on', '<=', $toDate);

        $total = (float) Expense::query()->tap($expenseRange)->sum('amount')
            + (float) Expense::query()->tap($expenseRange)->sum('tax_amount');

        $byCategory = Expense::query()
            ->tap($expenseRange)
            ->select('category')
            ->selectRaw('coalesce(sum(amount), 0) as amount_sum')
            ->selectRaw('coalesce(sum(tax_amount), 0) as tax_sum')
            ->selectRaw('count(*) as rows')
            ->groupBy('category')
            ->orderByDesc('amount_sum')
            ->get()
            ->map(fn ($row): array => [
                'category' => (string) $row->category,
                'gross' => round((float) $row->amount_sum + (float) $row->tax_sum, 2),
                'rows' => (int) $row->rows,
            ])
            ->all();

        return [
            'total' => round($total, 2),
            'currency' => $this->currency(),
            'by_category' => $byCategory,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $products
     * @return array<string, mixed>
     */
    private function salesSector(Carbon $fromUtc, Carbon $toUtc, array $products): array
    {
        $byStage = Lead::query()
            ->join('pipeline_stages', 'pipeline_stages.id', '=', 'leads.current_stage_id')
            ->join('pipelines', 'pipelines.id', '=', 'leads.pipeline_id')
            ->where(function ($query) use ($fromUtc, $toUtc): void {
                $query->whereBetween('leads.created_at', [$fromUtc, $toUtc])
                    ->orWhere(function ($open): void {
                        $open->whereNull('leads.closed_at')
                            ->whereHas('currentStage', fn ($stage) => $stage->where('kind', StageKind::Active->value));
                    });
            })
            ->selectRaw('pipeline_stages.id as stage_id, pipeline_stages.name as stage_name, pipelines.name as pipeline_name, pipeline_stages.kind as kind, count(*) as total')
            ->groupBy('pipeline_stages.id', 'pipeline_stages.name', 'pipelines.name', 'pipeline_stages.kind', 'pipeline_stages.position')
            ->orderBy('pipelines.name')
            ->orderBy('pipeline_stages.position')
            ->get()
            ->map(fn ($row): array => [
                'stage_id' => (int) $row->stage_id,
                'stage_name' => (string) $row->stage_name,
                'pipeline_name' => (string) $row->pipeline_name,
                'kind' => (string) $row->kind,
                'total' => (int) $row->total,
            ])
            ->all();

        $bySource = Lead::query()
            ->whereBetween('created_at', [$fromUtc, $toUtc])
            ->selectRaw("coalesce(nullif(trim(source), ''), 'Unknown') as source_label, count(*) as total")
            ->groupBy('source_label')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'source' => (string) $row->source_label,
                'total' => (int) $row->total,
            ])
            ->all();

        $demoNames = DefaultKemetStages::demoBucket();
        $demos = Lead::query()
            ->join('pipeline_stages', 'pipeline_stages.id', '=', 'leads.current_stage_id')
            ->whereIn('pipeline_stages.name', $demoNames)
            ->whereNull('leads.closed_at')
            ->selectRaw('pipeline_stages.name as stage_name, count(*) as total')
            ->groupBy('pipeline_stages.name')
            ->pluck('total', 'stage_name');

        return [
            'open_leads' => Lead::query()
                ->whereNull('closed_at')
                ->whereHas('currentStage', fn ($q) => $q->where('kind', StageKind::Active->value))
                ->count(),
            'new_in_range' => (int) array_sum(array_column($products, 'leads')),
            'won_in_range' => (int) array_sum(array_column($products, 'won')),
            'lost_in_range' => (int) array_sum(array_column($products, 'lost')),
            'by_stage' => $byStage,
            'by_source' => $bySource,
            'demos' => [
                'booked' => (int) ($demos['Demo'] ?? 0),
                'evaluating' => (int) ($demos['Evaluating'] ?? 0),
            ],
            'products' => $products,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function billingSector(Carbon $fromUtc, Carbon $toUtc, string $fromDate, string $toDate): array
    {
        $issued = Invoice::query()
            ->whereDate('issue_date', '>=', $fromDate)
            ->whereDate('issue_date', '<=', $toDate);

        $byStatus = Invoice::query()
            ->selectRaw('status, count(*) as rows, coalesce(sum(total), 0) as amount')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(function ($row): array {
                $status = $row->status instanceof InvoiceStatus
                    ? $row->status->value
                    : (string) $row->status;

                return [
                    $status => [
                        'rows' => (int) $row->rows,
                        'amount' => round((float) $row->amount, 2),
                    ],
                ];
            })
            ->all();

        $paidRevenue = round((float) Invoice::query()
            ->where('status', InvoiceStatus::Paid->value)
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$fromUtc, $toUtc])
            ->sum('total'), 2);

        $taxCollected = round((float) TaxLedgerEntry::query()
            ->whereDate('occurred_on', '>=', $fromDate)
            ->whereDate('occurred_on', '<=', $toDate)
            ->sum('tax_amount'), 2);

        $issuedCount = (clone $issued)->count();
        $issuedTotal = round((float) (clone $issued)->sum('total'), 2);

        return [
            'issued_count' => $issuedCount,
            'issued_total' => $issuedTotal,
            'paid_revenue' => $paidRevenue,
            'tax_collected' => $taxCollected,
            'draft_count' => (int) ($byStatus[InvoiceStatus::Draft->value]['rows'] ?? 0),
            'unpaid_count' => (int) ($byStatus[InvoiceStatus::Unpaid->value]['rows'] ?? 0)
                + (int) ($byStatus[InvoiceStatus::Sent->value]['rows'] ?? 0),
            'unpaid_total' => round(
                (float) ($byStatus[InvoiceStatus::Unpaid->value]['amount'] ?? 0)
                + (float) ($byStatus[InvoiceStatus::Sent->value]['amount'] ?? 0),
                2,
            ),
            'overdue_count' => (int) ($byStatus[InvoiceStatus::Overdue->value]['rows'] ?? 0),
            'overdue_total' => (float) ($byStatus[InvoiceStatus::Overdue->value]['amount'] ?? 0),
            'paid_count' => (int) ($byStatus[InvoiceStatus::Paid->value]['rows'] ?? 0),
            'by_status' => collect(InvoiceStatus::cases())->map(fn (InvoiceStatus $status): array => [
                'status' => $status->value,
                'rows' => (int) ($byStatus[$status->value]['rows'] ?? 0),
                'amount' => (float) ($byStatus[$status->value]['amount'] ?? 0),
            ])->all(),
            'currency' => $this->currency(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function subscriptionsSector(string $fromDate, string $toDate): array
    {
        $active = BillingSubscription::query()->where('status', 'active');
        $mrr = round((float) (clone $active)->where('cadence', 'monthly')->sum('amount'), 2);
        $dueSoon = BillingSubscription::query()
            ->where('status', 'active')
            ->get()
            ->filter(fn (BillingSubscription $sub): bool => $sub->isDueSoon())
            ->count();
        $overdue = BillingSubscription::query()
            ->where('status', 'active')
            ->get()
            ->filter(fn (BillingSubscription $sub): bool => $sub->isOverdue())
            ->count();

        $payments = DB::table('subscription_payments')
            ->whereDate('paid_on', '>=', $fromDate)
            ->whereDate('paid_on', '<=', $toDate);

        return [
            'active' => (clone $active)->count(),
            'paused' => BillingSubscription::query()->where('status', 'paused')->count(),
            'ended' => BillingSubscription::query()->where('status', 'ended')->count(),
            'mrr' => $mrr,
            'due_soon' => $dueSoon,
            'overdue' => $overdue,
            'payments_in_range' => (clone $payments)->count(),
            'collected_in_range' => round((float) (clone $payments)->sum('amount'), 2),
            'currency' => $this->currency(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function moneySector(): array
    {
        $accounts = MoneyAccount::query()->orderByDesc('is_active')->orderBy('name')->get();
        $debts = MoneyDebt::query()->get();
        $recurrings = MoneyRecurring::query()->where('status', 'active')->get();

        $cash = round((float) $accounts->where('is_active', true)->sum('balance'), 2);
        $iOwe = round((float) $debts
            ->where('direction', 'payable')
            ->where('status', '!=', 'settled')
            ->sum(fn (MoneyDebt $debt) => $debt->remaining()), 2);
        $owedToMe = round((float) $debts
            ->where('direction', 'receivable')
            ->where('status', '!=', 'settled')
            ->sum(fn (MoneyDebt $debt) => $debt->remaining()), 2);
        $monthlyOut = round((float) $recurrings->where('direction', 'outflow')->where('cadence', 'monthly')->sum('amount'), 2);
        $monthlyIn = round((float) $recurrings->where('direction', 'inflow')->where('cadence', 'monthly')->sum('amount'), 2);

        return [
            'cash' => $cash,
            'i_owe' => $iOwe,
            'owed_to_me' => $owedToMe,
            'net' => round($cash + $owedToMe - $iOwe, 2),
            'monthly_in' => $monthlyIn,
            'monthly_out' => $monthlyOut,
            'accounts' => $accounts->map(fn (MoneyAccount $account): array => [
                'name' => $account->name,
                'account_type' => $account->account_type,
                'balance' => (float) $account->balance,
                'is_active' => (bool) $account->is_active,
            ])->values()->all(),
            'currency' => $this->currency(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function messagingSector(Carbon $fromUtc, Carbon $toUtc): array
    {
        $base = Message::query()->whereBetween('sent_at', [$fromUtc, $toUtc]);
        $inbound = (clone $base)->where('direction', MessageDirection::Inbound->value)->count();
        $outbound = (clone $base)->where('direction', MessageDirection::Outbound->value)->count();
        $total = $inbound + $outbound;

        $byChannel = Message::query()
            ->whereBetween('sent_at', [$fromUtc, $toUtc])
            ->selectRaw("coalesce(channel, 'unknown') as channel_label, count(*) as total")
            ->groupBy('channel_label')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'channel' => (string) $row->channel_label,
                'total' => (int) $row->total,
            ])
            ->all();

        $activeContacts = (int) Message::query()
            ->whereBetween('sent_at', [$fromUtc, $toUtc])
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->join('leads', 'leads.id', '=', 'conversations.lead_id')
            ->selectRaw('count(distinct leads.contact_id) as aggregate')
            ->value('aggregate');

        return [
            'total' => $total,
            'inbound' => $inbound,
            'outbound' => $outbound,
            'reply_ratio' => $inbound > 0 ? round(($outbound / $inbound) * 100, 1) : 0.0,
            'active_contacts' => $activeContacts,
            'by_channel' => $byChannel,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function aiSector(Carbon $fromUtc, Carbon $toUtc): array
    {
        $inRange = LeadAnalysis::query()
            ->where(function ($query) use ($fromUtc, $toUtc): void {
                $query->whereBetween('completed_at', [$fromUtc, $toUtc])
                    ->orWhere(function ($created) use ($fromUtc, $toUtc): void {
                        $created->whereNull('completed_at')
                            ->whereBetween('created_at', [$fromUtc, $toUtc]);
                    });
            });

        $completed = (clone $inRange)->where('status', AnalysisStatus::Completed->value)->count();
        $failed = (clone $inRange)->where('status', AnalysisStatus::Failed->value)->count();
        $unavailable = (clone $inRange)->where('status', AnalysisStatus::Unavailable->value)->count();
        $queued = LeadAnalysis::query()->where('status', AnalysisStatus::Queued->value)->count();
        $avgScore = LeadAnalysis::query()
            ->where('status', AnalysisStatus::Completed->value)
            ->whereNotNull('score')
            ->whereBetween('completed_at', [$fromUtc, $toUtc])
            ->avg('score');

        $highScore = LeadAnalysis::query()
            ->where('status', AnalysisStatus::Completed->value)
            ->where('score', '>=', 70)
            ->whereBetween('completed_at', [$fromUtc, $toUtc])
            ->count();

        return [
            'completed' => $completed,
            'failed' => $failed,
            'unavailable' => $unavailable,
            'queued' => $queued,
            'avg_score' => $avgScore !== null ? round((float) $avgScore, 1) : null,
            'high_score' => $highScore,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function followUpSector(Carbon $fromUtc, Carbon $toUtc): array
    {
        $today = $this->clock->today();
        $tasks = FollowUpTask::query()
            ->where(function ($query) use ($fromUtc, $toUtc): void {
                $query->whereBetween('due_at', [$fromUtc, $toUtc])
                    ->orWhereBetween('executed_at', [$fromUtc, $toUtc]);
            });

        $byStatus = FollowUpTask::query()
            ->whereBetween('due_at', [$fromUtc, $toUtc])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $interactions = LeadInteraction::query()
            ->whereBetween('occurred_at', [$fromUtc, $toUtc])
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return [
            'tasks_in_range' => (clone $tasks)->count(),
            'by_status' => $byStatus->map(fn ($total, $status): array => [
                'status' => (string) $status,
                'total' => (int) $total,
            ])->values()->all(),
            'due_today' => Lead::query()
                ->whereNull('closed_at')
                ->whereDate('next_follow_up_on', $today)
                ->count(),
            'overdue' => Lead::query()
                ->whereNull('closed_at')
                ->whereNotNull('next_follow_up_on')
                ->whereDate('next_follow_up_on', '<', $today)
                ->count(),
            'scheduled_talks' => Lead::query()
                ->whereNull('closed_at')
                ->whereNotNull('next_conversation_at')
                ->where('conversations_stopped', false)
                ->whereBetween('next_conversation_at', [$fromUtc, $toUtc])
                ->count(),
            'interactions' => $interactions->map(fn ($total, $type): array => [
                'type' => (string) $type,
                'total' => (int) $total,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contactsSector(): array
    {
        $base = Contact::query()->whereNull('merged_into_contact_id');

        $byCountry = (clone $base)
            ->selectRaw("coalesce(nullif(country_code, ''), '??') as code, coalesce(nullif(country, ''), 'Unknown') as label, count(*) as total")
            ->groupBy('code', 'label')
            ->orderByDesc('total')
            ->limit(12)
            ->get()
            ->map(fn ($row): array => [
                'country_code' => (string) $row->code,
                'country' => (string) $row->label,
                'total' => (int) $row->total,
            ])
            ->all();

        $byArea = (clone $base)
            ->selectRaw("coalesce(nullif(trim(conversation_area), ''), 'Unspecified') as area, count(*) as total")
            ->groupBy('area')
            ->orderByDesc('total')
            ->limit(12)
            ->get()
            ->map(fn ($row): array => [
                'area' => (string) $row->area,
                'total' => (int) $row->total,
            ])
            ->all();

        $consent = (clone $base)
            ->selectRaw('consent_status, count(*) as total')
            ->groupBy('consent_status')
            ->pluck('total', 'consent_status');

        return [
            'total' => (clone $base)->count(),
            'by_country' => $byCountry,
            'by_area' => $byArea,
            'opted_in' => (int) ($consent['opted_in'] ?? 0),
            'opted_out' => (int) ($consent['opted_out'] ?? 0),
            'unknown_consent' => (int) ($consent['unknown'] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function integrationsSector(): array
    {
        $latest = IntegrationReport::query()
            ->with('account:id,provider,label')
            ->orderByDesc('fetched_at')
            ->limit(8)
            ->get();

        return [
            'reports' => $latest->map(fn (IntegrationReport $report): array => [
                'id' => $report->id,
                'provider' => $report->account?->provider ?? 'unknown',
                'label' => $report->account?->label,
                'report_type' => $report->report_type,
                'period_start' => $report->period_start?->toDateString(),
                'period_end' => $report->period_end?->toDateString(),
                'fetched_at' => $report->fetched_at?->toIso8601String(),
                'metrics' => is_array($report->metrics) ? $report->metrics : [],
            ])->all(),
        ];
    }
}
