import { Form, Head, Link } from '@inertiajs/react';
import {
    Page,
    PageHeader,
    Surface,
    SurfaceBody,
    SurfaceHeader,
} from '@/components/crm/page-frame';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { invoiceStatusTone } from '@/lib/status-tone';
import type { Paginator } from '@/types/crm';

type InvoiceRow = {
    id: number;
    number: string;
    contact_name: string;
    business_name: string | null;
    status: string;
    issue_date: string | null;
    due_date: string | null;
    total: number;
    currency: string;
    share_token: string;
};

type Alerts = {
    overdue_invoices: Array<{
        id: number;
        number: string;
        contact_name: string;
        due_date: string | null;
        total: number;
        currency: string;
    }>;
    unpaid_invoices: Array<{
        id: number;
        number: string;
        contact_name: string;
        due_date: string | null;
        total: number;
        currency: string;
    }>;
    due_subscriptions: Array<{
        id: number;
        label: string;
        contact_name: string;
        next_due_on: string | null;
        overdue: boolean;
        amount: number;
    }>;
};

export default function InvoicesIndex({
    invoices,
    filters,
    alerts,
}: {
    invoices: Paginator<InvoiceRow>;
    filters: { status: string };
    alerts: Alerts;
}) {
    return (
        <>
            <Head title="Invoices" />
            <Page>
                <PageHeader
                    title="Invoices"
                    description="Create, share, download, and track paid vs unpaid."
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href="/billing/subscriptions">
                                    Monthly customers
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href="/billing/tax-log">Tax log</Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href="/billing/profile">
                                    Logo & address
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link href="/invoices/create">New invoice</Link>
                            </Button>
                        </>
                    }
                />

                {(alerts.overdue_invoices.length > 0 ||
                    alerts.due_subscriptions.length > 0) && (
                    <Surface>
                        <SurfaceHeader title="Collection alerts" />
                        <SurfaceBody className="grid gap-3 md:grid-cols-2">
                            <div>
                                <h3 className="text-sm font-medium">
                                    Overdue invoices
                                </h3>
                                <ul className="mt-2 space-y-1 text-sm">
                                    {alerts.overdue_invoices.length === 0 ? (
                                        <li className="text-muted-foreground">
                                            None
                                        </li>
                                    ) : (
                                        alerts.overdue_invoices.map((row) => (
                                            <li key={row.id}>
                                                <Link
                                                    href={`/invoices/${row.id}`}
                                                    className="hover:underline"
                                                >
                                                    {row.number} ·{' '}
                                                    {row.contact_name} · due{' '}
                                                    {row.due_date}
                                                </Link>
                                            </li>
                                        ))
                                    )}
                                </ul>
                            </div>
                            <div>
                                <h3 className="text-sm font-medium">
                                    Monthly due / issues
                                </h3>
                                <ul className="mt-2 space-y-1 text-sm">
                                    {alerts.due_subscriptions.length === 0 ? (
                                        <li className="text-muted-foreground">
                                            None
                                        </li>
                                    ) : (
                                        alerts.due_subscriptions.map((row) => (
                                            <li key={row.id}>
                                                {row.label} · {row.contact_name}{' '}
                                                ·{' '}
                                                {row.overdue
                                                    ? 'OVERDUE'
                                                    : 'due'}{' '}
                                                {row.next_due_on}
                                            </li>
                                        ))
                                    )}
                                </ul>
                            </div>
                        </SurfaceBody>
                    </Surface>
                )}

                <Form
                    action="/invoices"
                    method="get"
                    className="flex flex-wrap items-end gap-3"
                >
                    {({ processing }) => (
                        <>
                            <label className="grid gap-1 text-sm">
                                <span className="text-muted-foreground">
                                    Status
                                </span>
                                <select
                                    name="status"
                                    defaultValue={filters.status || 'all'}
                                    className="h-9 rounded-md border border-border bg-background px-3"
                                >
                                    <option value="all">All</option>
                                    <option value="draft">Draft</option>
                                    <option value="unpaid">Unpaid</option>
                                    <option value="overdue">Overdue</option>
                                    <option value="paid">Paid</option>
                                    <option value="void">Void</option>
                                </select>
                            </label>
                            <Button type="submit" disabled={processing}>
                                Filter
                            </Button>
                        </>
                    )}
                </Form>

                {invoices.data.length === 0 ? (
                    <Surface>
                        <SurfaceBody className="border-dashed text-sm text-muted-foreground">
                            No invoices yet. Create one to bill a contact.
                        </SurfaceBody>
                    </Surface>
                ) : (
                    <Surface>
                        <SurfaceBody className="p-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Number</TableHead>
                                        <TableHead>Customer</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Due</TableHead>
                                        <TableHead className="text-right">
                                            Total
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {invoices.data.map((invoice) => (
                                        <TableRow key={invoice.id}>
                                            <TableCell>
                                                <Link
                                                    href={`/invoices/${invoice.id}`}
                                                    className="font-medium hover:underline"
                                                >
                                                    {invoice.number}
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                {invoice.contact_name}
                                                {invoice.business_name
                                                    ? ` · ${invoice.business_name}`
                                                    : ''}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={invoiceStatusTone(
                                                        invoice.status,
                                                    )}
                                                >
                                                    {invoice.status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {invoice.due_date}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {invoice.currency}{' '}
                                                {invoice.total.toFixed(2)}
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

InvoicesIndex.layout = {
    breadcrumbs: [{ title: 'Invoices', href: '/invoices' }],
};
