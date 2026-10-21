import { Form, Head, Link } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { AreaField, SelectField, TextField } from '@/components/crm/fields';
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

type Line = {
    description: string;
    quantity: string;
    unit_price: string;
    tax_rate: string;
};

type Generation = {
    id: number;
    prompt: string;
    status: string;
    result: {
        summary?: string | null;
        customer_address?: string | null;
        notes?: string | null;
        terms_and_conditions?: string | null;
        currency?: string | null;
        due_date?: string | null;
        items?: Array<{
            description: string;
            quantity: number;
            unit_price: number;
            tax_rate: number;
        }>;
    } | null;
    error: string | null;
    applied_at: string | null;
    completed_at: string | null;
    from_generation_id: number | null;
    created_at: string | null;
};

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
    terms_and_conditions: string | null;
    customer_address: string | null;
    share_token: string;
    paid_on: string | null;
    payment_method: string | null;
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
    payment_methods,
    defaults,
    llm_ready,
    generations,
}: {
    invoice: Invoice;
    business: Business;
    share_url: string;
    payment_methods: Array<{ id: number; name: string }>;
    defaults: { paid_on: string };
    llm_ready: boolean;
    generations: Generation[];
}) {
    const editable = invoice.status !== 'paid' && invoice.status !== 'void';
    const [lines, setLines] = useState<Line[]>(() =>
        invoice.items.map((item) => ({
            description: item.description,
            quantity: String(item.quantity),
            unit_price: String(item.unit_price),
            tax_rate: String(item.tax_rate),
        })),
    );

    useEffect(() => {
        setLines(
            invoice.items.map((item) => ({
                description: item.description,
                quantity: String(item.quantity),
                unit_price: String(item.unit_price),
                tax_rate: String(item.tax_rate),
            })),
        );
    }, [invoice.id, invoice.items]);

    const totals = useMemo(() => {
        return lines.reduce(
            (acc, line) => {
                const qty = Number(line.quantity) || 0;
                const price = Number(line.unit_price) || 0;
                const rate = Number(line.tax_rate) || 0;
                const sub = qty * price;
                const tax = sub * (rate / 100);
                acc.subtotal += sub;
                acc.tax += tax;
                acc.total += sub + tax;
                return acc;
            },
            { subtotal: 0, tax: 0, total: 0 },
        );
    }, [lines]);

    const updateLine = (index: number, key: keyof Line, value: string) => {
        setLines((current) =>
            current.map((row, i) =>
                i === index ? { ...row, [key]: value } : row,
            ),
        );
    };

    const copyShare = async () => {
        await navigator.clipboard.writeText(share_url);
    };

    return (
        <>
            <Head title={invoice.number} />
            <Page>
                <PageHeader
                    title={invoice.number}
                    description={
                        business.trading_name || business.legal_name
                    }
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <a href={`/invoices/${invoice.id}/pdf`}>
                                    Download PDF
                                </a>
                            </Button>
                            <Button variant="outline" asChild>
                                <a
                                    href={share_url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    Open share page
                                </a>
                            </Button>
                            <Button
                                variant="outline"
                                type="button"
                                onClick={copyShare}
                            >
                                Copy share link
                            </Button>
                            {invoice.status !== 'paid' ? null : (
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
                        </>
                    }
                />

                <div className="flex flex-wrap gap-2">
                    <Badge>{invoice.status}</Badge>
                    <Badge variant="outline">Due {invoice.due_date}</Badge>
                    {invoice.paid_on ? (
                        <Badge variant="outline">
                            Paid {invoice.paid_on}
                            {invoice.payment_method
                                ? ` · ${invoice.payment_method}`
                                : ''}
                        </Badge>
                    ) : null}
                </div>

                {invoice.status !== 'paid' ? (
                    <Surface>
                        <SurfaceHeader title="Record payment" />
                        <SurfaceBody>
                    <Form
                        action={`/invoices/${invoice.id}/mark-paid`}
                        method="post"
                        className="grid max-w-2xl gap-3 sm:grid-cols-[1fr_1fr_auto]"
                    >
                        {({ errors, processing }) => (
                            <>
                                <TextField
                                    name="paid_on"
                                    label="Paid on"
                                    type="date"
                                    defaultValue={defaults.paid_on}
                                    error={errors.paid_on}
                                />
                                <SelectField
                                    name="payment_method_id"
                                    label="Payment method"
                                    options={payment_methods.map((method) => ({
                                        value: String(method.id),
                                        label: method.name,
                                    }))}
                                    error={errors.payment_method_id}
                                    hint="Manage methods under Settings → Payment methods."
                                />
                                <div className="flex items-end">
                                    <Button type="submit" disabled={processing}>
                                        Mark paid
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                        </SurfaceBody>
                    </Surface>
                ) : null}

                <div className="grid gap-4 lg:grid-cols-2">
                    <Surface>
                        <SurfaceHeader title="From" />
                        <SurfaceBody className="text-sm">
                        {business.logo_url ? (
                            <img
                                src={business.logo_url}
                                alt="Logo"
                                className="max-h-16"
                            />
                        ) : (
                            <p className="text-muted-foreground">
                                No logo yet.{' '}
                                <Link
                                    href="/billing/profile"
                                    className="underline"
                                >
                                    Add logo
                                </Link>
                            </p>
                        )}
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
                        </SurfaceBody>
                    </Surface>
                    <Surface>
                        <SurfaceHeader title="Bill to" />
                        <SurfaceBody className="text-sm">
                        <p className="font-medium">
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
                        </SurfaceBody>
                    </Surface>
                </div>

                <Surface>
                    <SurfaceHeader
                        title="AI invoice builder"
                        description="Ask for a rewrite. Every run is stored so you can apply or regenerate later."
                        action={
                            !llm_ready ? (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href="/ai">Configure LLM</Link>
                                </Button>
                            ) : null
                        }
                    />
                    <SurfaceBody>
                    {editable ? (
                        <Form
                            action={`/invoices/${invoice.id}/generations`}
                            method="post"
                            className="grid gap-3"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <AreaField
                                        name="prompt"
                                        label="Tell AI what to change or rebuild"
                                        error={errors.prompt}
                                    />
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                    >
                                        Generate with AI
                                    </Button>
                                </>
                            )}
                        </Form>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Paid and void invoices stay locked. History below
                            still shows past AI runs.
                        </p>
                    )}

                    {generations.length > 0 ? (
                        <ul className="mt-4 space-y-2">
                            {generations.map((row) => (
                                <li
                                    key={row.id}
                                    className="rounded-md border p-3 text-sm"
                                >
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge variant="outline">
                                            {row.status}
                                        </Badge>
                                        <span className="text-muted-foreground">
                                            #{row.id}
                                        </span>
                                        {row.applied_at ? (
                                            <Badge>Applied</Badge>
                                        ) : null}
                                    </div>
                                    <p className="mt-1">{row.prompt}</p>
                                    {row.error ? (
                                        <p className="mt-1 text-destructive">
                                            {row.error}
                                        </p>
                                    ) : null}
                                    {row.result?.summary ? (
                                        <p className="mt-1 text-muted-foreground">
                                            {row.result.summary}
                                        </p>
                                    ) : null}
                                    {editable && row.status === 'completed' ? (
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            <Form
                                                action={`/invoices/${invoice.id}/generations/${row.id}/apply`}
                                                method="post"
                                            >
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                >
                                                    Apply to invoice
                                                </Button>
                                            </Form>
                                            <Form
                                                action={`/invoices/${invoice.id}/generations`}
                                                method="post"
                                            >
                                                {({ processing }) => (
                                                    <>
                                                        <input
                                                            type="hidden"
                                                            name="prompt"
                                                            value={row.prompt}
                                                        />
                                                        <input
                                                            type="hidden"
                                                            name="from_generation_id"
                                                            value={String(
                                                                row.id,
                                                            )}
                                                        />
                                                        <Button
                                                            type="submit"
                                                            size="sm"
                                                            variant="outline"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            Regenerate
                                                        </Button>
                                                    </>
                                                )}
                                            </Form>
                                        </div>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    ) : null}
                    </SurfaceBody>
                </Surface>

                {editable ? (
                    <Surface>
                        <SurfaceHeader title="Edit invoice fields" />
                        <SurfaceBody>
                    <Form
                        action={`/invoices/${invoice.id}`}
                        method="put"
                        className="grid max-w-4xl gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="contact_id"
                                    value={String(invoice.contact.id)}
                                />
                                <input
                                    type="hidden"
                                    name="items"
                                    value={JSON.stringify(
                                        lines.map((line) => ({
                                            description: line.description,
                                            quantity: Number(line.quantity),
                                            unit_price: Number(line.unit_price),
                                            tax_rate: Number(line.tax_rate),
                                        })),
                                    )}
                                />
                                <div className="grid gap-3 sm:grid-cols-3">
                                    <TextField
                                        name="issue_date"
                                        label="Issue date"
                                        type="date"
                                        defaultValue={invoice.issue_date}
                                        error={errors.issue_date}
                                    />
                                    <TextField
                                        name="due_date"
                                        label="Due date"
                                        type="date"
                                        defaultValue={invoice.due_date}
                                        error={errors.due_date}
                                    />
                                    <TextField
                                        name="currency"
                                        label="Currency"
                                        defaultValue={invoice.currency}
                                        error={errors.currency}
                                    />
                                </div>
                                <TextField
                                    name="customer_address"
                                    label="Bill-to address"
                                    defaultValue={invoice.customer_address}
                                    error={errors.customer_address}
                                />
                                <AreaField
                                    name="notes"
                                    label="Notes"
                                    defaultValue={invoice.notes}
                                    error={errors.notes}
                                />
                                <AreaField
                                    name="terms_and_conditions"
                                    label="Terms and conditions"
                                    defaultValue={
                                        invoice.terms_and_conditions
                                    }
                                    error={errors.terms_and_conditions}
                                />
                                <div className="space-y-3">
                                    <div className="flex items-center justify-between">
                                        <h3 className="text-sm font-medium">
                                            Line items
                                        </h3>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                setLines((current) => [
                                                    ...current,
                                                    {
                                                        description: '',
                                                        quantity: '1',
                                                        unit_price: '0',
                                                        tax_rate: '16',
                                                    },
                                                ])
                                            }
                                        >
                                            Add line
                                        </Button>
                                    </div>
                                    {lines.map((line, index) => (
                                        <div
                                            key={index}
                                            className="grid gap-2 rounded-md border p-3 md:grid-cols-12"
                                        >
                                            <div className="md:col-span-5">
                                                <label className="text-sm">
                                                    Description
                                                </label>
                                                <input
                                                    className="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                                    value={line.description}
                                                    onChange={(event) =>
                                                        updateLine(
                                                            index,
                                                            'description',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                            {(
                                                [
                                                    ['quantity', 'Qty'],
                                                    [
                                                        'unit_price',
                                                        'Unit price',
                                                    ],
                                                    ['tax_rate', 'Tax %'],
                                                ] as const
                                            ).map(([key, label]) => (
                                                <div
                                                    key={key}
                                                    className="md:col-span-2"
                                                >
                                                    <label className="text-sm">
                                                        {label}
                                                    </label>
                                                    <input
                                                        className="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                                        value={line[key]}
                                                        onChange={(event) =>
                                                            updateLine(
                                                                index,
                                                                key,
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                    />
                                                </div>
                                            ))}
                                            <div className="flex items-end md:col-span-1">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    disabled={
                                                        lines.length === 1
                                                    }
                                                    onClick={() =>
                                                        setLines((current) =>
                                                            current.filter(
                                                                (_, i) =>
                                                                    i !== index,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    Remove
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                    {errors.items ? (
                                        <p className="text-sm text-destructive">
                                            {errors.items}
                                        </p>
                                    ) : null}
                                    <p className="text-sm tabular-nums text-muted-foreground">
                                        Subtotal {totals.subtotal.toFixed(2)} ·
                                        Tax {totals.tax.toFixed(2)} · Total{' '}
                                        {totals.total.toFixed(2)}
                                    </p>
                                </div>
                                <Button type="submit" disabled={processing}>
                                    Save invoice
                                </Button>
                            </>
                        )}
                    </Form>
                        </SurfaceBody>
                    </Surface>
                ) : (
                    <Surface>
                        <SurfaceBody className="space-y-4">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Description</TableHead>
                                    <TableHead className="text-right">
                                        Qty
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Unit
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Tax %
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Total
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {invoice.items.map((item) => (
                                    <TableRow key={item.id}>
                                        <TableCell>
                                            {item.description}
                                        </TableCell>
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
                                    {invoice.currency}{' '}
                                    {invoice.subtotal.toFixed(2)}
                                </span>
                            </div>
                            <div className="flex justify-between text-muted-foreground">
                                <span>Tax</span>
                                <span>
                                    {invoice.currency}{' '}
                                    {invoice.tax_total.toFixed(2)}
                                </span>
                            </div>
                            <div className="flex justify-between text-base font-semibold">
                                <span>Total</span>
                                <span>
                                    {invoice.currency}{' '}
                                    {invoice.total.toFixed(2)}
                                </span>
                            </div>
                        </div>
                        {invoice.notes ? (
                            <p className="text-sm text-muted-foreground">
                                Notes: {invoice.notes}
                            </p>
                        ) : null}
                        {invoice.terms_and_conditions ? (
                            <p className="text-sm text-muted-foreground whitespace-pre-wrap">
                                Terms: {invoice.terms_and_conditions}
                            </p>
                        ) : null}
                        </SurfaceBody>
                    </Surface>
                )}

                <p className="break-all text-xs text-muted-foreground">
                    Share link: {share_url}
                </p>
            </Page>
        </>
    );
}

InvoiceShow.layout = {
    breadcrumbs: [
        { title: 'Invoices', href: '/invoices' },
        { title: 'Invoice', href: '#' },
    ],
};
