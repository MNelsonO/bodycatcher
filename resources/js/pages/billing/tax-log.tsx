import { Form, Head, Link } from '@inertiajs/react';
import {
    Page,
    PageHeader,
    Surface,
    SurfaceBody,
    SurfaceHeader,
} from '@/components/crm/page-frame';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { Paginator } from '@/types/crm';

type Entry = {
    id: number;
    source: string;
    system_name: string;
    description: string;
    contact_name: string | null;
    net_amount: number;
    tax_amount: number;
    gross_amount: number;
    occurred_on: string | null;
};

export default function TaxLogPage({
    entries,
    totals,
    systems,
    filters,
}: {
    entries: Paginator<Entry>;
    totals: {
        net_amount: number;
        tax_amount: number;
        gross_amount: number;
    };
    systems: string[];
    filters: { system: string };
}) {
    return (
        <>
            <Head title="Tax log" />
            <Page>
                <PageHeader
                    title="Tax log"
                    description="Tax collected from every billing system event. Paid invoices write a row automatically."
                    actions={
                        <Button variant="outline" asChild>
                            <Link href="/invoices">Invoices</Link>
                        </Button>
                    }
                />

                <div className="grid gap-3 sm:grid-cols-3">
                    <Surface>
                        <SurfaceBody>
                            <p className="text-xs text-muted-foreground">Net</p>
                            <p className="text-2xl tabular-nums">
                                {totals.net_amount.toFixed(2)}
                            </p>
                        </SurfaceBody>
                    </Surface>
                    <Surface>
                        <SurfaceBody>
                            <p className="text-xs text-muted-foreground">Tax</p>
                            <p className="text-2xl tabular-nums">
                                {totals.tax_amount.toFixed(2)}
                            </p>
                        </SurfaceBody>
                    </Surface>
                    <Surface>
                        <SurfaceBody>
                            <p className="text-xs text-muted-foreground">
                                Gross
                            </p>
                            <p className="text-2xl tabular-nums">
                                {totals.gross_amount.toFixed(2)}
                            </p>
                        </SurfaceBody>
                    </Surface>
                </div>

                <Form
                    action="/billing/tax-log"
                    method="get"
                    className="flex flex-wrap items-end gap-3"
                >
                    {({ processing }) => (
                        <>
                            <label className="grid gap-1 text-sm">
                                <span className="text-muted-foreground">
                                    System
                                </span>
                                <select
                                    name="system"
                                    defaultValue={filters.system || 'all'}
                                    className="h-9 rounded-md border border-border bg-background px-3"
                                >
                                    <option value="all">All systems</option>
                                    {systems.map((system) => (
                                        <option key={system} value={system}>
                                            {system}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <Button type="submit" disabled={processing}>
                                Filter
                            </Button>
                        </>
                    )}
                </Form>

                {entries.data.length === 0 ? (
                    <Surface>
                        <SurfaceBody className="text-sm text-muted-foreground">
                            No tax rows yet. Mark an invoice paid to log tax.
                        </SurfaceBody>
                    </Surface>
                ) : (
                    <Surface>
                        <SurfaceHeader title="Entries" />
                        <SurfaceBody className="p-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Date</TableHead>
                                        <TableHead>System</TableHead>
                                        <TableHead>Description</TableHead>
                                        <TableHead>Customer</TableHead>
                                        <TableHead className="text-right">
                                            Tax
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Gross
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {entries.data.map((entry) => (
                                        <TableRow key={entry.id}>
                                            <TableCell>
                                                {entry.occurred_on}
                                            </TableCell>
                                            <TableCell>
                                                {entry.system_name}
                                            </TableCell>
                                            <TableCell>
                                                {entry.description}
                                            </TableCell>
                                            <TableCell>
                                                {entry.contact_name ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {entry.tax_amount.toFixed(2)}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {entry.gross_amount.toFixed(2)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </SurfaceBody>
                    </Surface>
                )}
            </Page>
        </>
    );
}

TaxLogPage.layout = {
    breadcrumbs: [
        { title: 'Invoices', href: '/invoices' },
        { title: 'Tax log', href: '/billing/tax-log' },
    ],
};
