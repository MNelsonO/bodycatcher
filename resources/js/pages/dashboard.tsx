import { Head, Link } from '@inertiajs/react';
import type { DashboardMetrics, FollowUpRow } from '@/types/crm';

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

    if (metrics.follow_ups_due_today > 0) {
        return {
            line: `${metrics.follow_ups_due_today} follow-ups due today. Work the list.`,
            tone: 'blue',
        };
    }

    if (metrics.new_leads_today > 0) {
        return {
            line: `${metrics.new_leads_today} new leads landed today. Open and qualify.`,
            tone: 'blue',
        };
    }

    if (metrics.won_count > 0 && metrics.total_leads > 0) {
        return {
            line: `Desk is clear. ${metrics.won_count} won on the board.`,
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
        line: 'Nothing urgent. Keep pipeline moving.',
        tone: 'lead',
    };
}

const toneFill: Record<string, string> = {
    red: 'bg-destructive text-white',
    blue: 'bg-primary text-primary-foreground',
    green: 'bg-success text-success-foreground',
    lead: 'bg-lead text-lead-foreground',
};

const toneInk: Record<string, string> = {
    red: 'text-destructive',
    blue: 'text-primary',
    green: 'text-success',
    lead: 'text-lead',
};

export default function Dashboard({ metrics }: { metrics: DashboardMetrics }) {
    const empty = metrics.total_leads === 0;
    const speak = speakToday(metrics);
    const stageMax = Math.max(
        1,
        ...metrics.leads_by_stage.map((row) => row.total),
    );
    const openStages = metrics.leads_by_stage.filter(
        (row) => row.kind !== 'won' && row.kind !== 'lost',
    );
    const closedStages = metrics.leads_by_stage.filter(
        (row) => row.kind === 'won' || row.kind === 'lost',
    );

    return (
        <>
            <Head title="Command centre" />
            <div className="flex flex-col gap-8 p-4 md:p-6">
                {/* Hero — one composition, bold color voice */}
                <section
                    className={`relative overflow-hidden rounded-none px-5 py-8 md:px-8 md:py-10 ${toneFill[speak.tone]}`}
                >
                    <div
                        className="pointer-events-none absolute inset-0 opacity-20"
                        style={{
                            backgroundImage:
                                'linear-gradient(135deg, transparent 40%, #fff 40%, #fff 42%, transparent 42%), linear-gradient(225deg, transparent 55%, #000 55%, #000 56%, transparent 56%)',
                        }}
                        aria-hidden
                    />
                    <p className="relative text-xs font-semibold tracking-[0.2em] uppercase opacity-80">
                        Kemet · Laundry desk
                    </p>
                    <h1 className="relative mt-3 max-w-3xl text-3xl font-semibold tracking-tight md:text-5xl">
                        Command centre
                    </h1>
                    <p className="relative mt-4 max-w-2xl text-lg font-medium md:text-2xl">
                        {speak.line}
                    </p>
                    <p className="relative mt-3 text-sm opacity-75">
                        {metrics.today} · {metrics.timezone}
                    </p>
                </section>

                {/* Bold signal strip — solid fills, no soft cards */}
                <section className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Signal
                        href="/leads?follow_up=overdue"
                        value={metrics.overdue_follow_ups}
                        label="Overdue"
                        fill="bg-destructive text-white"
                        pulse={metrics.overdue_follow_ups > 0}
                    />
                    <Signal
                        href="/leads?follow_up=due_today"
                        value={metrics.follow_ups_due_today}
                        label="Due today"
                        fill="bg-primary text-primary-foreground"
                    />
                    <Signal
                        href="/leads?created=today"
                        value={metrics.new_leads_today}
                        label="New today"
                        fill="bg-laundry-white text-primary border-2 border-primary"
                    />
                    <Signal
                        href="/leads?kind=won"
                        value={metrics.won_count}
                        label="Won"
                        fill="bg-success text-success-foreground"
                    />
                </section>

                <section className="grid grid-cols-2 gap-3">
                    <Signal
                        href="/leads"
                        value={metrics.total_leads}
                        label="All leads"
                        fill="bg-lead text-lead-foreground"
                        compact
                    />
                    <Signal
                        href="/leads?kind=lost"
                        value={metrics.lost_count}
                        label="Lost"
                        fill="bg-laundry-white text-lead border-2 border-lead"
                        compact
                    />
                </section>

                {/* Pipeline — thick color bars, not cards */}
                <section className="space-y-4">
                    <div className="flex items-end justify-between gap-3">
                        <div>
                            <h2 className="text-lg font-semibold tracking-tight text-primary">
                                Pipeline
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Solid bars. Click a stage to open that list.
                            </p>
                        </div>
                    </div>

                    {empty ? (
                        <p className="border-l-4 border-lead py-3 pl-4 text-sm text-lead">
                            No leads yet. When you open one, stage weight shows
                            here.
                        </p>
                    ) : (
                        <div className="space-y-6">
                            <StageBars
                                rows={openStages}
                                max={stageMax}
                                mode="open"
                            />
                            {closedStages.length > 0 ? (
                                <StageBars
                                    rows={closedStages}
                                    max={stageMax}
                                    mode="closed"
                                />
                            ) : null}
                        </div>
                    )}
                </section>

                {/* Action list — speak next moves */}
                <section className="space-y-4">
                    <div>
                        <h2 className="text-lg font-semibold tracking-tight text-primary">
                            Next moves
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Overdue in red. Due today in blue. One job each.
                        </p>
                    </div>

                    {metrics.follow_up_rows.length === 0 ? (
                        <p className="border-l-4 border-success py-3 pl-4 text-sm text-success">
                            Nothing due and nothing overdue. Desk is clear.
                        </p>
                    ) : (
                        <ul className="space-y-2">
                            {metrics.follow_up_rows.map((row) => (
                                <FollowUpRowItem key={row.id} row={row} />
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

function Signal({
    href,
    value,
    label,
    fill,
    pulse = false,
    compact = false,
}: {
    href: string;
    value: number;
    label: string;
    fill: string;
    pulse?: boolean;
    compact?: boolean;
}) {
    return (
        <Link
            href={href}
            className={`group relative block outline-none transition hover:brightness-110 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 ${fill} ${
                compact ? 'px-4 py-4' : 'px-4 py-6 md:px-5 md:py-7'
            } ${pulse ? 'animate-[pulse_2.4s_ease-in-out_infinite]' : ''}`}
        >
            <p
                className={`font-semibold tracking-[0.14em] uppercase opacity-80 ${
                    compact ? 'text-[10px]' : 'text-xs'
                }`}
            >
                {label}
            </p>
            <p
                className={`mt-2 font-semibold tabular-nums tracking-tight ${
                    compact ? 'text-3xl' : 'text-4xl md:text-5xl'
                }`}
            >
                {value}
            </p>
        </Link>
    );
}

function StageBars({
    rows,
    max,
    mode,
}: {
    rows: DashboardMetrics['leads_by_stage'];
    max: number;
    mode: 'open' | 'closed';
}) {
    return (
        <ul className="space-y-3">
            {rows.map((row) => {
                const width = Math.max(8, Math.round((row.total / max) * 100));
                const bar =
                    row.kind === 'won'
                        ? 'bg-success'
                        : row.kind === 'lost'
                          ? 'bg-destructive'
                          : mode === 'open'
                            ? 'bg-primary'
                            : 'bg-lead';

                return (
                    <li key={row.stage_id}>
                        <Link
                            href={`/leads?stage_id=${row.stage_id}`}
                            className="block outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <div className="mb-1 flex items-baseline justify-between gap-3 text-sm">
                                <span>
                                    <span className="font-medium">
                                        {row.stage_name}
                                    </span>
                                    <span className="ml-2 text-xs text-muted-foreground">
                                        {row.pipeline_name}
                                    </span>
                                </span>
                                <span className="tabular-nums font-semibold text-laundry-ink">
                                    {row.total}
                                </span>
                            </div>
                            <div className="h-3 w-full bg-lead-soft">
                                <div
                                    className={`h-full ${bar} transition-[width] duration-500`}
                                    style={{ width: `${width}%` }}
                                />
                            </div>
                        </Link>
                    </li>
                );
            })}
        </ul>
    );
}

function FollowUpRowItem({ row }: { row: FollowUpRow }) {
    const overdue = row.reason.toLowerCase().includes('overdue');
    const rail = overdue ? 'border-destructive' : 'border-primary';
    const tag = overdue
        ? 'bg-destructive text-white'
        : 'bg-primary text-primary-foreground';

    return (
        <li className={`border-l-4 ${rail} bg-laundry-white`}>
            <Link
                href={`/leads/${row.id}`}
                className="block px-4 py-3 transition hover:bg-laundry-soft/60"
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
                <p className={`mt-1 font-semibold ${toneInk[overdue ? 'red' : 'blue']}`}>
                    {row.contact_name}
                    {row.business_name ? ` · ${row.business_name}` : ''}
                </p>
                <p className="mt-0.5 text-sm text-muted-foreground">
                    {row.stage_name} · {row.product_name}
                </p>
                <p className="mt-1 text-sm text-laundry-ink">
                    {row.next_action
                        ? row.next_action
                        : 'No next action written.'}
                </p>
            </Link>
        </li>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Command centre', href: '/dashboard' }],
};
