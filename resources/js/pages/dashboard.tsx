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
            line: `${metrics.follow_ups_due_today} follow-ups due today.`,
            tone: 'blue',
        };
    }
    if (metrics.whatsapp.inbound_today > 0) {
        return {
            line: `${metrics.whatsapp.inbound_today} WhatsApp inbound today — reply and qualify.`,
            tone: 'blue',
        };
    }
    if (metrics.demos.in_flight > 0) {
        return {
            line: `${metrics.demos.in_flight} demos in flight — keep them moving.`,
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
        line: 'Nothing urgent. Keep the pipeline moving.',
        tone: 'lead',
    };
}

const fill: Record<string, string> = {
    red: 'bg-destructive text-white',
    blue: 'bg-primary text-primary-foreground',
    green: 'bg-success text-success-foreground',
    lead: 'bg-lead text-lead-foreground',
};

const surface =
    'overflow-hidden rounded-xl border border-border bg-card shadow-[var(--shadow-card)]';

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
            <div className="mx-auto flex w-full max-w-[88rem] flex-col gap-8 p-4 md:p-8">
                {/* 1. Today */}
                <header className="grid gap-6 border-b border-border pb-8 lg:grid-cols-[1.4fr_1fr] lg:items-end">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Kemet Tech · Sales desk
                        </p>
                        <h1 className="mt-2 text-3xl font-semibold tracking-tight text-foreground md:text-4xl">
                            Command centre
                        </h1>
                        <p
                            className={`mt-4 max-w-2xl rounded-lg px-4 py-3 text-base font-medium leading-snug md:text-lg ${fill[speak.tone]}`}
                        >
                            {speak.line}
                        </p>
                        <p className="mt-3 text-sm text-muted-foreground">
                            {metrics.today} · {metrics.timezone}
                        </p>
                    </div>
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-2 xl:grid-cols-4">
                        <PriorityLink
                            href="/leads?follow_up=overdue"
                            label="Overdue"
                            value={metrics.overdue_follow_ups}
                            urgent={metrics.overdue_follow_ups > 0}
                        />
                        <PriorityLink
                            href="/leads?follow_up=due_today"
                            label="Due today"
                            value={metrics.follow_ups_due_today}
                        />
                        <PriorityLink
                            href="/whatsapp"
                            label="WA in today"
                            value={metrics.whatsapp.inbound_today}
                        />
                        <PriorityLink
                            href="/leads"
                            label="Open"
                            value={metrics.open_leads}
                        />
                    </div>
                </header>

                {/* 2. Work now */}
                <section className="grid gap-6 lg:grid-cols-5">
                    <div className={`${surface} lg:col-span-3`}>
                        <SectionTitle
                            title="Work now"
                            hint="Ranked follow-ups and talks. Highest priority first."
                        />
                        {metrics.follow_up_rows.length === 0 ? (
                            <p className="px-5 py-8 text-sm text-muted-foreground">
                                Nothing due and nothing overdue. Use the lead
                                board below for inbound chats.
                            </p>
                        ) : (
                            <ul className="divide-y divide-border">
                                {metrics.follow_up_rows.map((row) => (
                                    <FollowUpItem key={row.id} row={row} />
                                ))}
                            </ul>
                        )}
                    </div>

                    <div className={`${surface} lg:col-span-2`}>
                        <SectionTitle
                            title="Pipeline"
                            hint="Leads by stage. Click a stage to filter."
                        />
                        <div className="space-y-4 px-5 pb-5">
                            {metrics.leads_by_stage.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No leads in the funnel yet.
                                </p>
                            ) : (
                                metrics.leads_by_stage.map((row) => {
                                    const width = Math.max(
                                        8,
                                        Math.round(
                                            (row.total / stageMax) * 100,
                                        ),
                                    );
                                    const bar =
                                        row.kind === 'won'
                                            ? 'bg-success'
                                            : row.kind === 'lost'
                                              ? 'bg-destructive'
                                              : 'bg-primary';

                                    return (
                                        <Link
                                            key={row.stage_id}
                                            href={`/dashboard?stage_id=${row.stage_id}`}
                                            className="block outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        >
                                            <div className="mb-1.5 flex items-baseline justify-between gap-3 text-sm">
                                                <span className="font-medium text-foreground">
                                                    {row.stage_name}
                                                </span>
                                                <span className="tabular-nums font-semibold text-foreground">
                                                    {row.total}
                                                </span>
                                            </div>
                                            <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                                                <div
                                                    className={`h-full rounded-full ${bar}`}
                                                    style={{
                                                        width: `${width}%`,
                                                    }}
                                                />
                                            </div>
                                        </Link>
                                    );
                                })
                            )}
                        </div>
                        <div className="grid grid-cols-3 gap-px border-t border-border bg-border">
                            <Glance
                                label="Won"
                                value={String(metrics.won_count)}
                            />
                            <Glance
                                label="Lost"
                                value={String(metrics.lost_count)}
                            />
                            <Glance
                                label="Demos"
                                value={String(metrics.demos.in_flight)}
                            />
                        </div>
                    </div>
                </section>

                {/* 3. Lead board */}
                <section className={surface}>
                    <SectionTitle
                        title="Lead board"
                        hint="Filter the desk, then open a lead to reply."
                        action={
                            <Button asChild size="sm">
                                <Link href="/leads/create">New lead</Link>
                            </Button>
                        }
                    />

                    <Form
                        action="/dashboard"
                        method="get"
                        className="grid gap-3 border-b border-border px-5 py-4 md:grid-cols-2 xl:grid-cols-4"
                    >
                        {({ processing }) => (
                            <>
                                <TextField
                                    name="q"
                                    label="Search"
                                    defaultValue={board.filters.q}
                                    hint="Name or intent"
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
                                    label="System"
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
                                    label="From"
                                    type="date"
                                    defaultValue={board.filters.from}
                                />
                                <TextField
                                    name="to"
                                    label="To"
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
                                        Hot only
                                    </label>
                                    <div className="ml-auto flex gap-2">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Apply
                                        </Button>
                                        <Button variant="outline" asChild>
                                            <Link href="/dashboard">Clear</Link>
                                        </Button>
                                    </div>
                                </div>
                            </>
                        )}
                    </Form>

                    <div className="flex flex-wrap gap-x-6 gap-y-2 border-b border-border bg-muted/60 px-5 py-3 text-sm">
                        <span>
                            <strong className="tabular-nums">
                                {board.summary.matched}
                            </strong>{' '}
                            <span className="text-muted-foreground">
                                matched
                            </span>
                        </span>
                        <span>
                            <strong className="tabular-nums">
                                {board.summary.open}
                            </strong>{' '}
                            <span className="text-muted-foreground">open</span>
                        </span>
                        <span>
                            <strong className="tabular-nums">
                                {board.summary.hot}
                            </strong>{' '}
                            <span className="text-muted-foreground">hot</span>
                        </span>
                        <span className="text-muted-foreground">
                            {board.range.label}
                            {board.range.from
                                ? ` · ${board.range.from} → ${board.range.to}`
                                : ''}
                        </span>
                    </div>

                    {board.leads.length === 0 ? (
                        <p className="px-5 py-10 text-sm text-muted-foreground">
                            No leads match these filters. Widen status, stage,
                            or timeline.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[820px] text-left text-sm">
                                <thead className="border-b border-border text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    <tr>
                                        <th className="px-5 py-3">Lead</th>
                                        <th className="px-5 py-3">System</th>
                                        <th className="px-5 py-3">Stage</th>
                                        <th className="px-5 py-3">Intent</th>
                                        <th className="px-5 py-3">Next</th>
                                        <th className="px-5 py-3">Score</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {board.leads.map((lead) => (
                                        <tr
                                            key={lead.id}
                                            className="bg-card hover:bg-muted/50"
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
                                                    <p className="mt-1 text-xs text-muted-foreground">
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
                                            <td className="px-5 py-4 align-top text-muted-foreground">
                                                <p className="tabular-nums">
                                                    {lead.follow_up_label ??
                                                        '—'}
                                                </p>
                                                {lead.next_talk_label ? (
                                                    <p className="mt-1 text-xs">
                                                        Talk{' '}
                                                        {lead.next_talk_label}
                                                    </p>
                                                ) : null}
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

                {/* 4. Pulse */}
                <section className="grid gap-6 lg:grid-cols-3">
                    <div className={surface}>
                        <SectionTitle title="WhatsApp" hint="Last few days" />
                        <dl className="divide-y divide-border px-5">
                            <PulseRow
                                label="Messages today"
                                value={`${metrics.whatsapp.inbound_today} in / ${metrics.whatsapp.outbound_today} out`}
                            />
                            <PulseRow
                                label="Conversations"
                                value={String(metrics.whatsapp.conversations)}
                            />
                            <PulseRow
                                label="Active 7 days"
                                value={String(metrics.whatsapp.active_7d)}
                            />
                        </dl>
                        <div className="border-t border-border px-4 py-4">
                            <TwinBarChart
                                data={metrics.charts.whatsapp_7d}
                                aKey="inbound"
                                bKey="outbound"
                                aColor="var(--laundry-blue)"
                                bColor="var(--lead)"
                                aLabel="In"
                                bLabel="Out"
                            />
                        </div>
                    </div>

                    <div className={surface}>
                        <SectionTitle
                            title={`Leads · ${chartDays}d`}
                            hint="Opened vs won"
                        />
                        <div className="px-4 pb-4 pt-2">
                            <TwinBarChart
                                data={metrics.charts.leads_7d}
                                aKey="opened"
                                bKey="won"
                                aColor="var(--laundry-blue)"
                                bColor="var(--success)"
                                aLabel="Opened"
                                bLabel="Won"
                            />
                        </div>
                    </div>

                    <div className={surface}>
                        <SectionTitle title="Money" hint="Invoices & spend" />
                        <dl className="divide-y divide-border px-5">
                            <PulseRow
                                label="Collected"
                                value={money(
                                    metrics.orders.currency,
                                    metrics.orders.bought_revenue,
                                )}
                            />
                            <PulseRow
                                label="Unpaid"
                                value={String(metrics.orders.unpaid_count)}
                            />
                            <PulseRow
                                label="Overdue invoices"
                                value={String(metrics.orders.overdue_count)}
                            />
                            <PulseRow
                                label="Spend MTD"
                                value={money(
                                    metrics.orders.currency,
                                    metrics.spend.month_total,
                                )}
                            />
                        </dl>
                        <div className="border-t border-border px-4 py-4">
                            <SingleBarChart
                                data={metrics.charts.bought_7d}
                                valueKey="count"
                                color="var(--success)"
                            />
                        </div>
                    </div>
                </section>
            </div>
        </>
    );
}

function SectionTitle({
    title,
    hint,
    action,
}: {
    title: string;
    hint?: string;
    action?: ReactNode;
}) {
    return (
        <div className="flex items-start justify-between gap-3 border-b border-border px-5 py-4">
            <div>
                <h2 className="text-lg font-semibold tracking-tight text-foreground">
                    {title}
                </h2>
                {hint ? (
                    <p className="mt-1 text-sm text-muted-foreground">{hint}</p>
                ) : null}
            </div>
            {action}
        </div>
    );
}

function PriorityLink({
    href,
    label,
    value,
    urgent = false,
}: {
    href: string;
    label: string;
    value: number;
    urgent?: boolean;
}) {
    return (
        <Link
            href={href}
            className={`rounded-xl border px-4 py-3 outline-none transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring ${
                urgent
                    ? 'border-destructive bg-destructive text-white'
                    : 'border-border bg-card text-foreground'
            }`}
        >
            <p
                className={`text-xs font-medium ${urgent ? 'opacity-90' : 'text-muted-foreground'}`}
            >
                {label}
            </p>
            <p className="mt-1 text-2xl font-semibold tabular-nums">{value}</p>
        </Link>
    );
}

function Glance({ label, value }: { label: string; value: string }) {
    return (
        <div className="bg-card px-4 py-3 text-center">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-0.5 text-lg font-semibold tabular-nums">{value}</p>
        </div>
    );
}

function PulseRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-3 py-3 text-sm">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="font-semibold tabular-nums text-foreground">
                {value}
            </dd>
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
            <div className="mb-3 flex gap-4 text-xs font-medium text-muted-foreground">
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
            <div className="flex h-28 items-end gap-2">
                {data.map((day) => {
                    const a = Number(day[aKey] ?? 0);
                    const b = Number(day[bKey] ?? 0);
                    return (
                        <div
                            key={day.date}
                            className="flex flex-1 flex-col items-center gap-1"
                        >
                            <div className="flex h-20 w-full items-end justify-center gap-0.5">
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
                            <span className="text-[10px] text-muted-foreground">
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
}) {
    const max = Math.max(1, ...data.map((d) => Number(d[valueKey] ?? 0)));

    return (
        <div className="flex h-28 items-end gap-2">
            {data.map((day) => {
                const value = Number(day[valueKey] ?? 0);
                return (
                    <div
                        key={day.date}
                        className="flex flex-1 flex-col items-center gap-1"
                    >
                        <div className="flex h-20 w-full items-end">
                            <div
                                className="min-h-0.5 w-full rounded-t-sm"
                                style={{
                                    height: `${(value / max) * 100}%`,
                                    background: color,
                                }}
                                title={`${value}`}
                            />
                        </div>
                        <span className="text-[10px] text-muted-foreground">
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

    return (
        <li>
            <Link
                href={`/leads/${row.id}`}
                className="block px-5 py-4 transition-colors hover:bg-muted/50"
            >
                <div className="flex flex-wrap items-center gap-2">
                    {typeof row.rank === 'number' && row.rank > 0 ? (
                        <span className="text-xs font-semibold tabular-nums text-muted-foreground">
                            #{row.rank}
                        </span>
                    ) : null}
                    <Badge variant={overdue ? 'destructive' : 'default'}>
                        {row.reason}
                    </Badge>
                    {fromChat ? <Badge variant="outline">Chat</Badge> : null}
                    <span className="text-xs text-muted-foreground">
                        {row.follow_up_label}
                        {row.next_talk_label
                            ? ` · talk ${row.next_talk_label}`
                            : ''}
                    </span>
                </div>
                <p
                    className={`mt-2 font-semibold ${
                        overdue ? 'text-destructive' : 'text-foreground'
                    }`}
                >
                    {row.contact_name}
                    {row.business_name ? ` · ${row.business_name}` : ''}
                </p>
                <p className="mt-0.5 text-sm text-muted-foreground">
                    {row.stage_name} · {row.product_name}
                </p>
                <p className="mt-1.5 text-sm text-foreground">
                    {row.next_action ?? 'No next action written.'}
                </p>
            </Link>
        </li>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Command centre', href: '/dashboard' }],
};
