import { Form, Head, Link, router } from '@inertiajs/react';
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

type ContactOption = {
    id: number;
    full_name: string;
    business_name: string | null;
    email: string | null;
    location: string | null;
};

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

type Business = {
    legal_name: string;
    trading_name: string | null;
    address_lines: string[];
    phone: string | null;
    email: string | null;
    tax_id: string | null;
    logo_url: string | null;
    default_terms: string | null;
};

function linesFromGeneration(
    generation: Generation | null,
    taxRate: number,
): Line[] {
    const items = generation?.result?.items;

    if (!items || items.length === 0) {
        return [
            {
                description: '',
                quantity: '1',
                unit_price: '0',
                tax_rate: String(taxRate),
            },
        ];
    }

    return items.map((item) => ({
        description: item.description,
        quantity: String(item.quantity),
        unit_price: String(item.unit_price),
        tax_rate: String(item.tax_rate),
    }));
}

export default function InvoiceCreate({
    contacts,
    defaults,
    business,
    llm_ready,
    selected_generation,
    generations,
}: {
    contacts: ContactOption[];
    defaults: {
        issue_date: string;
        due_date: string;
        currency: string;
        tax_rate: number;
        terms_and_conditions: string | null;
        next_number: string;
    };
    business: Business;
    llm_ready: boolean;
    selected_generation: Generation | null;
    generations: Generation[];
}) {
    const draft =
        selected_generation?.status === 'completed' ? selected_generation : null;
    const formKey = `invoice-form-${draft?.id ?? 'blank'}`;
    const [lines, setLines] = useState<Line[]>(() =>
        linesFromGeneration(draft, defaults.tax_rate),
    );

    useEffect(() => {
        setLines(linesFromGeneration(draft, defaults.tax_rate));
    }, [draft?.id, defaults.tax_rate]);

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

    return (
        <>
            <Head title="New invoice" />
            <Page width="wide">
                <PageHeader
                    title="New invoice"
                    description="Number, from/logo, bill to, terms, notes, and AI history live on this form."
                    actions={
                        <Button variant="outline" asChild>
                            <Link href="/invoices">Back</Link>
                        </Button>
                    }
                />

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
                                    Add logo & address
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
                        <SurfaceHeader title="Invoice number" />
                        <SurfaceBody className="text-sm">
                        <p className="text-lg font-semibold tracking-tight">
                            {defaults.next_number}
                        </p>
                        <p className="mt-1 text-muted-foreground">
                            Assigned when you save. Edit logo, address, and
                            default terms under Business profile.
                        </p>
                        </SurfaceBody>
                    </Surface>
                </div>

                <Surface>
                    <SurfaceHeader
                        title="AI invoice builder"
                        description="Describe the invoice. History is stored so you can reopen any draft and regenerate."
                        action={
                            !llm_ready ? (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href="/ai">Configure LLM</Link>
                                </Button>
                            ) : null
                        }
                    />
                    <SurfaceBody>
                    <Form
                        action="/invoices/generate"
                        method="post"
                        className="grid gap-3"
                    >
                        {({ errors, processing }) => (
                            <>
                                <AreaField
                                    name="prompt"
                                    label="Tell AI what to build"
                                    defaultValue={
                                        selected_generation?.prompt ?? ''
                                    }
                                    error={errors.prompt}
                                />
                                <SelectField
                                    name="contact_id"
                                    label="Customer for this draft (optional)"
                                    options={contacts.map((contact) => ({
                                        value: String(contact.id),
                                        label: contact.business_name
                                            ? `${contact.full_name} · ${contact.business_name}`
                                            : contact.full_name,
                                    }))}
                                    error={errors.contact_id}
                                />
                                <div className="flex flex-wrap gap-2">
                                    <Button type="submit" disabled={processing}>
                                        Generate with AI
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>

                    {selected_generation ? (
                        <div className="mt-4 rounded-md border bg-muted/30 p-3 text-sm">
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge>{selected_generation.status}</Badge>
                                <span className="text-muted-foreground">
                                    Draft #{selected_generation.id}
                                </span>
                            </div>
                            {selected_generation.error ? (
                                <p className="mt-2 text-destructive">
                                    {selected_generation.error}
                                </p>
                            ) : null}
                            {selected_generation.result?.summary ? (
                                <p className="mt-2">
                                    {selected_generation.result.summary}
                                </p>
                            ) : null}
                            <div className="mt-3 flex flex-wrap gap-2">
                                <Form
                                    action="/invoices/generate"
                                    method="post"
                                >
                                    {({ processing }) => (
                                        <>
                                            <input
                                                type="hidden"
                                                name="prompt"
                                                value={
                                                    selected_generation.prompt
                                                }
                                            />
                                            <input
                                                type="hidden"
                                                name="from_generation_id"
                                                value={String(
                                                    selected_generation.id,
                                                )}
                                            />
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                size="sm"
                                                disabled={processing}
                                            >
                                                Regenerate from this history
                                            </Button>
                                        </>
                                    )}
                                </Form>
                                {selected_generation.status === 'completed' ? (
                                    <Form
                                        action={`/invoices/generations/${selected_generation.id}/create`}
                                        method="post"
                                        className="grid gap-2 sm:grid-cols-[1fr_auto]"
                                    >
                                        {({ errors, processing }) => (
                                            <>
                                                <SelectField
                                                    name="contact_id"
                                                    label="Create invoice for"
                                                    required
                                                    options={contacts.map(
                                                        (contact) => ({
                                                            value: String(
                                                                contact.id,
                                                            ),
                                                            label: contact.business_name
                                                                ? `${contact.full_name} · ${contact.business_name}`
                                                                : contact.full_name,
                                                        }),
                                                    )}
                                                    error={errors.contact_id}
                                                />
                                                <div className="flex items-end">
                                                    <Button
                                                        type="submit"
                                                        disabled={processing}
                                                    >
                                                        Save as invoice
                                                    </Button>
                                                </div>
                                            </>
                                        )}
                                    </Form>
                                ) : null}
                            </div>
                        </div>
                    ) : null}

                    {generations.length > 0 ? (
                        <div className="mt-4 space-y-2">
                            <h3 className="text-sm font-medium">
                                Generation history
                            </h3>
                            <ul className="space-y-2">
                                {generations.map((row) => (
                                    <li
                                        key={row.id}
                                        className="flex flex-wrap items-start justify-between gap-2 rounded-md border p-3 text-sm"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Badge variant="outline">
                                                    {row.status}
                                                </Badge>
                                                <span className="text-muted-foreground">
                                                    #{row.id}
                                                </span>
                                            </div>
                                            <p className="mt-1 line-clamp-2">
                                                {row.prompt}
                                            </p>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                router.get(
                                                    '/invoices/create',
                                                    {
                                                        generation: row.id,
                                                    },
                                                )
                                            }
                                        >
                                            Open
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ) : null}
                    </SurfaceBody>
                </Surface>

                <Surface>
                    <SurfaceHeader title="Invoice details" />
                    <SurfaceBody>
                <Form
                    key={formKey}
                    action="/invoices"
                    method="post"
                    className="grid max-w-4xl gap-4"
                >
                    {({ errors, processing }) => (
                        <>
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
                            <SelectField
                                name="contact_id"
                                label="Bill to"
                                required
                                options={contacts.map((contact) => ({
                                    value: String(contact.id),
                                    label: contact.business_name
                                        ? `${contact.full_name} · ${contact.business_name}`
                                        : contact.full_name,
                                }))}
                                error={errors.contact_id}
                            />
                            <div className="grid gap-3 sm:grid-cols-3">
                                <TextField
                                    name="issue_date"
                                    label="Issue date"
                                    type="date"
                                    defaultValue={defaults.issue_date}
                                    error={errors.issue_date}
                                />
                                <TextField
                                    name="due_date"
                                    label="Due date"
                                    type="date"
                                    defaultValue={
                                        draft?.result?.due_date ??
                                        defaults.due_date
                                    }
                                    error={errors.due_date}
                                />
                                <TextField
                                    name="currency"
                                    label="Currency"
                                    defaultValue={
                                        draft?.result?.currency ??
                                        defaults.currency
                                    }
                                    error={errors.currency}
                                />
                            </div>
                            <TextField
                                name="customer_address"
                                label="Bill-to address on invoice"
                                defaultValue={
                                    draft?.result?.customer_address ?? ''
                                }
                                error={errors.customer_address}
                            />
                            <AreaField
                                name="notes"
                                label="Notes"
                                defaultValue={draft?.result?.notes ?? ''}
                                error={errors.notes}
                            />
                            <AreaField
                                name="terms_and_conditions"
                                label="Terms and conditions"
                                defaultValue={
                                    draft?.result?.terms_and_conditions ??
                                    defaults.terms_and_conditions ??
                                    ''
                                }
                                error={errors.terms_and_conditions}
                            />

                            <div className="rounded-lg border border-border p-4">
                                <div className="mb-3 flex items-center justify-between">
                                    <h2 className="text-sm font-medium">
                                        Line items
                                    </h2>
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
                                                    tax_rate: String(
                                                        defaults.tax_rate,
                                                    ),
                                                },
                                            ])
                                        }
                                    >
                                        Add line
                                    </Button>
                                </div>
                                <div className="space-y-3">
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
                                                    disabled={lines.length === 1}
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
                                </div>
                                {errors.items ? (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.items}
                                    </p>
                                ) : null}
                                <p className="mt-4 text-sm tabular-nums text-muted-foreground">
                                    Subtotal {totals.subtotal.toFixed(2)} · Tax{' '}
                                    {totals.tax.toFixed(2)} · Total{' '}
                                    {totals.total.toFixed(2)}
                                </p>
                            </div>

                            <Button type="submit" disabled={processing}>
                                Create invoice
                            </Button>
                        </>
                    )}
                </Form>
                    </SurfaceBody>
                </Surface>
            </Page>
        </>
    );
}

InvoiceCreate.layout = {
    breadcrumbs: [
        { title: 'Invoices', href: '/invoices' },
        { title: 'New', href: '/invoices/create' },
    ],
};
