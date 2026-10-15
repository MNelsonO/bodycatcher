import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
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

const fill: Record<string, string> = {
    red: 'bg-destructive text-white',
    blue: 'bg-primary text-primary-foreground',
    green: 'bg-success text-success-foreground',
    lead: 'bg-lead text-lead-foreground',
    white: 'bg-laundry-white text-primary border-2 border-primary',
};

function money(currency: string, amount: number): string {
    return `${currency} ${amount.toLocaleString(undefined, { maximumFractionDigits: 0 })}`;
}

export default function Dashboard({ metrics }: { metrics: DashboardMetrics }) {
    const speak = speakToday(metrics);
    const stageMax = Math.max(
        1,
        ...metrics.leads_by_stage.map((row) => row.total),
    );

    return (
        <>
            <Head title="Command centre" />
            <div className="flex flex-col gap-6 p-4 md:gap-8 md:p-6">
                <section
                    className={`relative overflow-hidden px-5 py-8 md:px-8 md:py-10 ${fill[speak.tone]}`}
                >
                    <p className="text-xs font-semibold tracking-[0.2em] uppercase opacity-80">
                        Kemet · Laundry desk
                    </p>
                    <h1 className="mt-3 max-w-3xl text-3xl font-semibold tracking-tight md:text-5xl">
                        Command centre
                    </h1>
                    <p className="mt-4 max-w-3xl text-lg font-medium md:text-2xl">
                        {speak.line}
                    </p>
                    <p className="mt-3 text-sm opacity-75">
                        {metrics.today} · {metrics.timezone} · live desk metrics
                    </p>
                </section>

                {/* Action signals */}
                <section className="grid grid-cols-2 gap-3 lg:grid-cols-4">
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
                <section className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <MetricBlock
                        title="Follow-up stages"
                        href="/leads"
                        tone="blue"
                        rows={[
                            ['In stage', metrics.stage_buckets.follow_up.total],
                            [
                                'Due today',
                                metrics.follow_ups_due_today,
                            ],
                            ['Overdue', metrics.overdue_follow_ups],
                            [
                                'Scheduled talks',
                                metrics.scheduled_conversations,
                            ],
                            [
                                'Stopped',
                                metrics.stopped_conversations,
                            ],
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
                                'All meeting stages',
                                metrics.stage_buckets.meetings.total,
                            ],
                            [
                                'Requested',
                                metrics.stage_buckets.meetings.requested,
                            ],
                            [
                                'Scheduled',
                                metrics.stage_buckets.meetings.scheduled,
                            ],
                            [
                                'Completed',
                                metrics.stage_buckets.meetings.completed,
                            ],
                            [
                                'Nurture',
                                metrics.stage_buckets.nurture.total,
                            ],
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
                            [
                                'Meetings',
                                metrics.interactions.meetings_today,
                            ],
                            ['Notes', metrics.interactions.notes_today],
                            [
                                'Bought (paid)',
                                metrics.orders.bought_count,
                            ],
                        ]}
                    />
                </section>

                {/* WhatsApp + Demos + Bought + Pipeline counts */}
                <section className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
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
                            ['Requested', metrics.demos.requested],
                            ['Scheduled', metrics.demos.scheduled],
                            ['Completed', metrics.demos.completed],
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
                <section className="grid gap-3 lg:grid-cols-3">
                    <ChartCard
                        title="Leads · 7 days"
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
                        title="WhatsApp · 7 days"
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
                        title="Bought · 7 days"
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
                <section className="grid gap-6 lg:grid-cols-5">
                    <div className="space-y-4 lg:col-span-2">
                        <div>
                            <h2 className="text-lg font-semibold tracking-tight text-primary">
                                Funnel
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Solid stage weight. Click to filter leads.
                            </p>
                        </div>
                        {metrics.leads_by_stage.length === 0 ? (
                            <p className="border-l-4 border-lead py-3 pl-4 text-sm text-lead">
                                No leads yet.
                            </p>
                        ) : (
                            <ul className="space-y-3">
                                {metrics.leads_by_stage.map((row) => {
                                    const width = Math.max(
                                        6,
                                        Math.round((row.total / stageMax) * 100),
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
                                                href={`/leads?stage_id=${row.stage_id}`}
                                                className="block outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            >
                                                <div className="mb-1 flex items-baseline justify-between gap-3 text-sm">
                                                    <span className="font-medium">
                                                        {row.stage_name}
                                                    </span>
                                                    <span className="tabular-nums font-semibold">
                                                        {row.total}
                                                    </span>
                                                </div>
                                                <div className="h-3 w-full bg-lead-soft">
                                                    <div
                                                        className={`h-full ${bar}`}
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

                    <div className="space-y-4 lg:col-span-3">
                        <div>
                            <h2 className="text-lg font-semibold tracking-tight text-primary">
                                Next moves
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Overdue red. Due today blue.
                            </p>
                        </div>
                        {metrics.follow_up_rows.length === 0 ? (
                            <p className="border-l-4 border-success py-3 pl-4 text-sm text-success">
                                Nothing due and nothing overdue.
                            </p>
                        ) : (
                            <ul className="space-y-2">
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
            className={`block px-4 py-6 outline-none transition hover:brightness-110 focus-visible:ring-2 focus-visible:ring-ring md:px-5 md:py-7 ${fill[tone]} ${
                pulse ? 'animate-[pulse_2.4s_ease-in-out_infinite]' : ''
            }`}
        >
            <p className="text-xs font-semibold tracking-[0.14em] uppercase opacity-80">
                {label}
            </p>
            <p className="mt-2 text-4xl font-semibold tabular-nums tracking-tight md:text-5xl">
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
        <div className="overflow-hidden bg-laundry-white shadow-[0_0_0_2px_var(--laundry-blue)]">
            <Link
                href={href}
                className={`block px-4 py-3 ${head} outline-none hover:brightness-110`}
            >
                <p className="text-xs font-semibold tracking-[0.16em] uppercase opacity-90">
                    {title}
                </p>
            </Link>
            <dl className="divide-y divide-primary/10">
                {rows.map(([label, value]) => (
                    <div
                        key={label}
                        className="flex items-center justify-between gap-3 px-4 py-2.5 text-sm"
                    >
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd className="font-semibold tabular-nums text-laundry-ink">
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
        <div className="bg-laundry-white shadow-[0_0_0_2px_var(--laundry-blue)]">
            <div className="flex items-end justify-between gap-3 border-b border-primary/10 px-4 py-3">
                <div>
                    <h2 className="font-semibold text-primary">{title}</h2>
                    <p className="text-xs text-muted-foreground">{subtitle}</p>
                </div>
                <Link
                    href={href}
                    className="text-xs font-semibold uppercase tracking-wide text-primary hover:underline"
                >
                    Open
                </Link>
            </div>
            <div className="px-3 py-4">{children}</div>
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
            <div className="mb-3 flex gap-3 text-[10px] font-semibold tracking-wide uppercase">
                <span className="inline-flex items-center gap-1.5">
                    <span
                        className="inline-block size-2.5"
                        style={{ background: aColor }}
                    />
                    {aLabel}
                </span>
                <span className="inline-flex items-center gap-1.5">
                    <span
                        className="inline-block size-2.5"
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
                            className="flex flex-1 flex-col items-center gap-1"
                        >
                            <div className="flex h-28 w-full items-end justify-center gap-0.5">
                                <div
                                    className="w-[42%] min-h-0.5"
                                    style={{
                                        height: `${(a / max) * 100}%`,
                                        background: aColor,
                                    }}
                                    title={`${aLabel}: ${a}`}
                                />
                                <div
                                    className="w-[42%] min-h-0.5"
                                    style={{
                                        height: `${(b / max) * 100}%`,
                                        background: bColor,
                                    }}
                                    title={`${bLabel}: ${b}`}
                                />
                            </div>
                            <span className="text-[10px] font-medium text-muted-foreground">
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
                        className="flex flex-1 flex-col items-center gap-1"
                    >
                        <div className="flex h-28 w-full items-end">
                            <div
                                className="w-full min-h-0.5"
                                style={{
                                    height: `${(value / max) * 100}%`,
                                    background: color,
                                }}
                                title={`${value}`}
                            />
                        </div>
                        <span className="text-[10px] font-medium text-muted-foreground">
                            {day.label}
                        </span>
                    </div>
                );
            })}
        </div>
    );
}

function FollowUpItem({ row }: { row: FollowUpRow }) {
    const overdue = row.reason.toLowerCase().includes('overdue');
    const rail = overdue ? 'border-destructive' : 'border-primary';
    const tag = overdue
        ? 'bg-destructive text-white'
        : 'bg-primary text-primary-foreground';

    return (
        <li className={`border-l-4 ${rail} bg-laundry-white`}>
            <Link
                href={`/leads/${row.id}`}
                className="block px-4 py-3 transition hover:bg-laundry-soft/50"
            >
                <div className="flex flex-wrap items-center gap-2">
                    <span
                        className={`px-2 py-0.5 text-[10px] font-semibold tracking-wide uppercase ${tag}`}
                    >
                        {row.reason}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {row.follow_up_label}
                    </span>
                </div>
                <p
                    className={`mt-1 font-semibold ${
                        overdue ? 'text-destructive' : 'text-primary'
                    }`}
                >
                    {row.contact_name}
                    {row.business_name ? ` · ${row.business_name}` : ''}
                </p>
                <p className="mt-0.5 text-sm text-muted-foreground">
                    {row.stage_name} · {row.product_name}
                </p>
                <p className="mt-1 text-sm">
                    {row.next_action ?? 'No next action written.'}
                </p>
            </Link>
        </li>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Command centre', href: '/dashboard' }],
};
