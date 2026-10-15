<?php

namespace App\Leads\Services;

use App\Billing\Invoice;
use App\Billing\InvoiceStatus;
use App\Conversations\Conversation;
use App\Conversations\Message;
use App\Conversations\MessageChannel;
use App\Conversations\MessageDirection;
use App\Expenses\Expense;
use App\Leads\InteractionType;
use App\Leads\Lead;
use App\Leads\LeadInteraction;
use App\SalesPipelines\StageKind;
use App\Settings\DisplayClock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
        $days = $this->lastDays(7);

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

        $demoRequested = $this->countByStageName('Demo Requested');
        $demoScheduled = $this->countByStageName('Demo Scheduled');
        $demoCompleted = $this->countByStageName('Demo Completed');

        $paidRevenue = (float) Invoice::query()
            ->where('status', InvoiceStatus::Paid->value)
            ->sum('total');

        $expenseMonth = (float) Expense::query()
            ->whereBetween('spent_on', [
                Carbon::parse($today)->startOfMonth()->toDateString(),
                Carbon::parse($today)->endOfMonth()->toDateString(),
            ])
            ->sum(DB::raw('amount + tax_amount'));

        return [
            'total_leads' => Lead::query()->count(),
            'new_leads_today' => Lead::query()->whereBetween('created_at', [$start, $end])->count(),
            'follow_ups_due_today' => $this->followUps->apply(Lead::query(), 'due_today')->count(),
            'overdue_follow_ups' => $this->followUps->apply(Lead::query(), 'overdue')->count(),
            'won_count' => $this->countByKind(StageKind::Won),
            'lost_count' => $this->countByKind(StageKind::Lost),
            'open_leads' => Lead::query()
                ->whereHas('currentStage', fn ($q) => $q->where('kind', StageKind::Active->value))
                ->count(),
            'leads_by_stage' => $byStage,
            'follow_up_rows' => $this->followUpRows($today),
            'whatsapp' => $this->whatsappMetrics($start, $end, $days),
            'demos' => [
                'requested' => $demoRequested,
                'scheduled' => $demoScheduled,
                'completed' => $demoCompleted,
                'in_flight' => $demoRequested + $demoScheduled,
                'total' => $demoRequested + $demoScheduled + $demoCompleted,
            ],
            'stage_buckets' => $this->stageBuckets(
                $demoRequested,
                $demoScheduled,
                $demoCompleted,
            ),
            'interactions' => $this->interactionMetrics($start, $end),
            'scheduled_conversations' => Lead::query()
                ->whereNotNull('next_conversation_at')
                ->where('conversations_stopped', false)
                ->whereNull('closed_at')
                ->count(),
            'stopped_conversations' => Lead::query()
                ->where('conversations_stopped', true)
                ->whereNull('closed_at')
                ->count(),
            'orders' => [
                'bought_count' => Invoice::query()->where('status', InvoiceStatus::Paid->value)->count(),
                'bought_revenue' => $paidRevenue,
                'unpaid_count' => Invoice::query()->whereIn('status', [
                    InvoiceStatus::Sent->value,
                    InvoiceStatus::Unpaid->value,
                ])->count(),
                'overdue_count' => Invoice::query()->where('status', InvoiceStatus::Overdue->value)->count(),
                'draft_count' => Invoice::query()->where('status', InvoiceStatus::Draft->value)->count(),
                'currency' => Invoice::query()->value('currency') ?? 'KES',
            ],
            'spend' => [
                'month_total' => $expenseMonth,
            ],
            'charts' => [
                'leads_7d' => $this->leadsSeries($days),
                'whatsapp_7d' => $this->whatsappSeries($days),
                'bought_7d' => $this->boughtSeries($days),
                'funnel' => $byStage,
            ],
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

    private function countByStageName(string $name): int
    {
        return Lead::query()
            ->whereHas('currentStage', fn ($q) => $q->where('name', $name))
            ->count();
    }

    /**
     * @param  list<string>  $names
     */
    private function countByStageNames(array $names): int
    {
        return Lead::query()
            ->whereHas('currentStage', fn ($q) => $q->whereIn('name', $names))
            ->count();
    }

    /**
     * Sales-agent view of the funnel: follow-up work, almost-buying, meetings.
     *
     * @return array{
     *     follow_up: array{total: int, stages: list<string>},
     *     almost_buying: array{total: int, stages: list<string>},
     *     meetings: array{total: int, requested: int, scheduled: int, completed: int, stages: list<string>},
     *     nurture: array{total: int, stages: list<string>}
     * }
     */
    private function stageBuckets(int $demoRequested, int $demoScheduled, int $demoCompleted): array
    {
        $followUp = ['New Lead', 'Contacted', 'Qualified'];
        $almostBuying = ['Interested', 'Proposal / Price Sent', 'Negotiation'];
        $meetings = ['Demo Requested', 'Demo Scheduled', 'Demo Completed'];
        $nurture = ['Nurture / Future Opportunity'];

        return [
            'follow_up' => [
                'total' => $this->countByStageNames($followUp),
                'stages' => $followUp,
            ],
            'almost_buying' => [
                'total' => $this->countByStageNames($almostBuying),
                'stages' => $almostBuying,
            ],
            'meetings' => [
                'total' => $demoRequested + $demoScheduled + $demoCompleted,
                'requested' => $demoRequested,
                'scheduled' => $demoScheduled,
                'completed' => $demoCompleted,
                'stages' => $meetings,
            ],
            'nurture' => [
                'total' => $this->countByStageNames($nurture),
                'stages' => $nurture,
            ],
        ];
    }

    /**
     * @return array{today: int, phone_calls_today: int, meetings_today: int, notes_today: int}
     */
    private function interactionMetrics(mixed $start, mixed $end): array
    {
        $base = fn () => LeadInteraction::query()->whereBetween('occurred_at', [$start, $end]);

        return [
            'today' => $base()->count(),
            'phone_calls_today' => $base()->where('type', InteractionType::PhoneCall->value)->count(),
            'meetings_today' => $base()->where('type', InteractionType::Meeting->value)->count(),
            'notes_today' => $base()->where('type', InteractionType::Note->value)->count(),
        ];
    }

    /**
     * @return list<string>
     */
    private function lastDays(int $count): array
    {
        $today = Carbon::parse($this->clock->today(), $this->clock->timezone())->startOfDay();
        $days = [];

        for ($i = $count - 1; $i >= 0; $i--) {
            $days[] = $today->copy()->subDays($i)->toDateString();
        }

        return $days;
    }

    /**
     * @param  list<string>  $days
     * @return array<string, mixed>
     */
    private function whatsappMetrics(mixed $start, mixed $end, array $days): array
    {
        $inboundToday = Message::query()
            ->where('channel', MessageChannel::WhatsApp->value)
            ->where('direction', MessageDirection::Inbound->value)
            ->whereBetween('sent_at', [$start, $end])
            ->count();
        $outboundToday = Message::query()
            ->where('channel', MessageChannel::WhatsApp->value)
            ->where('direction', MessageDirection::Outbound->value)
            ->whereBetween('sent_at', [$start, $end])
            ->count();

        $windowStart = Carbon::parse($days[0], $this->clock->timezone())->startOfDay()->utc();
        $windowEnd = Carbon::parse($days[count($days) - 1], $this->clock->timezone())->endOfDay()->utc();

        return [
            'conversations' => Conversation::query()->count(),
            'messages_today' => $inboundToday + $outboundToday,
            'inbound_today' => $inboundToday,
            'outbound_today' => $outboundToday,
            'messages_7d' => Message::query()
                ->where('channel', MessageChannel::WhatsApp->value)
                ->whereBetween('sent_at', [$windowStart, $windowEnd])
                ->count(),
            'active_7d' => Conversation::query()
                ->whereHas('messages', function ($q) use ($windowStart, $windowEnd): void {
                    $q->where('channel', MessageChannel::WhatsApp->value)
                        ->whereBetween('sent_at', [$windowStart, $windowEnd]);
                })
                ->count(),
        ];
    }

    /**
     * @param  list<string>  $days
     * @return list<array{date: string, label: string, opened: int, won: int}>
     */
    private function leadsSeries(array $days): array
    {
        return array_map(function (string $day): array {
            [$start, $end] = $this->dayUtcRange($day);

            $opened = Lead::query()->whereBetween('created_at', [$start, $end])->count();
            $won = Lead::query()
                ->whereHas('currentStage', fn ($q) => $q->where('kind', StageKind::Won->value))
                ->whereBetween('closed_at', [$start, $end])
                ->count();

            return [
                'date' => $day,
                'label' => Carbon::parse($day)->format('D'),
                'opened' => $opened,
                'won' => $won,
            ];
        }, $days);
    }

    /**
     * @param  list<string>  $days
     * @return list<array{date: string, label: string, inbound: int, outbound: int}>
     */
    private function whatsappSeries(array $days): array
    {
        return array_map(function (string $day): array {
            [$start, $end] = $this->dayUtcRange($day);

            $inbound = Message::query()
                ->where('channel', MessageChannel::WhatsApp->value)
                ->where('direction', MessageDirection::Inbound->value)
                ->whereBetween('sent_at', [$start, $end])
                ->count();
            $outbound = Message::query()
                ->where('channel', MessageChannel::WhatsApp->value)
                ->where('direction', MessageDirection::Outbound->value)
                ->whereBetween('sent_at', [$start, $end])
                ->count();

            return [
                'date' => $day,
                'label' => Carbon::parse($day)->format('D'),
                'inbound' => $inbound,
                'outbound' => $outbound,
            ];
        }, $days);
    }

    /**
     * @param  list<string>  $days
     * @return list<array{date: string, label: string, count: int, revenue: float}>
     */
    private function boughtSeries(array $days): array
    {
        return array_map(function (string $day): array {
            [$start, $end] = $this->dayUtcRange($day);

            $base = Invoice::query()
                ->where('status', InvoiceStatus::Paid->value)
                ->whereBetween('paid_at', [$start, $end]);

            return [
                'date' => $day,
                'label' => Carbon::parse($day)->format('D'),
                'count' => (int) (clone $base)->count(),
                'revenue' => (float) (clone $base)->sum('total'),
            ];
        }, $days);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function dayUtcRange(string $day): array
    {
        $start = Carbon::parse($day, $this->clock->timezone())->startOfDay()->utc();
        $end = Carbon::parse($day, $this->clock->timezone())->endOfDay()->utc();

        return [$start, $end];
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
