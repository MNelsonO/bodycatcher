import { Form, Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';

type ProductRow = {
    product_id: number;
    product_name: string;
    leads: number;
    open: number;
    won: number;
    lost: number;
    high_interest: number;
    conversion_rate: number;
    avg_score: number | null;
};

type DayRow = {
    date: string;
    label: string;
    leads: number;
    won: number;
    messages: number;
    revenue: number;
    spend: number;
};

type Report = {
    filters: { from: string; to: string };
    summary: {
        leads: number;
        won: number;
        lost: number;
        open: number;
        conversion_rate: number;
        high_interest: number;
        spend: number;
        bought_revenue: number;
        net_position: number;
        unpaid_invoices: number;
        messages: number;
        ai_reads: number;
        currency: string;
    };
    products: ProductRow[];
    daily: DayRow[];
    spend: {
        total: number;
        currency: string;
        by_category: Array<{ category: string; gross: number; rows: number }>;
    };
    sectors: {
        sales: {
            open_leads: number;
            new_in_range: number;
            won_in_range: number;
            lost_in_range: number;
            by_stage: Array<{
                stage_id: number;
                stage_name: string;
                pipeline_name: string;
                kind: string;
                total: number;
            }>;
            by_source: Array<{ source: string; total: number }>;
            demos: {
                requested: number;
                scheduled: number;
                completed: number;
            };
        };
        billing: {
            issued_count: number;
            issued_total: number;
            paid_revenue: number;
            tax_collected: number;
            draft_count: number;
            unpaid_count: number;
            unpaid_total: number;
            overdue_count: number;
            overdue_total: number;
            paid_count: number;
            by_status: Array<{ status: string; rows: number; amount: number }>;
            currency: string;
        };
        subscriptions: {
            active: number;
            paused: number;
            ended: number;
            mrr: number;
            due_soon: number;
            overdue: number;
            payments_in_range: number;
            collected_in_range: number;
            currency: string;
        };
        money: {
            cash: number;
            i_owe: number;
            owed_to_me: number;
            net: number;
            monthly_in: number;
            monthly_out: number;
            accounts: Array<{
                name: string;
                account_type: string;
                balance: number;
                is_active: boolean;
            }>;
            currency: string;
        };
        messaging: {
            total: number;
            inbound: number;
            outbound: number;
            reply_ratio: number;
            active_contacts: number;
            by_channel: Array<{ channel: string; total: number }>;
        };
        ai: {
            completed: number;
            failed: number;
            unavailable: number;
            queued: number;
            avg_score: number | null;
            high_score: number;
        };
        follow_ups: {
            tasks_in_range: number;
            by_status: Array<{ status: string; total: number }>;
            due_today: number;
            overdue: number;
            scheduled_talks: number;
            interactions: Array<{ type: string; total: number }>;
        };
        contacts: {
            total: number;
            by_country: Array<{
                country_code: string;
                country: string;
                total: number;
            }>;
            by_area: Array<{ area: string; total: number }>;
            opted_in: number;
            opted_out: number;
            unknown_consent: number;
        };
        integrations: {
            reports: Array<{
                id: number;
                provider: string;
                label: string | null;
                report_type: string;
                period_start: string | null;
                period_end: string | null;
                metrics: Record<string, unknown>;
            }>;
        };
    };
    timezone: string;
};

const surface =
    'overflow-hidden rounded-xl border border-border bg-card shadow-[var(--shadow-card)]';

function money(currency: string, amount: number): string {
    return `${currency} ${amount.toLocaleString(undefined, {
        maximumFractionDigits: 0,
    })}`;
}

export default function ReportsIndex({ report }: { report: Report }) {
    const { sectors } = report;
    const maxLeads = Math.max(1, ...report.products.map((row) => row.leads));
    const maxConversion = Math.max(
        1,
        ...report.products.map((row) => row.conversion_rate),
    );
    const maxInterest = Math.max(
        1,
        ...report.products.map((row) => row.high_interest),
    );
    const maxDaily = Math.max(
        1,
        ...report.daily.map((row) => Math.max(row.leads, row.won)),
    );
    const maxSpendDay = Math.max(1, ...report.daily.map((row) => row.spend));
    const maxRevenueDay = Math.max(
        1,
        ...report.daily.map((row) => row.revenue),
    );

    return (
        <>
            <Head title="Reports" />
            <div className="mx-auto flex w-full max-w-[90rem] flex-col gap-6 p-4 md:gap-8 md:p-8">
                {/* Page header + range */}
                <div className="flex flex-col gap-5 border-b border-border pb-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                            Business reports
                        </h1>
                        <p className="mt-2 max-w-[65ch] text-sm leading-relaxed text-muted-foreground">
                            Detailed view across sales, billing, money,
                            messaging, AI, contacts, and spend. Range uses{' '}
                            {report.timezone}.
                        </p>
                    </div>

                    <Form
                        action="/reports"
                        method="get"
                        className="flex flex-wrap items-end gap-3"
                    >
                        {({ processing }) => (
                            <>
                                <label className="grid gap-1.5 text-sm">
                                    <span className="font-medium text-muted-foreground">
                                        From
                                    </span>
                                    <input
                                        type="date"
                                        name="from"
                                        defaultValue={report.filters.from}
                                        className="h-10 rounded-md border border-input bg-card px-3 text-foreground focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    />
                                </label>
                                <label className="grid gap-1.5 text-sm">
                                    <span className="font-medium text-muted-foreground">
                                        To
                                    </span>
                                    <input
                                        type="date"
                                        name="to"
                                        defaultValue={report.filters.to}
                                        className="h-10 rounded-md border border-input bg-card px-3 text-foreground focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    />
                                </label>
                                <Button type="submit" disabled={processing}>
                                    Apply range
                                </Button>
                                <div className="flex gap-2">
                                    <QuickRange days={7} label="7d" />
                                    <QuickRange days={30} label="30d" />
                                    <QuickRange days={90} label="90d" />
                                </div>
                            </>
                        )}
                    </Form>
                </div>

                {/* Section jump links */}
                <nav className="flex flex-wrap gap-2 text-sm">
                    {[
                        ['sales', 'Sales'],
                        ['billing', 'Billing'],
                        ['subscriptions', 'Subscriptions'],
                        ['money', 'Money'],
                        ['messaging', 'Messaging'],
                        ['ai', 'AI'],
                        ['follow-ups', 'Follow-ups'],
                        ['contacts', 'Contacts'],
                        ['spend', 'Spend'],
                        ['integrations', 'Integrations'],
                    ].map(([id, label]) => (
                        <a
                            key={id}
                            href={`#${id}`}
                            className="rounded-full border border-border bg-card px-3.5 py-1.5 font-medium text-primary transition-colors hover:bg-secondary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            {label}
                        </a>
                    ))}
                </nav>

                {/* Headline stats */}
                <section className="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
                    <Stat label="New leads" value={String(report.summary.leads)} tone="blue" />
                    <Stat label="Open pipeline" value={String(report.summary.open)} tone="lead" />
                    <Stat label="Won" value={String(report.summary.won)} tone="green" />
                    <Stat label="Conversion" value={`${report.summary.conversion_rate}%`} tone="lead" />
                    <Stat
                        label="Paid revenue"
                        value={money(report.summary.currency, report.summary.bought_revenue)}
                        tone="green"
                    />
                    <Stat
                        label="Net (rev − spend)"
                        value={money(report.summary.currency, report.summary.net_position)}
                        tone={report.summary.net_position >= 0 ? 'green' : 'red'}
                    />
                    <Stat
                        label="Unpaid invoices"
                        value={money(report.summary.currency, report.summary.unpaid_invoices)}
                        tone="red"
                    />
                    <Stat
                        label="Spend"
                        value={money(report.summary.currency, report.summary.spend)}
                        tone="red"
                    />
                    <Stat label="Messages" value={String(report.summary.messages)} tone="blue" />
                    <Stat label="AI reads" value={String(report.summary.ai_reads)} tone="lead" />
                    <Stat label="High interest" value={String(report.summary.high_interest)} tone="blue" />
                    <Stat label="Lost" value={String(report.summary.lost)} tone="red" />
                </section>

                <Sector id="sales" title="Sales & pipeline" hint="Leads opened in range, open stages, sources, demos.">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <MiniStat label="New in range" value={String(sectors.sales.new_in_range)} />
                        <MiniStat label="Open now" value={String(sectors.sales.open_leads)} />
                        <MiniStat label="Won in range" value={String(sectors.sales.won_in_range)} />
                        <MiniStat label="Lost in range" value={String(sectors.sales.lost_in_range)} />
                    </div>
                    <div className="mt-5 grid gap-4 lg:grid-cols-3">
                        <SolidPanel title="Leads per product">
                            <ProductBars rows={report.products} valueKey="leads" max={maxLeads} color="var(--laundry-blue)" />
                        </SolidPanel>
                        <SolidPanel title="Conversion per product">
                            <ProductBars rows={report.products} valueKey="conversion_rate" max={maxConversion} color="var(--success)" suffix="%" />
                        </SolidPanel>
                        <SolidPanel title="High interest per product">
                            <ProductBars rows={report.products} valueKey="high_interest" max={maxInterest} color="var(--lead)" />
                        </SolidPanel>
                    </div>
                    <div className="mt-5 grid gap-4 lg:grid-cols-3">
                        <SolidPanel title="Pipeline stages (open + range)">
                            <CountList
                                rows={sectors.sales.by_stage.map((row) => ({
                                    label: `${row.pipeline_name} · ${row.stage_name}`,
                                    total: row.total,
                                    meta: row.kind,
                                }))}
                            />
                        </SolidPanel>
                        <SolidPanel title="Lead sources">
                            <CountList
                                rows={sectors.sales.by_source.map((row) => ({
                                    label: row.source,
                                    total: row.total,
                                }))}
                            />
                        </SolidPanel>
                        <SolidPanel title="Demos in flight">
                            <div className="grid grid-cols-2 gap-3">
                                <MiniStat label="Demo booked" value={String(sectors.sales.demos.booked)} />
                                <MiniStat label="Evaluating" value={String(sectors.sales.demos.evaluating)} />
                            </div>
                        </SolidPanel>
                    </div>
                    <div className="mt-5 overflow-x-auto rounded-lg border border-border">
                        <table className="w-full min-w-[720px] text-sm">
                            <thead className="border-b border-border bg-secondary text-left text-xs font-semibold text-secondary-foreground">
                                <tr>
                                    <th className="px-5 py-3">Product</th>
                                    <th className="px-5 py-3">Leads</th>
                                    <th className="px-5 py-3">Open</th>
                                    <th className="px-5 py-3">Won</th>
                                    <th className="px-5 py-3">Lost</th>
                                    <th className="px-5 py-3">Conversion</th>
                                    <th className="px-5 py-3">High interest</th>
                                    <th className="px-5 py-3">Avg AI score</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {report.products.map((row) => (
                                    <tr key={row.product_id} className="bg-card hover:bg-muted">
                                        <td className="px-5 py-3.5 font-semibold">
                                            <Link href={`/leads?product_id=${row.product_id}`} className="text-primary hover:underline">
                                                {row.product_name}
                                            </Link>
                                        </td>
                                        <td className="px-5 py-3.5 tabular-nums">{row.leads}</td>
                                        <td className="px-5 py-3.5 tabular-nums">{row.open}</td>
                                        <td className="px-5 py-3.5 font-medium tabular-nums text-success">{row.won}</td>
                                        <td className="px-5 py-3.5 font-medium tabular-nums text-destructive">{row.lost}</td>
                                        <td className="px-5 py-3.5 tabular-nums">{row.conversion_rate}%</td>
                                        <td className="px-5 py-3.5 tabular-nums">{row.high_interest}</td>
                                        <td className="px-5 py-3.5 tabular-nums">{row.avg_score ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Sector>

                <Sector id="billing" title="Billing & revenue" hint="Invoices issued, paid, unpaid, overdue, and tax collected in range.">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <MiniStat label="Issued" value={`${sectors.billing.issued_count} · ${money(sectors.billing.currency, sectors.billing.issued_total)}`} />
                        <MiniStat label="Paid in range" value={money(sectors.billing.currency, sectors.billing.paid_revenue)} />
                        <MiniStat label="Unpaid open" value={`${sectors.billing.unpaid_count} · ${money(sectors.billing.currency, sectors.billing.unpaid_total)}`} />
                        <MiniStat label="Overdue" value={`${sectors.billing.overdue_count} · ${money(sectors.billing.currency, sectors.billing.overdue_total)}`} />
                        <MiniStat label="Tax logged" value={money(sectors.billing.currency, sectors.billing.tax_collected)} />
                        <MiniStat label="Drafts" value={String(sectors.billing.draft_count)} />
                        <MiniStat label="Paid invoices (all-time open set)" value={String(sectors.billing.paid_count)} />
                    </div>
                    <div className="mt-5">
                        <SolidPanel title="Invoices by status">
                            <CountList
                                rows={sectors.billing.by_status.map((row) => ({
                                    label: row.status,
                                    total: row.rows,
                                    meta: money(sectors.billing.currency, row.amount),
                                }))}
                            />
                        </SolidPanel>
                    </div>
                </Sector>

                <Sector id="subscriptions" title="Subscriptions" hint="Monthly customers, MRR, due soon, and collections in range.">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <MiniStat label="Active" value={String(sectors.subscriptions.active)} />
                        <MiniStat label="MRR" value={money(sectors.subscriptions.currency, sectors.subscriptions.mrr)} />
                        <MiniStat label="Due soon" value={String(sectors.subscriptions.due_soon)} />
                        <MiniStat label="Overdue" value={String(sectors.subscriptions.overdue)} />
                        <MiniStat label="Paused" value={String(sectors.subscriptions.paused)} />
                        <MiniStat label="Ended" value={String(sectors.subscriptions.ended)} />
                        <MiniStat label="Payments in range" value={String(sectors.subscriptions.payments_in_range)} />
                        <MiniStat label="Collected in range" value={money(sectors.subscriptions.currency, sectors.subscriptions.collected_in_range)} />
                    </div>
                </Sector>

                <Sector id="money" title="Money position" hint="Cash, people you owe / who owe you, and recurring monthly flows.">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <MiniStat label="Cash" value={money(sectors.money.currency, sectors.money.cash)} />
                        <MiniStat label="I owe" value={money(sectors.money.currency, sectors.money.i_owe)} />
                        <MiniStat label="Owed to me" value={money(sectors.money.currency, sectors.money.owed_to_me)} />
                        <MiniStat label="Net position" value={money(sectors.money.currency, sectors.money.net)} />
                        <MiniStat label="Monthly in" value={money(sectors.money.currency, sectors.money.monthly_in)} />
                        <MiniStat label="Monthly out" value={money(sectors.money.currency, sectors.money.monthly_out)} />
                    </div>
                    {sectors.money.accounts.length > 0 ? (
                        <ul className="mt-5 divide-y divide-border rounded-lg border border-border">
                            {sectors.money.accounts.map((account) => (
                                <li key={account.name} className="flex items-center justify-between gap-3 bg-card px-4 py-3 text-sm">
                                    <span>
                                        <span className="font-medium">{account.name}</span>
                                        <span className="ml-2 text-xs text-muted-foreground">
                                            {account.account_type}
                                            {account.is_active ? '' : ' · inactive'}
                                        </span>
                                    </span>
                                    <span className="font-semibold tabular-nums">
                                        {money(sectors.money.currency, account.balance)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    ) : null}
                </Sector>

                <Sector id="messaging" title="WhatsApp & messaging" hint="Inbound vs outbound traffic and active contacts in range.">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        <MiniStat label="Total messages" value={String(sectors.messaging.total)} />
                        <MiniStat label="Inbound" value={String(sectors.messaging.inbound)} />
                        <MiniStat label="Outbound" value={String(sectors.messaging.outbound)} />
                        <MiniStat label="Reply ratio" value={`${sectors.messaging.reply_ratio}%`} />
                        <MiniStat label="Active contacts" value={String(sectors.messaging.active_contacts)} />
                    </div>
                    <div className="mt-5">
                        <SolidPanel title="By channel">
                            <CountList
                                rows={sectors.messaging.by_channel.map((row) => ({
                                    label: row.channel,
                                    total: row.total,
                                }))}
                            />
                        </SolidPanel>
                    </div>
                </Sector>

                <Sector id="ai" title="AI intelligence" hint="Lead reads completed, failed, queued, and score quality.">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <MiniStat label="Completed reads" value={String(sectors.ai.completed)} />
                        <MiniStat label="Failed" value={String(sectors.ai.failed)} />
                        <MiniStat label="Unavailable" value={String(sectors.ai.unavailable)} />
                        <MiniStat label="Queued now" value={String(sectors.ai.queued)} />
                        <MiniStat label="Avg score" value={sectors.ai.avg_score != null ? String(sectors.ai.avg_score) : '—'} />
                        <MiniStat label="Score ≥ 70" value={String(sectors.ai.high_score)} />
                    </div>
                </Sector>

                <Sector id="follow-ups" title="Follow-ups & touches" hint="Tasks, overdue follow-ups, scheduled talks, and recorded interactions.">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <MiniStat label="Tasks in range" value={String(sectors.follow_ups.tasks_in_range)} />
                        <MiniStat label="Due today" value={String(sectors.follow_ups.due_today)} />
                        <MiniStat label="Overdue" value={String(sectors.follow_ups.overdue)} />
                        <MiniStat label="Scheduled talks" value={String(sectors.follow_ups.scheduled_talks)} />
                    </div>
                    <div className="mt-5 grid gap-4 lg:grid-cols-2">
                        <SolidPanel title="Task status">
                            <CountList rows={sectors.follow_ups.by_status.map((row) => ({ label: row.status, total: row.total }))} />
                        </SolidPanel>
                        <SolidPanel title="Interactions logged">
                            <CountList rows={sectors.follow_ups.interactions.map((row) => ({ label: row.type, total: row.total }))} />
                        </SolidPanel>
                    </div>
                </Sector>

                <Sector id="contacts" title="Contacts & markets" hint="Where clients sit by country, conversation area, and consent.">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <MiniStat label="Contacts" value={String(sectors.contacts.total)} />
                        <MiniStat label="Opted in" value={String(sectors.contacts.opted_in)} />
                        <MiniStat label="Opted out" value={String(sectors.contacts.opted_out)} />
                        <MiniStat label="Unknown consent" value={String(sectors.contacts.unknown_consent)} />
                    </div>
                    <div className="mt-5 grid gap-4 lg:grid-cols-2">
                        <SolidPanel title="By country">
                            <CountList
                                rows={sectors.contacts.by_country.map((row) => ({
                                    label: `${row.country} (${row.country_code})`,
                                    total: row.total,
                                }))}
                            />
                        </SolidPanel>
                        <SolidPanel title="Conversation areas">
                            <CountList
                                rows={sectors.contacts.by_area.map((row) => ({
                                    label: row.area,
                                    total: row.total,
                                }))}
                            />
                        </SolidPanel>
                    </div>
                </Sector>

                <Sector id="spend" title="Spend" hint="Every expense day and category totals in range.">
                    <div className="grid gap-4 lg:grid-cols-2">
                        <SolidPanel title="Spend · every day">
                            <div className="mb-3 flex items-center justify-between text-sm">
                                <Legend color="var(--destructive)" label="Spend" />
                                <span className="font-semibold tabular-nums">
                                    {money(report.spend.currency, report.spend.total)}
                                </span>
                            </div>
                            <DayBars
                                days={report.daily}
                                valueKey="spend"
                                max={maxSpendDay}
                                color="var(--destructive)"
                                format={(day) => money(report.spend.currency, day.spend)}
                            />
                            {report.spend.by_category.length > 0 ? (
                                <ul className="mt-4 space-y-2.5 border-t border-border pt-4">
                                    {report.spend.by_category.map((row) => (
                                        <li key={row.category} className="flex items-center justify-between text-sm">
                                            <span className="capitalize text-muted-foreground">
                                                {row.category} · {row.rows}
                                            </span>
                                            <span className="font-semibold tabular-nums">
                                                {money(report.spend.currency, row.gross)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            ) : null}
                        </SolidPanel>
                        <SolidPanel title="Leads, won & revenue · every day">
                            <div className="mb-3 flex flex-wrap gap-4">
                                <Legend color="var(--laundry-blue)" label="Leads" />
                                <Legend color="var(--success)" label="Won" />
                                <Legend color="var(--lead)" label="Revenue" />
                            </div>
                            <div className="flex h-40 items-end gap-1 overflow-x-auto pb-1">
                                {report.daily.map((day) => (
                                    <div
                                        key={day.date}
                                        className="flex min-w-7 flex-1 flex-col items-center gap-1"
                                        title={`${day.date}: ${day.leads} leads, ${day.won} won, ${money(report.summary.currency, day.revenue)}`}
                                    >
                                        <div className="flex h-28 w-full items-end justify-center gap-0.5">
                                            <div
                                                className="min-h-0.5 w-[30%] rounded-t-sm"
                                                style={{
                                                    height: `${(day.leads / maxDaily) * 100}%`,
                                                    background: 'var(--laundry-blue)',
                                                }}
                                            />
                                            <div
                                                className="min-h-0.5 w-[30%] rounded-t-sm"
                                                style={{
                                                    height: `${(day.won / maxDaily) * 100}%`,
                                                    background: 'var(--success)',
                                                }}
                                            />
                                            <div
                                                className="min-h-0.5 w-[30%] rounded-t-sm"
                                                style={{
                                                    height: `${(day.revenue / maxRevenueDay) * 100}%`,
                                                    background: 'var(--lead)',
                                                }}
                                            />
                                        </div>
                                        <span className="text-[10px] text-muted-foreground">
                                            {day.label}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </SolidPanel>
                    </div>
                </Sector>

                <Sector id="integrations" title="Integrations" hint="Latest Meta / Search Console sync snapshots.">
                    {sectors.integrations.reports.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No integration reports yet. Sync from{' '}
                            <Link href="/integrations" className="font-medium text-primary underline underline-offset-4">
                                Integrations
                            </Link>
                            .
                        </p>
                    ) : (
                        <ul className="space-y-4">
                            {sectors.integrations.reports.map((row) => (
                                <li key={row.id} className="rounded-lg border border-border bg-card p-4 text-sm">
                                    <div className="font-semibold">
                                        {row.provider}
                                        {row.label ? ` · ${row.label}` : ''} · {row.report_type}
                                    </div>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        {row.period_start} → {row.period_end}
                                    </p>
                                    <pre className="mt-3 overflow-x-auto rounded-md bg-muted p-3 font-mono text-xs text-foreground">
                                        {JSON.stringify(row.metrics, null, 2)}
                                    </pre>
                                </li>
                            ))}
                        </ul>
                    )}
                </Sector>
            </div>
        </>
    );
}

function Sector({
    id,
    title,
    hint,
    children,
}: {
    id: string;
    title: string;
    hint: string;
    children: ReactNode;
}) {
    return (
        <section id={id} className={`scroll-mt-20 ${surface}`}>
            <div className="border-b border-border bg-card px-5 py-4">
                <h2 className="text-lg font-semibold tracking-tight text-foreground">
                    {title}
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">{hint}</p>
            </div>
            <div className="bg-card px-5 py-5">{children}</div>
        </section>
    );
}

function QuickRange({ days, label }: { days: number; label: string }) {
    const to = new Date();
    const from = new Date();
    from.setDate(to.getDate() - (days - 1));
    const fmt = (d: Date) => d.toISOString().slice(0, 10);

    return (
        <Button variant="outline" asChild>
            <Link href={`/reports?from=${fmt(from)}&to=${fmt(to)}`}>
                {label}
            </Link>
        </Button>
    );
}

function Stat({
    label,
    value,
    tone,
}: {
    label: string;
    value: string;
    tone: 'blue' | 'green' | 'red' | 'lead';
}) {
    const fill =
        tone === 'blue'
            ? 'bg-primary text-primary-foreground'
            : tone === 'green'
              ? 'bg-success text-success-foreground'
              : tone === 'red'
                ? 'bg-destructive text-white'
                : 'bg-lead text-lead-foreground';

    return (
        <div className={`rounded-xl px-4 py-5 shadow-[var(--shadow-card)] ${fill}`}>
            <p className="text-sm font-medium opacity-90">{label}</p>
            <p className="mt-1.5 text-xl font-semibold tracking-tight tabular-nums md:text-2xl">
                {value}
            </p>
        </div>
    );
}

function MiniStat({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border border-border bg-muted px-4 py-3">
            <p className="text-xs font-medium text-muted-foreground">
                {label}
            </p>
            <p className="mt-1 text-base font-semibold tabular-nums">{value}</p>
        </div>
    );
}

function SolidPanel({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <div className="overflow-hidden rounded-lg border border-border bg-card">
            <div className="border-b border-border bg-secondary px-4 py-2.5">
                <h3 className="text-sm font-semibold text-secondary-foreground">
                    {title}
                </h3>
            </div>
            <div className="px-4 py-4">{children}</div>
        </div>
    );
}

function ProductBars({
    rows,
    valueKey,
    max,
    color,
    suffix = '',
}: {
    rows: ProductRow[];
    valueKey: 'leads' | 'conversion_rate' | 'high_interest';
    max: number;
    color: string;
    suffix?: string;
}) {
    if (rows.length === 0) {
        return <p className="text-sm text-muted-foreground">No product data.</p>;
    }

    return (
        <ul className="space-y-4">
            {rows.map((row) => {
                const value = row[valueKey];
                const width = Math.max(4, Math.round((value / max) * 100));

                return (
                    <li key={`${valueKey}-${row.product_id}`}>
                        <div className="mb-1.5 flex items-baseline justify-between gap-2 text-sm">
                            <span className="truncate font-medium">
                                {row.product_name}
                            </span>
                            <span className="font-semibold tabular-nums">
                                {value}
                                {suffix}
                            </span>
                        </div>
                        <div className="h-2.5 w-full overflow-hidden rounded-full bg-lead-soft">
                            <div
                                className="h-full rounded-full"
                                style={{ width: `${width}%`, background: color }}
                            />
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}

function CountList({
    rows,
}: {
    rows: Array<{ label: string; total: number; meta?: string }>;
}) {
    if (rows.length === 0) {
        return <p className="text-sm text-muted-foreground">Nothing in this range.</p>;
    }

    return (
        <ul className="divide-y divide-border">
            {rows.map((row) => (
                <li
                    key={`${row.label}-${row.total}-${row.meta ?? ''}`}
                    className="flex items-center justify-between gap-3 py-2.5 text-sm first:pt-0 last:pb-0"
                >
                    <span className="min-w-0 truncate">
                        {row.label}
                        {row.meta ? (
                            <span className="ml-2 text-xs text-muted-foreground">
                                {row.meta}
                            </span>
                        ) : null}
                    </span>
                    <span className="font-semibold tabular-nums">{row.total}</span>
                </li>
            ))}
        </ul>
    );
}

function DayBars({
    days,
    valueKey,
    max,
    color,
    format,
}: {
    days: DayRow[];
    valueKey: 'spend' | 'messages' | 'revenue';
    max: number;
    color: string;
    format: (day: DayRow) => string;
}) {
    return (
        <div className="flex h-40 items-end gap-1 overflow-x-auto pb-1">
            {days.map((day) => (
                <div
                    key={day.date}
                    className="flex min-w-7 flex-1 flex-col items-center gap-1"
                    title={`${day.date}: ${format(day)}`}
                >
                    <div className="flex h-28 w-full items-end">
                        <div
                            className="min-h-0.5 w-full rounded-t-sm"
                            style={{
                                height: `${(day[valueKey] / max) * 100}%`,
                                background: color,
                            }}
                        />
                    </div>
                    <span className="text-[10px] text-muted-foreground">
                        {day.label}
                    </span>
                </div>
            ))}
        </div>
    );
}

function Legend({ color, label }: { color: string; label: string }) {
    return (
        <span className="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
            <span
                className="inline-block size-2.5 rounded-sm"
                style={{ background: color }}
            />
            {label}
        </span>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [{ title: 'Reports', href: '/reports' }],
};