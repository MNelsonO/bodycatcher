import { Form, Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { SelectField, TextField } from '@/components/crm/fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { ChartDay, DashboardMetrics, FollowUpRow } from '@/types/crm';

function speakToday(metrics: DashboardMetrics): {
    line: string;
    tone: 'red' | 'blue' | 'green' | 'lead';
} {
    if (metrics.overdue_follow_ups > 0) {
        return {
            line: `${metrics.overdue_follow_ups} overdue — clear these first.`,
            tone: 'red',
        };
    }
    if (metrics.orders.overdue_count > 0) {
        return {
            line: `${metrics.orders.overdue_count} overdue invoices need collection.`,
            tone: 'red',
        };
    }
    if (metrics.follow_ups_due_today > 0) {
        return {
            line: `${metrics.follow_ups_due_today} follow-ups due today. Work the list.`,
            tone: 'blue',
        };
    }
    if (metrics.whatsapp.inbound_today > 0) {
        return {
            line: `${metrics.whatsapp.inbound_today} WhatsApp inbound today. Reply and qualify.`,
            tone: 'blue',
        };
    }
    if (metrics.demos.in_flight > 0) {
        return {
            line: `${metrics.demos.in_flight} demos in flight. Keep them moving.`,
            tone: 'blue',
        };
    }
    if (metrics.orders.bought_count > 0) {
        return {
            line: `${metrics.orders.bought_count} bought · ${money(metrics.orders.currency, metrics.orders.bought_revenue)} collected.`,
            tone: 'green',
        };
    }
    if (metrics.total_leads === 0) {
        return {
            line: 'No leads yet. Open one or wait for WhatsApp inbound.',
            tone: 'lead',
        };
    }
    return {
        line: 'Nothing urgent. Keep pipeline and demos moving.',
        tone: 'lead',
    };
}

/* Solid fills only: no alpha backgrounds */
const fill: Record<string, string> = {
    red: 'bg-destructive text-white',
    blue: 'bg-primary text-primary-foreground',
    green: 'bg-success text-success-foreground',
    lead: 'bg-lead text-lead-foreground',
    white: 'bg-card text-primary border border-primary',
};

/* One shared white card surface */
const surface =
    'overflow-hidden rounded-xl border border-border bg-card shadow-[var(--shadow-card)]';

const sectionHead = 'border-b border-border bg-card px-5 py-4';

function money(currency: string, amount: number): string {
    return `${currency} ${amount.toLocaleString(undefined, { maximumFractionDigits: 0 })}`;
}

export default function Dashboard({ metrics }: { metrics: DashboardMetrics }) {
    const speak = speakToday(metrics);
    const stageMax = Math.max(
        1,
        ...metrics.leads_by_stage.map((row) => row.total),
    );
    const board = metrics.board;
    const chartDays = metrics.charts.days ?? 7;

    return (
        <>
            <Head title="Command centre" />
            <div className="mx-auto flex w-full max-w-[90rem] flex-col gap-6 p-4 md:gap-8 md:p-8">
                {/* Hero: the one bold blue panel */}
                <section
                    className={`rounded-xl px-6 py-8 shadow-[var(--shadow-pop)] md:px-10 md:py-11 ${fill[speak.tone]}`}
                >
                    <p className="text-sm font-medium opacity-90">
                        Kemet · Laundry desk
                    </p>
                    <h1 className="mt-3 max-w-3xl text-3xl font-semibold tracking-tight md:text-5xl">
                        Command centre
                    </h1>
                    <p className="mt-4 max-w-3xl text-lg leading-snug font-medium md:text-2xl">
                        {speak.line}
                    </p>
                    <p className="mt-4 text-sm opacity-90">
                        {metrics.today} · {metrics.timezone} · live desk metrics
                    </p>
                </section>

                {/* Desk filters */}
                <section className={surface}>
                    <div className={sectionHead}>
                        <h2 className="text-lg font-semibold tracking-tight text-foreground">
                            Desk filters
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Slice by status, stage, hot leads, follow-ups, and
                            timeline. Board below updates with the filter.
                        </p>
                    </div>
                    <Form
                        action="/dashboard"
                        method="get"
                        className="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-4"
                    >
                        {({ processing }) => (
                            <>
                                <TextField
                                    name="q"
                                    label="Search name / intent"
                                    defaultValue={board.filters.q}
                                />
                                <SelectField
                                    name="kind"
                                    label="Status"
                                    options={board.options.kinds}
                                    defaultValue={
                                        board.filters.kind !== ''
                                            ? board.filters.kind
                                            : '__all__'
                                    }
                                />
                                <SelectField
                                    name="stage_id"
                                    label="Stage"
                                    options={[
                                        {
                                            value: '__all__',
                                            label: 'All stages',
                                        },
                                        ...board.options.stages,
                                    ]}
                                    defaultValue={
                                        board.filters.stage_id !== ''
                                            ? board.filters.stage_id
                                            : '__all__'
                                    }
                                />
                                <SelectField
                                    name="product_id"
                                    label="Product / system"
                                    options={[
                                        {
                                            value: '__all__',
                                            label: 'All systems',
                                        },
                                        ...board.options.products,
                                    ]}
                                    defaultValue={
                                        board.filters.product_id !== ''
                                            ? board.filters.product_id
                                            : '__all__'
                                    }
                                />
                                <SelectField
                                    name="follow_up"
                                    label="Follow-up"
                                    options={board.options.follow_ups}
                                    defaultValue={
                                        board.filters.follow_up !== ''
                                            ? board.filters.follow_up
                                            : '__all__'
                                    }
                                />
                                <SelectField
                                    name="timeline"
                                    label="Timeline"
                                    options={board.options.timelines}
                                    defaultValue={
                                        board.filters.timeline !== ''
                                            ? board.filters.timeline
                                            : '__all__'
                                    }
                                />
                                <TextField
                                    name="from"
                                    label="From date"
                                    type="date"
                                    defaultValue={board.filters.from}
                                />
                                <TextField
                                    name="to"
                                    label="To date"
                                    type="date"
                                    defaultValue={board.filters.to}
                                />
                                <div className="flex flex-wrap items-end gap-3 xl:col-span-4">
                                    <label className="inline-flex items-center gap-2 text-sm font-medium">
                                        <input
                                            type="checkbox"
                                            name="hot"
                                            value="1"
                                            defaultChecked={board.filters.hot}
                                            className="size-4 rounded border-input accent-primary"
                                        />
                                        Hot only (high-interest stage or AI ≥
                                        70)
                                    </label>
                                    <div className="ml-auto flex gap-2">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Apply filters
                                        </Button>
                                        <Button variant="outline" asChild>
                                            <Link href="/dashboard">Clear</Link>
                                        </Button>
                                    </div>
                                </div>
                            </>
                        )}
                    </Form>
                    <div className="grid grid-cols-2 gap-3 border-t border-border bg-muted p-5 sm:grid-cols-5">
                        <MiniStat
                            label="Matched"
                            value={String(board.summary.matched)}
                        />
                        <MiniStat
                            label="Open"
                            value={String(board.summary.open)}
                        />
                        <MiniStat
                            label="Hot"
                            value={String(board.summary.hot)}
                        />
                        <MiniStat
                            label="Won"
                            value={String(board.summary.won)}
                        />
                        <MiniStat
                            label="Lost"
                            value={String(board.summary.lost)}
                        />
                    </div>
                    <p className="border-t border-border px-5 py-3 text-xs text-muted-foreground">
                        Timeline: {board.range.label}
                        {board.range.from
                            ? ` · ${board.range.from} → ${board.range.to}`
                            : ''}
                    </p>
                </section>

                {/* Filtered lead board */}
                <section className={surface}>
                    <div className={sectionHead}>
                        <h2 className="text-lg font-semibold tracking-tight text-foreground">
                            Filtered lead board
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Up to 40 leads matching the desk filters.
                        </p>
                    </div>
                    {board.leads.length === 0 ? (
                        <p className="px-5 py-10 text-sm text-muted-foreground">
                            No leads match these filters. Widen status, stage,
                            or timeline.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[880px] text-left text-sm">
                                <thead className="border-b border-border bg-secondary text-xs font-semibold text-secondary-foreground">
                                    <tr>
                                        <th className="px-5 py-3">Lead</th>
                                        <th className="px-5 py-3">System</th>
                                        <th className="px-5 py-3">Stage</th>
                                        <th className="px-5 py-3">Intent</th>
                                        <th className="px-5 py-3">Follow-up</th>
                                        <th className="px-5 py-3">Next talk</th>
                                        <th className="px-5 py-3">Score</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {board.leads.map((lead) => (
                                        <tr
                                            key={lead.id}
                                            className="bg-card hover:bg-muted"
                                        >
                                            <td className="px-5 py-4 align-top">
                                                <Link
                                                    href={`/leads/${lead.id}`}
                                                    className="font-semibold text-primary hover:underline"
                                                >
                                                    {lead.contact_name}
                                                </Link>
                                                <div className="mt-1.5 flex flex-wrap gap-1">
                                                    {lead.hot ? (
                                                        <Badge>Hot</Badge>
                                                    ) : null}
                                                    <Badge variant="outline">
                                                        {lead.stage_kind}
                                                    </Badge>
                                                </div>
                                                {lead.business_name ? (
                                                    <p className="mt-1.5 text-xs text-muted-foreground">
                                                        {lead.business_name}
                                                    </p>
                                                ) : null}
                                            </td>
                                            <td className="px-5 py-4 align-top">
                                                {lead.product_name}
                                            </td>
                                            <td className="px-5 py-4 align-top">
                                                {lead.stage_name}
                                            </td>
                                            <td className="max-w-xs px-5 py-4 align-top">
                                                <p className="line-clamp-2">
                                                    {lead.intent ??
                                                        lead.next_action ?? (
                                                            <span className="text-muted-foreground">
                                                                —
                                                            </span>
                                                        )}
                                                </p>
                                            </td>
                                            <td className="px-5 py-4 align-top tabular-nums text-muted-foreground">
                                                {lead.follow_up_label ?? '—'}
                                                {lead.follow_up_rank ? (
                                                    <span className="mt-1 block text-xs font-semibold text-foreground">
                                                        Rank {lead.follow_up_rank}
                                                        {lead.follow_up_source ===
                                                        'chat'
                                                            ? ' · chat'
                                                            : ''}
                                                    </span>
                                                ) : null}
                                            </td>
                                            <td className="px-5 py-4 align-top tabular-nums text-muted-foreground">
                                                {lead.next_talk_label ?? '—'}
                                            </td>
                                            <td className="px-5 py-4 align-top font-medium tabular-nums">
                                                {lead.ai_score ?? '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                {/* Action signals */}
                <section className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <SolidCard
                        href="/leads?follow_up=overdue"
                        label="Overdue"
                        value={metrics.overdue_follow_ups}
                        tone="red"
                        pulse={metrics.overdue_follow_ups > 0}
                    />
                    <SolidCard
                        href="/leads?follow_up=due_today"
                        label="Due today"
                        value={metrics.follow_ups_due_today}
                        tone="blue"
                    />
                    <SolidCard
                        href="/leads?created=today"
                        label="New leads"
                        value={metrics.new_leads_today}
                        tone="white"
                    />
                    <SolidCard
                        href="/leads?kind=won"
                        label="Won"
                        value={metrics.won_count}
                        tone="green"
                    />
                </section>

                {/* Stage buckets for the automated sales agent */}
                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <MetricBlock
                        title="Follow-up stages"
                        href="/leads"
                        tone="blue"
                        rows={[
                            ['In stage', metrics.stage_buckets.follow_up.total],
                            ['Due today', metrics.follow_ups_due_today],
                            ['Overdue', metrics.overdue_follow_ups],
                            [
                                'Scheduled talks',
                                metrics.scheduled_conversations,
                            ],
                            ['Stopped', metrics.stopped_conversations],
                        ]}
                    />
                    <MetricBlock
                        title="Almost buying"
                        href="/leads"
                        tone="lead"
                        rows={[
                            [
                                'In stage',
                                metrics.stage_buckets.almost_buying.total,
                            ],
                            ...metrics.stage_buckets.almost_buying.stages.map(
                                (name) => {
                                    const row = metrics.leads_by_stage.find(
                                        (item) => item.stage_name === name,
                                    );
                                    return [name, row?.total ?? 0] as [
                                        string,
                                        number,
                                    ];
                                },
                            ),
                        ]}
                    />
                    <MetricBlock
                        title="Meetings / demos"
                        href="/calendar"
                        tone="blue"
                        rows={[
                            [
                                'All demo stages',
                                metrics.stage_buckets.meetings.total,
                            ],
                            [
                                'Demo booked',
                                metrics.stage_buckets.meetings.booked,
                            ],
                            [
                                'Evaluating',
                                metrics.stage_buckets.meetings.evaluating,
                            ],
                            ['Nurture', metrics.stage_buckets.nurture.total],
                        ]}
                    />
                    <MetricBlock
                        title="Touches today"
                        href="/leads"
                        tone="green"
                        rows={[
                            ['All touches', metrics.interactions.today],
                            [
                                'Phone calls',
                                metrics.interactions.phone_calls_today,
                            ],
                            ['Meetings', metrics.interactions.meetings_today],
                            ['Notes', metrics.interactions.notes_today],
                            ['Bought (paid)', metrics.orders.bought_count],
                        ]}
                    />
                </section>

                {/* WhatsApp + Demos + Bought + Pipeline counts */}
                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <MetricBlock
                        title="WhatsApp"
                        href="/whatsapp"
                        tone="blue"
                        rows={[
                            ['Conversations', metrics.whatsapp.conversations],
                            ['Active 7d', metrics.whatsapp.active_7d],
                            ['Msgs today', metrics.whatsapp.messages_today],
                            [
                                'In / out today',
                                `${metrics.whatsapp.inbound_today} / ${metrics.whatsapp.outbound_today}`,
                            ],
                            ['Msgs 7d', metrics.whatsapp.messages_7d],
                        ]}
                    />
                    <MetricBlock
                        title="Demos"
                        href="/leads"
                        tone="lead"
                        rows={[
                            ['In flight', metrics.demos.in_flight],
                            ['Demo booked', metrics.demos.booked],
                            ['Evaluating', metrics.demos.evaluating],
                            ['All demo stages', metrics.demos.total],
                        ]}
                    />
                    <MetricBlock
                        title="Bought / orders"
                        href="/invoices"
                        tone="green"
                        rows={[
                            ['Bought (paid)', metrics.orders.bought_count],
                            [
                                'Revenue',
                                money(
                                    metrics.orders.currency,
                                    metrics.orders.bought_revenue,
                                ),
                            ],
                            ['Unpaid', metrics.orders.unpaid_count],
                            ['Overdue invoices', metrics.orders.overdue_count],
                            ['Drafts', metrics.orders.draft_count],
                        ]}
                    />
                    <MetricBlock
                        title="Leads board"
                        href="/leads"
                        tone="blue"
                        rows={[
                            ['Open', metrics.open_leads],
                            ['Total', metrics.total_leads],
                            ['Won', metrics.won_count],
                            ['Lost', metrics.lost_count],
                            [
                                'Spend MTD',
                                money(
                                    metrics.orders.currency,
                                    metrics.spend.month_total,
                                ),
                            ],
                        ]}
                    />
                </section>

                {/* Charts */}
                <section className="grid gap-4 lg:grid-cols-3">
                    <ChartCard
                        title={`Leads · ${chartDays} days`}
                        subtitle="Opened vs won"
                        href="/leads"
                    >
                        <TwinBarChart
                            data={metrics.charts.leads_7d}
                            aKey="opened"
                            bKey="won"
                            aColor="var(--laundry-blue)"
                            bColor="var(--success)"
                            aLabel="Opened"
                            bLabel="Won"
                        />
                    </ChartCard>
                    <ChartCard
                        title={`WhatsApp · ${chartDays} days`}
                        subtitle="Inbound vs outbound"
                        href="/whatsapp"
                    >
                        <TwinBarChart
                            data={metrics.charts.whatsapp_7d}
                            aKey="inbound"
                            bKey="outbound"
                            aColor="var(--laundry-blue)"
                            bColor="var(--lead)"
                            aLabel="In"
                            bLabel="Out"
                        />
                    </ChartCard>
                    <ChartCard
                        title={`Bought · ${chartDays} days`}
                        subtitle="Paid invoices"
                        href="/invoices?status=paid"
                    >
                        <SingleBarChart
                            data={metrics.charts.bought_7d}
                            valueKey="count"
                            color="var(--success)"
                            labelKey="label"
                        />
                    </ChartCard>
                </section>

                {/* Funnel + next moves */}
                <section className="grid gap-4 lg:grid-cols-5">
                    <div className={`${surface} p-5 lg:col-span-2`}>
                        <div className="mb-5">
                            <h2 className="text-lg font-semibold tracking-tight text-foreground">
                                Funnel
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Bar length shows leads per stage. Click a stage
                                to filter leads.
                            </p>
                        </div>
                        {metrics.leads_by_stage.length === 0 ? (
                            <p className="rounded-md border border-border bg-lead-soft px-4 py-3 text-sm text-lead">
                                No leads yet.
                            </p>
                        ) : (
                            <ul className="space-y-4">
                                {metrics.leads_by_stage.map((row) => {
                                    const width = Math.max(
                                        6,
                                        Math.round(
                                            (row.total / stageMax) * 100,
                                        ),
                                    );
                                    const bar =
                                        row.kind === 'won'
                                            ? 'bg-success'
                                            : row.kind === 'lost'
                                              ? 'bg-destructive'
                                              : row.stage_name
                                                      .toLowerCase()
                                                      .includes('demo')
                                                ? 'bg-lead'
                                                : 'bg-primary';

                                    return (
                                        <li key={row.stage_id}>
                                            <Link
                                                href={`/dashboard?stage_id=${row.stage_id}`}
                                                className="block rounded-md outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            >
                                                <div className="mb-1.5 flex items-baseline justify-between gap-3 text-sm">
                                                    <span className="font-medium">
                                                        {row.stage_name}
                                                    </span>
                                                    <span className="font-semibold tabular-nums">
                                                        {row.total}
                                                    </span>
                                                </div>
                                                <div className="h-3 w-full overflow-hidden rounded-full bg-lead-soft">
                                                    <div
                                                        className={`h-full rounded-full ${bar}`}
                                                        style={{
                                                            width: `${width}%`,
                                                        }}
                                                    />
                                                </div>
                                            </Link>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </div>

                    <div className={`${surface} p-5 lg:col-span-3`}>
                        <div className="mb-5">
                            <h2 className="text-lg font-semibold tracking-tight text-foreground">
                                Ranked follow-ups
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Highest rank first. Chat dates, due/overdue, and
                                talks soon.
                            </p>
                        </div>
                        {metrics.follow_up_rows.length === 0 ? (
                            <p className="rounded-md border border-border bg-success-soft px-4 py-3 text-sm text-success">
                                Nothing due and nothing overdue.
                            </p>
                        ) : (
                            <ul className="space-y-3">
                                {metrics.follow_up_rows.map((row) => (
                                    <FollowUpItem key={row.id} row={row} />
                                ))}
                            </ul>
                        )}
                    </div>
                </section>
            </div>
        </>
    );
}

function MiniStat({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border border-border bg-card px-4 py-3">
            <p className="text-xs font-medium text-muted-foreground">
                {label}
            </p>
            <p className="mt-1 text-xl font-semibold tabular-nums">{value}</p>
        </div>
    );
}

function SolidCard({
    href,
    label,
    value,
    tone,
    pulse = false,
}: {
    href: string;
    label: string;
    value: number;
    tone: keyof typeof fill;
    pulse?: boolean;
}) {
    return (
        <Link
            href={href}
            className={`block rounded-xl px-5 py-6 shadow-[var(--shadow-card)] outline-none transition-colors hover:brightness-110 focus-visible:ring-2 focus-visible:ring-ring md:px-6 md:py-7 ${fill[tone]} ${
                pulse ? 'ring-2 ring-destructive ring-offset-2' : ''
            }`}
        >
            <p className="text-sm font-medium opacity-90">{label}</p>
            <p className="mt-2 text-4xl font-semibold tracking-tight tabular-nums md:text-5xl">
                {value}
            </p>
        </Link>
    );
}

function MetricBlock({
    title,
    href,
    tone,
    rows,
}: {
    title: string;
    href: string;
    tone: 'blue' | 'green' | 'lead' | 'red';
    rows: Array<[string, string | number]>;
}) {
    const head = fill[tone];

    return (
        <div className={surface}>
            <Link
                href={href}
                className={`block px-5 py-3.5 ${head} outline-none hover:brightness-110 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset`}
            >
                <p className="text-sm font-semibold">{title}</p>
            </Link>
            <dl className="divide-y divide-border">
                {rows.map(([label, value]) => (
                    <div
                        key={label}
                        className="flex items-center justify-between gap-3 bg-card px-5 py-3 text-sm"
                    >
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd className="font-semibold tabular-nums text-foreground">
                            {value}
                        </dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}

function ChartCard({
    title,
    subtitle,
    href,
    children,
}: {
    title: string;
    subtitle: string;
    href: string;
    children: ReactNode;
}) {
    return (
        <div className={surface}>
            <div
                className={`flex items-end justify-between gap-3 ${sectionHead}`}
            >
                <div>
                    <h2 className="font-semibold text-foreground">{title}</h2>
                    <p className="text-xs text-muted-foreground">{subtitle}</p>
                </div>
                <Link
                    href={href}
                    className="text-sm font-semibold text-primary hover:underline"
                >
                    Open
                </Link>
            </div>
            <div className="bg-card px-4 py-5">{children}</div>
        </div>
    );
}

function TwinBarChart({
    data,
    aKey,
    bKey,
    aColor,
    bColor,
    aLabel,
    bLabel,
}: {
    data: ChartDay[];
    aKey: keyof ChartDay;
    bKey: keyof ChartDay;
    aColor: string;
    bColor: string;
    aLabel: string;
    bLabel: string;
}) {
    const max = Math.max(
        1,
        ...data.map((d) =>
            Math.max(Number(d[aKey] ?? 0), Number(d[bKey] ?? 0)),
        ),
    );

    return (
        <div>
            <div className="mb-4 flex gap-4 text-xs font-medium text-muted-foreground">
                <span className="inline-flex items-center gap-1.5">
                    <span
                        className="inline-block size-2.5 rounded-sm"
                        style={{ background: aColor }}
                    />
                    {aLabel}
                </span>
                <span className="inline-flex items-center gap-1.5">
                    <span
                        className="inline-block size-2.5 rounded-sm"
                        style={{ background: bColor }}
                    />
                    {bLabel}
                </span>
            </div>
            <div className="flex h-36 items-end gap-2">
                {data.map((day) => {
                    const a = Number(day[aKey] ?? 0);
                    const b = Number(day[bKey] ?? 0);
                    return (
                        <div
                            key={day.date}
                            className="flex flex-1 flex-col items-center gap-1.5"
                        >
                            <div className="flex h-28 w-full items-end justify-center gap-0.5">
                                <div
                                    className="min-h-0.5 w-[42%] rounded-t-sm"
                                    style={{
                                        height: `${(a / max) * 100}%`,
                                        background: aColor,
                                    }}
                                    title={`${aLabel}: ${a}`}
                                />
                                <div
                                    className="min-h-0.5 w-[42%] rounded-t-sm"
                                    style={{
                                        height: `${(b / max) * 100}%`,
                                        background: bColor,
                                    }}
                                    title={`${bLabel}: ${b}`}
                                />
                            </div>
                            <span className="text-xs text-muted-foreground">
                                {day.label}
                            </span>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

function SingleBarChart({
    data,
    valueKey,
    color,
}: {
    data: ChartDay[];
    valueKey: keyof ChartDay;
    color: string;
    labelKey?: string;
}) {
    const max = Math.max(1, ...data.map((d) => Number(d[valueKey] ?? 0)));

    return (
        <div className="flex h-36 items-end gap-2">
            {data.map((day) => {
                const value = Number(day[valueKey] ?? 0);
                return (
                    <div
                        key={day.date}
                        className="flex flex-1 flex-col items-center gap-1.5"
                    >
                        <div className="flex h-28 w-full items-end">
                            <div
                                className="min-h-0.5 w-full rounded-t-sm"
                                style={{
                                    height: `${(value / max) * 100}%`,
                                    background: color,
                                }}
                                title={`${value}`}
                            />
                        </div>
                        <span className="text-xs text-muted-foreground">
                            {day.label}
                        </span>
                    </div>
                );
            })}
        </div>
    );
}

function FollowUpItem({ row }: { row: FollowUpRow }) {
    const overdue =
        row.status === 'overdue' ||
        row.reason.toLowerCase().includes('overdue');
    const fromChat = row.source === 'chat';
    const rail = overdue
        ? 'border-l-destructive'
        : fromChat
          ? 'border-l-lead'
          : 'border-l-primary';
    const tag = overdue
        ? 'bg-destructive text-white'
        : fromChat
          ? 'bg-lead text-lead-foreground'
          : 'bg-primary text-primary-foreground';

    return (
        <li
            className={`overflow-hidden rounded-lg border border-border border-l-4 bg-card ${rail}`}
        >
            <Link
                href={`/leads/${row.id}`}
                className="block px-4 py-3.5 transition-colors hover:bg-muted"
            >
                <div className="flex flex-wrap items-center gap-2">
                    {typeof row.rank === 'number' && row.rank > 0 ? (
                        <span className="rounded-full bg-muted px-2.5 py-0.5 text-xs font-semibold tabular-nums text-foreground">
                            #{row.rank}
                        </span>
                    ) : null}
                    <span
                        className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${tag}`}
                    >
                        {row.reason}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {row.follow_up_label}
                        {row.next_talk_label
                            ? ` · talk ${row.next_talk_label}`
                            : ''}
                    </span>
                </div>
                <p
                    className={`mt-2 font-semibold ${
                        overdue ? 'text-destructive' : 'text-primary'
                    }`}
                >
                    {row.contact_name}
                    {row.business_name ? ` · ${row.business_name}` : ''}
                </p>
                <p className="mt-0.5 text-sm text-muted-foreground">
                    {row.stage_name} · {row.product_name}
                </p>
                <p className="mt-1.5 text-sm">
                    {row.next_action ?? 'No next action written.'}
                </p>
            </Link>
        </li>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Command centre', href: '/dashboard' }],
};