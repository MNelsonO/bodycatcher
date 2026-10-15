import { Form, Head, Link } from '@inertiajs/react';
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

type Invoice = {
    id: number;
    number: string;
    status: string;
    issue_date: string | null;
    due_date: string | null;
    currency: string;
    subtotal: number;
    tax_total: number;
    total: number;
    notes: string | null;
    customer_address: string | null;
    share_token: string;
    contact: {
        id: number;
        full_name: string;
        business_name: string | null;
        email: string | null;
        primary_phone: string;
        location: string | null;
    };
    items: Array<{
        id: number;
        description: string;
        quantity: number;
        unit_price: number;
        tax_rate: number;
        line_total: number;
    }>;
};

type Business = {
    legal_name: string;
    trading_name: string | null;
    address_lines: string[];
    phone: string | null;
    email: string | null;
    tax_id: string | null;
    bank_details: string | null;
    logo_url: string | null;
};

export default function InvoiceShow({
    invoice,
    business,
    share_url,
}: {
    invoice: Invoice;
    business: Business;
    share_url: string;
}) {
    const copyShare = async () => {
        await navigator.clipboard.writeText(share_url);
    };

    return (
        <>
            <Head title={invoice.number} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            {business.trading_name || business.legal_name}
                        </p>
                        <h1 className="text-xl font-semibold tracking-tight">
                            {invoice.number}
                        </h1>
                        <div className="mt-2 flex flex-wrap gap-2">
                            <Badge>{invoice.status}</Badge>
                            <Badge variant="outline">
                                Due {invoice.due_date}
                            </Badge>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <a href={`/invoices/${invoice.id}/pdf`}>
                                Download PDF
                            </a>
                        </Button>
                        <Button variant="outline" asChild>
                            <a href={share_url} target="_blank" rel="noreferrer">
                                Open share page
                            </a>
                        </Button>
                        <Button variant="outline" type="button" onClick={copyShare}>
                            Copy share link
                        </Button>
                        {invoice.status !== 'paid' ? (
                            <Form
                                action={`/invoices/${invoice.id}/mark-paid`}
                                method="post"
                            >
                                <Button type="submit">Mark paid</Button>
                            </Form>
                        ) : (
                            <Form
                                action={`/invoices/${invoice.id}/mark-unpaid`}
                                method="post"
                            >
                                <Button type="submit" variant="outline">
                                    Mark unpaid
                                </Button>
                            </Form>
                        )}
                        {invoice.status === 'draft' ? (
                            <Form
                                action={`/invoices/${invoice.id}/mark-sent`}
                                method="post"
                            >
                                <Button type="submit" variant="outline">
                                    Mark sent / unpaid
                                </Button>
                            </Form>
                        ) : null}
                        <Button variant="ghost" asChild>
                            <Link href="/invoices">All invoices</Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <section className="rounded-lg border p-4 text-sm">
                        <h2 className="font-medium">From</h2>
                        {business.logo_url ? (
                            <img
                                src={business.logo_url}
                                alt="Logo"
                                className="mt-2 max-h-16"
                            />
                        ) : null}
                        <p className="mt-2 font-medium">
                            {business.trading_name || business.legal_name}
                        </p>
                        {business.address_lines.map((line) => (
                            <p key={line} className="text-muted-foreground">
                                {line}
                            </p>
                        ))}
                        {business.tax_id ? (
                            <p className="text-muted-foreground">
                                Tax ID: {business.tax_id}
                            </p>
                        ) : null}
                    </section>
                    <section className="rounded-lg border p-4 text-sm">
                        <h2 className="font-medium">Bill to</h2>
                        <p className="mt-2 font-medium">
                            {invoice.contact.full_name}
                        </p>
                        {invoice.contact.business_name ? (
                            <p>{invoice.contact.business_name}</p>
                        ) : null}
                        <p className="text-muted-foreground">
                            {invoice.customer_address ||
                                invoice.contact.location ||
                                'No address'}
                        </p>
                        <p className="text-muted-foreground">
                            {invoice.contact.primary_phone}
                        </p>
                        {invoice.contact.email ? (
                            <p className="text-muted-foreground">
                                {invoice.contact.email}
                            </p>
                        ) : null}
                    </section>
                </div>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Description</TableHead>
                            <TableHead className="text-right">Qty</TableHead>
                            <TableHead className="text-right">Unit</TableHead>
                            <TableHead className="text-right">Tax %</TableHead>
                            <TableHead className="text-right">Total</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {invoice.items.map((item) => (
                            <TableRow key={item.id}>
                                <TableCell>{item.description}</TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {item.quantity.toFixed(2)}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {item.unit_price.toFixed(2)}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {item.tax_rate.toFixed(2)}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {item.line_total.toFixed(2)}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>

                <div className="ml-auto w-full max-w-xs space-y-1 text-sm tabular-nums">
                    <div className="flex justify-between text-muted-foreground">
                        <span>Subtotal</span>
                        <span>
                            {invoice.currency} {invoice.subtotal.toFixed(2)}
                        </span>
                    </div>
                    <div className="flex justify-between text-muted-foreground">
                        <span>Tax</span>
                        <span>
                            {invoice.currency} {invoice.tax_total.toFixed(2)}
                        </span>
                    </div>
                    <div className="flex justify-between text-base font-semibold">
                        <span>Total</span>
                        <span>
                            {invoice.currency} {invoice.total.toFixed(2)}
                        </span>
                    </div>
                </div>

                {invoice.notes ? (
                    <p className="text-sm text-muted-foreground">
                        Notes: {invoice.notes}
                    </p>
                ) : null}

                <p className="break-all text-xs text-muted-foreground">
                    Share link: {share_url}
                </p>
            </div>
        </>
    );
}

InvoiceShow.layout = {
    breadcrumbs: [
        { title: 'Invoices', href: '/invoices' },
        { title: 'Invoice', href: '#' },
    ],
};
