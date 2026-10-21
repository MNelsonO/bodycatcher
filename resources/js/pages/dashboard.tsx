import { Form, Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    CalendarClock,
    CheckCircle2,
    Flame,
    MessageCircle,
    Plus,
    Sparkles,
    Users,
} from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';
import { SelectField, TextField } from '@/components/crm/fields';
import { Page, surfaceClass } from '@/components/crm/page-frame';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { ChartDay, DashboardMetrics, FollowUpRow } from '@/types/crm';

type Tone = 'red' | 'blue' | 'green' | 'lead';
type LeadRow = DashboardMetrics['board']['leads'][number];

/* ------------------------------------------------------------------ */
/* Helpers                                                             */
/* ------------------------------------------------------------------ */

function money(currency: string, amount: number): string {
    return `${currency} ${amount.toLocaleString(undefined, { maximumFractionDigits: 0 })}`;
}

function speakToday(metrics: DashboardMetrics): {
    line: string;
    tone: Tone;
    href?: string;
    cta?: string;
} {
    if (metrics.overdue_follow_ups > 0) {
        return {
            line: `${metrics.overdue_follow_ups} overdue follow-up${metrics.overdue_follow_ups === 1 ? '' : 's'} — clear these first.`,
            tone: 'red',
            href: '/leads?follow_up=overdue',
            cta: 'Open overdue',
        };
    }
    if (metrics.orders.overdue_count > 0) {
        return {
            line: `${metrics.orders.overdue_count} overdue invoice${metrics.orders.overdue_count === 1 ? '' : 's'} need collection.`,
            tone: 'red',
        };
    }
    if (metrics.follow_ups_due_today > 0) {
        return {
            line: `${metrics.follow_ups_due_today} follow-up${metrics.follow_ups_due_today === 1 ? '' : 's'} due today.`,
            tone: 'blue',
            href: '/leads?follow_up=due_today',
            cta: 'Start now',
        };
    }
    if (metrics.whatsapp.inbound_today > 0) {
        return {
            line: `${metrics.whatsapp.inbound_today} WhatsApp inbound today — reply and qualify.`,
            tone: 'blue',
            href: '/whatsapp',
            cta: 'Open inbox',
        };
    }
    if (metrics.demos.in_flight > 0) {
        return {
            line: `${metrics.demos.in_flight} demo${metrics.demos.in_flight === 1 ? '' : 's'} in flight — keep them moving.`,
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
            line: 'No leads yet. Add one or wait for WhatsApp inbound.',
            tone: 'lead',
            href: '/leads/create',
            cta: 'New lead',
        };
    }
    return {
        line: 'Nothing urgent. Keep the pipeline moving.',
        tone: 'lead',
    };
}

const bannerTone: Record<Tone, string> = {
    red: 'bg-destructive text-white',
    blue: 'bg-primary text-primary-foreground',
    green: 'bg-success text-success-foreground',
    lead: 'bg-lead text-lead-foreground',
};

const bannerIcon: Record<Tone, ComponentType<{ className?: string }>> = {
    red: AlertTriangle,
    blue: CalendarClock,
    green: CheckCircle2,
    lead: Sparkles,
};

function scoreClass(score: number | null | undefined): string {
    if (score === null || score === undefined) {
        return 'text-muted-foreground';
    }
    if (score >= 70) {
        return 'bg-success/15 text-success';
    }
    if (score >= 40) {
        return 'bg-secondary text-secondary-foreground';
    }
    return 'bg-muted text-muted-foreground';
}

/* ------------------------------------------------------------------ */
/* Page                                                                */
/* ------------------------------------------------------------------ */

export default function Dashboard({ metrics }: { metrics: DashboardMetrics }) {
    const speak = speakToday(metrics);
    const BannerIcon = bannerIcon[speak.tone];
    const stageMax = Math.max(
        1,
        ...metrics.leads_by_stage.map((row) => row.total),
    );
    const stageSum = metrics.leads_by_stage.reduce(
        (sum, row) => sum + row.total,
        0,
    );
    const board = metrics.board;
    const chartDays = metrics.charts.days ?? 7;

    const activeFilters = Object.values(board.filters).filter(
        (v) => v !== '' && v !== false && v !== null && v !== undefined,
    ).length;

    const winRate =
        metrics.won_count + metrics.lost_count > 0
            ? Math.round(
                  (metrics.won_count /
                      (metrics.won_count + metrics.lost_count)) *
                      100,
              )
            : null;

    return (
        <>
            <Head title="Command centre" />
            <Page>
                {/* 1. Today ------------------------------------------------ */}
                <header className="grid gap-6 lg:grid-cols-[1.3fr_1fr] lg:items-stretch">
                    <div className="flex flex-col justify-between gap-5">
                        <div>
                            <p className="text-sm font-medium text-primary">
                                Kemet Tech · Sales desk
                            </p>
                            <h1 className="mt-1.5 text-3xl font-semibold tracking-tight text-foreground md:text-4xl">
                                Command centre
                            </h1>
                            <p className="mt-1.5 text-sm text-muted-foreground">
                                {metrics.today} · {metrics.timezone}
                            </p>
                        </div>

                        <div
                            role="status"
                            className={`flex flex-wrap items-center gap-x-4 gap-y-3 rounded-xl px-5 py-4 ${bannerTone[speak.tone]}`}
                        >
                            <BannerIcon className="size-6 shrink-0" />
                            <p className="min-w-0 flex-1 text-base font-semibold leading-snug md:text-lg">
                                {speak.line}
                            </p>
                            {speak.href && speak.cta ? (
                                <Link
                                    href={speak.href}
                                    className="rounded-lg bg-white/20 px-3.5 py-2 text-sm font-semibold outline-none transition-colors hover:bg-white/30 focus-visible:ring-2 focus-visible:ring-white"
                                >
                                    {speak.cta} →
                                </Link>
                            ) : null}
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <PriorityLink
                            href="/leads?follow_up=overdue"
                            label="Overdue"
                            value={metrics.overdue_follow_ups}
                            icon={AlertTriangle}
                            urgent={metrics.overdue_follow_ups > 0}
                        />
                        <PriorityLink
                            href="/leads?follow_up=due_today"
                            label="Due today"
                            value={metrics.follow_ups_due_today}
                            icon={CalendarClock}
                        />
                        <PriorityLink
                            href="/whatsapp"
                            label="WhatsApp in today"
                            value={metrics.whatsapp.inbound_today}
                            icon={MessageCircle}
                        />
                        <PriorityLink
                            href="/leads"
                            label="Open leads"
                            value={metrics.open_leads}
                            icon={Users}
                        />
                    </div>
                </header>

                {/* 2. Work now + Pipeline ---------------------------------- */}
                <section className="grid gap-6 lg:grid-cols-5">
                    <div className={`${surfaceClass} lg:col-span-3`}>
                        <SectionTitle
                            title="Work now"
                            hint="Ranked follow-ups and talks. Highest priority first."
                            badge={
                                metrics.follow_up_rows.length > 0
                                    ? String(metrics.follow_up_rows.length)
                                    : undefined
                            }
                        />
                        {metrics.follow_up_rows.length === 0 ? (
                            <EmptyState
                                icon={CheckCircle2}
                                title="You're clear"
                                body="Nothing due and nothing overdue. Use the lead board below for inbound chats."
                            />
                        ) : (
                            <ul className="divide-y divide-border">
                                {metrics.follow_up_rows.map((row) => (
                                    <FollowUpItem key={row.id} row={row} />
                                ))}
                            </ul>
                        )}
                    </div>

                    <div className={`${surfaceClass} flex flex-col lg:col-span-2`}>
                        <SectionTitle
                            title="Pipeline"
                            hint="Leads by stage. Select a stage to filter."
                        />
                        <div className="flex-1 space-y-1 px-2 py-3">
                            {metrics.leads_by_stage.length === 0 ? (
                                <p className="px-3 py-2 text-sm text-muted-foreground">
                                    No leads in the funnel yet.
                                </p>
                            ) : (
                                metrics.leads_by_stage.map((row) => {
                                    const width = Math.max(
                                        6,
                                        Math.round(
                                            (row.total / stageMax) * 100,
                                        ),
                                    );
                                    const share =
                                        stageSum > 0
                                            ? Math.round(
                                                  (row.total / stageSum) * 100,
                                              )
                                            : 0;
                                    const bar =
                                        row.kind === 'won'
                                            ? 'bg-success'
                                            : row.kind === 'lost'
                                              ? 'bg-destructive/70'
                                              : 'bg-primary';
                                    const selected =
                                        String(board.filters.stage_id) ===
                                        String(row.stage_id);

                                    return (
                                        <Link
                                            key={row.stage_id}
                                            href={`/dashboard?stage_id=${row.stage_id}`}
                                            aria-current={
                                                selected ? 'true' : undefined
                                            }
                                            className={`block rounded-lg px-3 py-2 outline-none transition-colors hover:bg-muted/60 focus-visible:ring-2 focus-visible:ring-ring ${
                                                selected ? 'bg-muted' : ''
                                            }`}
                                        >
                                            <div className="mb-1.5 flex items-baseline justify-between gap-3 text-sm">
                                                <span className="font-medium text-foreground">
                                                    {row.stage_name}
                                                </span>
                                                <span className="tabular-nums text-muted-foreground">
                                                    <strong className="font-semibold text-foreground">
                                                        {row.total}
                                                    </strong>{' '}
                                                    · {share}%
                                                </span>
                                            </div>
                                            <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                                                <div
                                                    className={`h-full rounded-full transition-[width] ${bar}`}
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
                        <div className="grid grid-cols-4 gap-px border-t border-border bg-border">
                            <Glance
                                label="Won"
                                value={String(metrics.won_count)}
                            />
                            <Glance
                                label="Lost"
                                value={String(metrics.lost_count)}
                            />
                            <Glance
                                label="Win rate"
                                value={winRate === null ? '—' : `${winRate}%`}
                            />
                            <Glance
                                label="Demos"
                                value={String(metrics.demos.in_flight)}
                            />
                        </div>
                    </div>
                </section>

                {/* 3. Lead board ------------------------------------------- */}
                <section className={surfaceClass}>
                    <SectionTitle
                        title="Lead board"
                        hint="Filter the desk, then open a lead to reply."
                        action={
                            <Button asChild size="sm">
                                <Link href="/leads/create">
                                    <Plus className="size-4" />
                                    New lead
                                </Link>
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

                                <details
                                    className="group md:col-span-2 xl:col-span-4"
                                    open={
                                        board.filters.follow_up !== '' ||
                                        board.filters.timeline !== '' ||
                                        board.filters.from !== '' ||
                                        board.filters.to !== ''
                                    }
                                >
                                    <summary className="cursor-pointer select-none text-sm font-medium text-primary outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                        More filters
                                    </summary>
                                    <div className="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
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
                                    </div>
                                </details>

                                <div className="flex flex-wrap items-center gap-3 md:col-span-2 xl:col-span-4">
                                    <label className="inline-flex cursor-pointer items-center gap-2 text-sm font-medium">
                                        <input
                                            type="checkbox"
                                            name="hot"
                                            value="1"
                                            defaultChecked={board.filters.hot}
                                            className="size-4 rounded border-input accent-primary"
                                        />
                                        <Flame className="size-4 text-destructive" />
                                        Hot only
                                    </label>
                                    <div className="ml-auto flex gap-2">
                                        {activeFilters > 0 ? (
                                            <Button variant="ghost" asChild>
                                                <Link href="/dashboard">
                                                    Clear ({activeFilters})
                                                </Link>
                                            </Button>
                                        ) : null}
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Applying…'
                                                : 'Apply'}
                                        </Button>
                                    </div>
                                </div>
                            </>
                        )}
                    </Form>

                    <div className="flex flex-wrap gap-x-6 gap-y-1 border-b border-border bg-muted/60 px-5 py-3 text-sm">
                        <SummaryStat
                            value={board.summary.matched}
                            label="matched"
                        />
                        <SummaryStat value={board.summary.open} label="open" />
                        <SummaryStat value={board.summary.hot} label="hot" />
                        <span className="text-muted-foreground sm:ml-auto">
                            {board.range.label}
                            {board.range.from
                                ? ` · ${board.range.from} → ${board.range.to}`
                                : ''}
                        </span>
                    </div>

                    {board.leads.length === 0 ? (
                        <EmptyState
                            icon={Users}
                            title="No leads match"
                            body="Widen the status, stage or timeline, or clear the filters."
                        />
                    ) : (
                        <>
                            {/* Mobile: cards */}
                            <ul className="divide-y divide-border md:hidden">
                                {board.leads.map((lead) => (
                                    <li key={lead.id}>
                                        <Link
                                            href={`/leads/${lead.id}`}
                                            className="block px-5 py-4 transition-colors hover:bg-muted/50"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0">
                                                    <p className="truncate font-semibold text-primary">
                                                        {lead.contact_name}
                                                    </p>
                                                    {lead.business_name ? (
                                                        <p className="truncate text-xs text-muted-foreground">
                                                            {lead.business_name}
                                                        </p>
                                                    ) : null}
                                                </div>
                                                <ScoreChip
                                                    score={lead.ai_score}
                                                />
                                            </div>
                                            <div className="mt-2 flex flex-wrap items-center gap-1.5">
                                                {lead.hot ? <HotBadge /> : null}
                                                <Badge variant="outline">
                                                    {lead.stage_name}
                                                </Badge>
                                                <span className="text-xs text-muted-foreground">
                                                    {lead.product_name}
                                                </span>
                                            </div>
                                            <p className="mt-2 line-clamp-2 text-sm">
                                                {lead.intent ??
                                                    lead.next_action ??
                                                    '—'}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {lead.follow_up_label ?? 'No follow-up'}
                                                {lead.next_talk_label
                                                    ? ` · talk ${lead.next_talk_label}`
                                                    : ''}
                                            </p>
                                        </Link>
                                    </li>
                                ))}
                            </ul>

                            {/* Desktop: table */}
                            <div className="hidden overflow-x-auto md:block">
                                <table className="w-full min-w-[820px] text-left text-sm">
                                    <thead className="sticky top-0 border-b border-border bg-card text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                        <tr>
                                            <th scope="col" className="px-5 py-3">
                                                Lead
                                            </th>
                                            <th scope="col" className="px-5 py-3">
                                                System
                                            </th>
                                            <th scope="col" className="px-5 py-3">
                                                Stage
                                            </th>
                                            <th scope="col" className="px-5 py-3">
                                                Intent
                                            </th>
                                            <th scope="col" className="px-5 py-3">
                                                Next
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-5 py-3 text-right"
                                            >
                                                Score
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {board.leads.map((lead) => (
                                            <LeadTableRow
                                                key={lead.id}
                                                lead={lead}
                                            />
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}
                </section>

                {/* 4. Pulse ------------------------------------------------ */}
                <section className="grid gap-6 lg:grid-cols-3">
                    <div className={surfaceClass}>
                        <SectionTitle
                            title="WhatsApp"
                            hint={`Messages · last ${chartDays} days`}
                        />
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
                        <div className="border-t border-border px-5 py-4">
                            <TwinBarChart
                                name="WhatsApp messages in and out"
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

                    <div className={surfaceClass}>
                        <SectionTitle
                            title={`Leads · ${chartDays}d`}
                            hint="Opened vs won"
                        />
                        <div className="px-5 pb-5 pt-4">
                            <TwinBarChart
                                name="Leads opened versus won"
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

                    <div className={surfaceClass}>
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
                                danger={metrics.orders.overdue_count > 0}
                            />
                            <PulseRow
                                label="Spend MTD"
                                value={money(
                                    metrics.orders.currency,
                                    metrics.spend.month_total,
                                )}
                            />
                        </dl>
                        <div className="border-t border-border px-5 py-4">
                            <p className="mb-3 text-xs font-medium text-muted-foreground">
                                Purchases per day
                            </p>
                            <SingleBarChart
                                name="Purchases per day"
                                data={metrics.charts.bought_7d}
                                valueKey="count"
                                color="var(--success)"
                            />
                        </div>
                    </div>
                </section>
            </Page>
        </>
    );
}

/* ------------------------------------------------------------------ */
/* Building blocks                                                     */
/* ------------------------------------------------------------------ */

function SectionTitle({
    title,
    hint,
    action,
    badge,
}: {
    title: string;
    hint?: string;
    action?: ReactNode;
    badge?: string;
}) {
    return (
        <div className="flex items-start justify-between gap-3 border-b border-border px-5 py-4">
            <div>
                <div className="flex items-center gap-2">
                    <h2 className="text-lg font-semibold tracking-tight text-foreground">
                        {title}
                    </h2>
                    {badge ? (
                        <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-semibold tabular-nums text-muted-foreground">
                            {badge}
                        </span>
                    ) : null}
                </div>
                {hint ? (
                    <p className="mt-0.5 text-sm text-muted-foreground">
                        {hint}
                    </p>
                ) : null}
            </div>
            {action}
        </div>
    );
}

function EmptyState({
    icon: Icon,
    title,
    body,
}: {
    icon: ComponentType<{ className?: string }>;
    title: string;
    body: string;
}) {
    return (
        <div className="flex flex-col items-center gap-2 px-5 py-10 text-center">
            <span className="rounded-full bg-muted p-3 text-muted-foreground">
                <Icon className="size-5" />
            </span>
            <p className="font-semibold text-foreground">{title}</p>
            <p className="max-w-sm text-sm text-muted-foreground">{body}</p>
        </div>
    );
}

function PriorityLink({
    href,
    label,
    value,
    icon: Icon,
    urgent = false,
}: {
    href: string;
    label: string;
    value: number;
    icon: ComponentType<{ className?: string }>;
    urgent?: boolean;
}) {
    return (
        <Link
            href={href}
            className={`flex flex-col justify-between gap-3 rounded-xl border px-4 py-4 outline-none transition-colors focus-visible:ring-2 focus-visible:ring-ring ${
                urgent
                    ? 'border-destructive bg-destructive text-white hover:bg-destructive/90'
                    : 'border-border bg-card text-foreground shadow-[var(--shadow-card)] hover:bg-muted'
            }`}
        >
            <div
                className={`flex items-center gap-2 text-xs font-medium ${urgent ? 'opacity-90' : 'text-muted-foreground'}`}
            >
                <Icon className="size-4" />
                {label}
            </div>
            <p className="text-3xl font-semibold leading-none tabular-nums">
                {value}
            </p>
        </Link>
    );
}

function Glance({ label, value }: { label: string; value: string }) {
    return (
        <div className="bg-card px-2 py-3 text-center">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-0.5 text-lg font-semibold tabular-nums">{value}</p>
        </div>
    );
}

function SummaryStat({ value, label }: { value: number; label: string }) {
    return (
        <span>
            <strong className="tabular-nums">{value}</strong>{' '}
            <span className="text-muted-foreground">{label}</span>
        </span>
    );
}

function PulseRow({
    label,
    value,
    danger = false,
}: {
    label: string;
    value: string;
    danger?: boolean;
}) {
    return (
        <div className="flex items-center justify-between gap-3 py-3 text-sm">
            <dt className="text-muted-foreground">{label}</dt>
            <dd
                className={`font-semibold tabular-nums ${danger ? 'text-destructive' : 'text-foreground'}`}
            >
                {value}
            </dd>
        </div>
    );
}

function HotBadge() {
    return (
        <Badge className="gap-1">
            <Flame className="size-3" />
            Hot
        </Badge>
    );
}

function ScoreChip({ score }: { score: number | null | undefined }) {
    if (score === null || score === undefined) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <span
            className={`inline-flex min-w-9 justify-center rounded-md px-2 py-0.5 text-sm font-semibold tabular-nums ${scoreClass(score)}`}
        >
            {score}
        </span>
    );
}

function LeadTableRow({ lead }: { lead: LeadRow }) {
    return (
        <tr className="bg-card transition-colors hover:bg-muted/50">
            <td className="px-5 py-4 align-top">
                <Link
                    href={`/leads/${lead.id}`}
                    className="font-semibold text-primary hover:underline"
                >
                    {lead.contact_name}
                </Link>
                {lead.business_name ? (
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        {lead.business_name}
                    </p>
                ) : null}
                {lead.hot ? (
                    <div className="mt-1.5">
                        <HotBadge />
                    </div>
                ) : null}
            </td>
            <td className="px-5 py-4 align-top">{lead.product_name}</td>
            <td className="px-5 py-4 align-top">
                <p>{lead.stage_name}</p>
                <Badge variant="outline" className="mt-1.5">
                    {lead.stage_kind}
                </Badge>
            </td>
            <td className="max-w-xs px-5 py-4 align-top">
                <p className="line-clamp-2">
                    {lead.intent ?? lead.next_action ?? (
                        <span className="text-muted-foreground">—</span>
                    )}
                </p>
            </td>
            <td className="px-5 py-4 align-top text-muted-foreground">
                <p className="tabular-nums">{lead.follow_up_label ?? '—'}</p>
                {lead.next_talk_label ? (
                    <p className="mt-1 text-xs">Talk {lead.next_talk_label}</p>
                ) : null}
            </td>
            <td className="px-5 py-4 text-right align-top">
                <ScoreChip score={lead.ai_score} />
            </td>
        </tr>
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
                className={`block border-l-4 px-5 py-4 outline-none transition-colors hover:bg-muted/50 focus-visible:bg-muted/50 ${
                    overdue ? 'border-l-destructive' : 'border-l-transparent'
                }`}
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
                    {fromChat ? (
                        <Badge variant="outline" className="gap-1">
                            <MessageCircle className="size-3" />
                            Chat
                        </Badge>
                    ) : null}
                    <span className="text-xs text-muted-foreground">
                        {row.follow_up_label}
                        {row.next_talk_label
                            ? ` · talk ${row.next_talk_label}`
                            : ''}
                    </span>
                </div>
                <p
                    className={`mt-2 font-semibold ${overdue ? 'text-destructive' : 'text-foreground'}`}
                >
                    {row.contact_name}
                    {row.business_name ? ` · ${row.business_name}` : ''}
                </p>
                <p className="mt-0.5 text-sm text-muted-foreground">
                    {row.stage_name} · {row.product_name}
                </p>
                <p className="mt-1.5 text-sm text-foreground">
                    {row.next_action ?? (
                        <span className="text-muted-foreground">
                            No next action written.
                        </span>
                    )}
                </p>
            </Link>
        </li>
    );
}

/* ------------------------------------------------------------------ */
/* Charts                                                              */
/* ------------------------------------------------------------------ */

function Legend({ items }: { items: { color: string; label: string }[] }) {
    return (
        <div className="mb-3 flex gap-4 text-xs font-medium text-muted-foreground">
            {items.map((item) => (
                <span
                    key={item.label}
                    className="inline-flex items-center gap-1.5"
                >
                    <span
                        className="inline-block size-2.5 rounded-sm"
                        style={{ background: item.color }}
                    />
                    {item.label}
                </span>
            ))}
        </div>
    );
}

function BarValue({ value }: { value: number }) {
    return (
        <span className="h-3.5 text-[10px] font-semibold leading-none tabular-nums text-muted-foreground">
            {value > 0 ? value : ''}
        </span>
    );
}

function TwinBarChart({
    name,
    data,
    aKey,
    bKey,
    aColor,
    bColor,
    aLabel,
    bLabel,
}: {
    name: string;
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
    const total = data.reduce(
        (sum, d) => sum + Number(d[aKey] ?? 0) + Number(d[bKey] ?? 0),
        0,
    );

    return (
        <figure aria-label={name}>
            <Legend
                items={[
                    { color: aColor, label: aLabel },
                    { color: bColor, label: bLabel },
                ]}
            />
            {total === 0 ? (
                <p className="flex h-28 items-center justify-center rounded-lg border border-dashed border-border text-sm text-muted-foreground">
                    No activity in this period.
                </p>
            ) : (
                <div className="flex h-32 items-end gap-2 border-b border-border">
                    {data.map((day) => {
                        const a = Number(day[aKey] ?? 0);
                        const b = Number(day[bKey] ?? 0);

                        return (
                            <div
                                key={day.date}
                                className="flex flex-1 flex-col items-center"
                                title={`${day.label} — ${aLabel}: ${a}, ${bLabel}: ${b}`}
                            >
                                <div className="flex h-24 w-full items-end justify-center gap-0.5">
                                    <div className="flex h-full w-[42%] flex-col items-center justify-end gap-0.5">
                                        <BarValue value={a} />
                                        <div
                                            className="min-h-0.5 w-full rounded-t-sm"
                                            style={{
                                                height: `${(a / max) * 80}%`,
                                                background: aColor,
                                            }}
                                        />
                                    </div>
                                    <div className="flex h-full w-[42%] flex-col items-center justify-end gap-0.5">
                                        <BarValue value={b} />
                                        <div
                                            className="min-h-0.5 w-full rounded-t-sm"
                                            style={{
                                                height: `${(b / max) * 80}%`,
                                                background: bColor,
                                            }}
                                        />
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}
            {total > 0 ? <DayLabels data={data} /> : null}
        </figure>
    );
}

function SingleBarChart({
    name,
    data,
    valueKey,
    color,
}: {
    name: string;
    data: ChartDay[];
    valueKey: keyof ChartDay;
    color: string;
}) {
    const max = Math.max(1, ...data.map((d) => Number(d[valueKey] ?? 0)));
    const total = data.reduce((sum, d) => sum + Number(d[valueKey] ?? 0), 0);

    return (
        <figure aria-label={name}>
            {total === 0 ? (
                <p className="flex h-28 items-center justify-center rounded-lg border border-dashed border-border text-sm text-muted-foreground">
                    No purchases in this period.
                </p>
            ) : (
                <div className="flex h-32 items-end gap-2 border-b border-border">
                    {data.map((day) => {
                        const value = Number(day[valueKey] ?? 0);

                        return (
                            <div
                                key={day.date}
                                className="flex h-24 flex-1 flex-col items-center justify-end gap-0.5"
                                title={`${day.label}: ${value}`}
                            >
                                <BarValue value={value} />
                                <div
                                    className="min-h-0.5 w-full rounded-t-sm"
                                    style={{
                                        height: `${(value / max) * 80}%`,
                                        background: color,
                                    }}
                                />
                            </div>
                        );
                    })}
                </div>
            )}
            {total > 0 ? <DayLabels data={data} /> : null}
        </figure>
    );
}

function DayLabels({ data }: { data: ChartDay[] }) {
    return (
        <div className="mt-1.5 flex gap-2" aria-hidden>
            {data.map((day) => (
                <span
                    key={day.date}
                    className="flex-1 text-center text-[10px] text-muted-foreground"
                >
                    {day.label}
                </span>
            ))}
        </div>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Command centre', href: '/dashboard' }],
};