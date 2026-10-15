import { Form, Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';

type ProductRow = {
    product_id: number;
    product_name: string;
    leads: number;
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
    spend: number;
};

type Report = {
    filters: { from: string; to: string };
    summary: {
        leads: number;
        won: number;
        conversion_rate: number;
        high_interest: number;
        spend: number;
        bought_revenue: number;
        currency: string;
    };
    products: ProductRow[];
    daily: DayRow[];
    spend: {
        total: number;
        currency: string;
        by_category: Array<{ category: string; gross: number; rows: number }>;
    };
    timezone: string;
};

function money(currency: string, amount: number): string {
    return `${currency} ${amount.toLocaleString(undefined, {
        maximumFractionDigits: 0,
    })}`;
}

export default function ReportsIndex({ report }: { report: Report }) {
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

    return (
        <>
            <Head title="Reports" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight text-primary">
                            Reports
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Leads, conversion, high interest per product, and
                            spend by day. Range uses {report.timezone}.
                        </p>
                    </div>

                    <Form
                        action="/reports"
                        method="get"
                        className="flex flex-wrap items-end gap-3"
                    >
                        {({ processing }) => (
                            <>
                                <label className="grid gap-1 text-sm">
                                    <span className="text-muted-foreground">
                                        From
                                    </span>
                                    <input
                                        type="date"
                                        name="from"
                                        defaultValue={report.filters.from}
                                        className="h-9 rounded-md border bg-background px-3"
                                    />
                                </label>
                                <label className="grid gap-1 text-sm">
                                    <span className="text-muted-foreground">
                                        To
                                    </span>
                                    <input
                                        type="date"
                                        name="to"
                                        defaultValue={report.filters.to}
                                        className="h-9 rounded-md border bg-background px-3"
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

                <section className="grid grid-cols-2 gap-3 lg:grid-cols-5">
                    <Stat
                        label="Leads"
                        value={String(report.summary.leads)}
                        tone="blue"
                    />
                    <Stat
                        label="Won"
                        value={String(report.summary.won)}
                        tone="green"
                    />
                    <Stat
                        label="Conversion"
                        value={`${report.summary.conversion_rate}%`}
                        tone="lead"
                    />
                    <Stat
                        label="High interest"
                        value={String(report.summary.high_interest)}
                        tone="blue"
                    />
                    <Stat
                        label="Spend"
                        value={money(
                            report.summary.currency,
                            report.summary.spend,
                        )}
                        tone="red"
                    />
                </section>

                <section className="grid gap-3 lg:grid-cols-3">
                    <SolidPanel title="Leads per product">
                        <ProductBars
                            rows={report.products}
                            valueKey="leads"
                            max={maxLeads}
                            color="var(--laundry-blue)"
                        />
                    </SolidPanel>
                    <SolidPanel title="Conversion per product">
                        <ProductBars
                            rows={report.products}
                            valueKey="conversion_rate"
                            max={maxConversion}
                            color="var(--success)"
                            suffix="%"
                        />
                    </SolidPanel>
                    <SolidPanel title="High interest per product">
                        <ProductBars
                            rows={report.products}
                            valueKey="high_interest"
                            max={maxInterest}
                            color="var(--lead)"
                        />
                    </SolidPanel>
                </section>

                <section className="overflow-hidden bg-laundry-white shadow-[0_0_0_2px_var(--laundry-blue)]">
                    <div className="border-b border-primary/10 px-4 py-3">
                        <h2 className="font-semibold text-primary">
                            Product table
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            High interest = Interested / Demo / Proposal /
                            Negotiation, or AI score ≥ 70.
                        </p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[640px] text-sm">
                            <thead className="bg-laundry-soft/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3">Product</th>
                                    <th className="px-4 py-3">Leads</th>
                                    <th className="px-4 py-3">Won</th>
                                    <th className="px-4 py-3">Lost</th>
                                    <th className="px-4 py-3">Conversion</th>
                                    <th className="px-4 py-3">High interest</th>
                                    <th className="px-4 py-3">Avg score</th>
                                </tr>
                            </thead>
                            <tbody>
                                {report.products.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="px-4 py-8 text-muted-foreground"
                                        >
                                            No products yet.
                                        </td>
                                    </tr>
                                ) : (
                                    report.products.map((row) => (
                                        <tr
                                            key={row.product_id}
                                            className="border-t border-primary/10"
                                        >
                                            <td className="px-4 py-3 font-medium">
                                                <Link
                                                    href={`/leads?product_id=${row.product_id}`}
                                                    className="text-primary hover:underline"
                                                >
                                                    {row.product_name}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3 tabular-nums">
                                                {row.leads}
                                            </td>
                                            <td className="px-4 py-3 tabular-nums text-success">
                                                {row.won}
                                            </td>
                                            <td className="px-4 py-3 tabular-nums text-destructive">
                                                {row.lost}
                                            </td>
                                            <td className="px-4 py-3 tabular-nums">
                                                {row.conversion_rate}%
                                            </td>
                                            <td className="px-4 py-3 tabular-nums">
                                                {row.high_interest}
                                            </td>
                                            <td className="px-4 py-3 tabular-nums">
                                                {row.avg_score ?? '—'}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="grid gap-3 lg:grid-cols-2">
                    <SolidPanel title="Leads & won · every day">
                        <div className="mb-3 flex gap-3 text-[10px] font-semibold tracking-wide uppercase">
                            <Legend color="var(--laundry-blue)" label="Leads" />
                            <Legend color="var(--success)" label="Won" />
                        </div>
                        <div className="flex h-40 items-end gap-1 overflow-x-auto pb-1">
                            {report.daily.map((day) => (
                                <div
                                    key={day.date}
                                    className="flex min-w-7 flex-1 flex-col items-center gap-1"
                                    title={`${day.date}: ${day.leads} leads, ${day.won} won`}
                                >
                                    <div className="flex h-28 w-full items-end justify-center gap-0.5">
                                        <div
                                            className="w-[40%] min-h-0.5"
                                            style={{
                                                height: `${(day.leads / maxDaily) * 100}%`,
                                                background: 'var(--laundry-blue)',
                                            }}
                                        />
                                        <div
                                            className="w-[40%] min-h-0.5"
                                            style={{
                                                height: `${(day.won / maxDaily) * 100}%`,
                                                background: 'var(--success)',
                                            }}
                                        />
                                    </div>
                                    <span className="text-[9px] text-muted-foreground">
                                        {day.label}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </SolidPanel>

                    <SolidPanel title="Spend · every day">
                        <div className="mb-3 flex items-center justify-between text-sm">
                            <Legend color="var(--destructive)" label="Spend" />
                            <span className="font-semibold tabular-nums">
                                {money(report.spend.currency, report.spend.total)}
                            </span>
                        </div>
                        <div className="flex h-40 items-end gap-1 overflow-x-auto pb-1">
                            {report.daily.map((day) => (
                                <div
                                    key={day.date}
                                    className="flex min-w-7 flex-1 flex-col items-center gap-1"
                                    title={`${day.date}: ${money(report.spend.currency, day.spend)}`}
                                >
                                    <div className="flex h-28 w-full items-end">
                                        <div
                                            className="w-full min-h-0.5"
                                            style={{
                                                height: `${(day.spend / maxSpendDay) * 100}%`,
                                                background: 'var(--destructive)',
                                            }}
                                        />
                                    </div>
                                    <span className="text-[9px] text-muted-foreground">
                                        {day.label}
                                    </span>
                                </div>
                            ))}
                        </div>
                        {report.spend.by_category.length > 0 ? (
                            <ul className="mt-4 space-y-2 border-t border-primary/10 pt-3">
                                {report.spend.by_category.map((row) => (
                                    <li
                                        key={row.category}
                                        className="flex items-center justify-between text-sm"
                                    >
                                        <span className="capitalize text-muted-foreground">
                                            {row.category} · {row.rows}
                                        </span>
                                        <span className="font-semibold tabular-nums">
                                            {money(
                                                report.spend.currency,
                                                row.gross,
                                            )}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        ) : null}
                    </SolidPanel>
                </section>

                <p className="text-xs text-muted-foreground">
                    Bought revenue in range:{' '}
                    <span className="font-semibold text-success">
                        {money(
                            report.summary.currency,
                            report.summary.bought_revenue,
                        )}
                    </span>
                </p>
            </div>
        </>
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
        <div className={`px-4 py-5 ${fill}`}>
            <p className="text-[10px] font-semibold tracking-[0.14em] uppercase opacity-80">
                {label}
            </p>
            <p className="mt-1 text-2xl font-semibold tabular-nums md:text-3xl">
                {value}
            </p>
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
        <div className="bg-laundry-white shadow-[0_0_0_2px_var(--laundry-blue)]">
            <div className="border-b border-primary/10 px-4 py-3">
                <h2 className="font-semibold text-primary">{title}</h2>
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
        return (
            <p className="text-sm text-muted-foreground">No product data.</p>
        );
    }

    return (
        <ul className="space-y-3">
            {rows.map((row) => {
                const value = row[valueKey];
                const width = Math.max(4, Math.round((value / max) * 100));

                return (
                    <li key={`${valueKey}-${row.product_id}`}>
                        <div className="mb-1 flex items-baseline justify-between gap-2 text-sm">
                            <span className="truncate font-medium">
                                {row.product_name}
                            </span>
                            <span className="tabular-nums font-semibold">
                                {value}
                                {suffix}
                            </span>
                        </div>
                        <div className="h-2.5 w-full bg-lead-soft">
                            <div
                                className="h-full"
                                style={{ width: `${width}%`, background: color }}
                            />
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}

function Legend({ color, label }: { color: string; label: string }) {
    return (
        <span className="inline-flex items-center gap-1.5 text-[10px] font-semibold tracking-wide uppercase">
            <span className="inline-block size-2.5" style={{ background: color }} />
            {label}
        </span>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [{ title: 'Reports', href: '/reports' }],
};
