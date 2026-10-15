import { Form, Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { AreaField, SelectField, TextField } from '@/components/crm/fields';
import { Button } from '@/components/ui/button';

type ContactOption = {
    id: number;
    full_name: string;
    business_name: string | null;
};

type Line = {
    description: string;
    quantity: string;
    unit_price: string;
    tax_rate: string;
};

export default function InvoiceCreate({
    contacts,
    defaults,
}: {
    contacts: ContactOption[];
    defaults: {
        issue_date: string;
        due_date: string;
        currency: string;
        tax_rate: number;
    };
}) {
    const [lines, setLines] = useState<Line[]>([
        {
            description: '',
            quantity: '1',
            unit_price: '0',
            tax_rate: String(defaults.tax_rate),
        },
    ]);

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
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex items-end justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            New invoice
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Line items include tax. Share link and PDF unlock
                            after save.
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href="/invoices">Back</Link>
                    </Button>
                </div>

                <Form
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
                                label="Customer"
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
                                    defaultValue={defaults.due_date}
                                    error={errors.due_date}
                                />
                                <TextField
                                    name="currency"
                                    label="Currency"
                                    defaultValue={defaults.currency}
                                    error={errors.currency}
                                />
                            </div>
                            <TextField
                                name="customer_address"
                                label="Customer address on invoice"
                                error={errors.customer_address}
                            />
                            <AreaField
                                name="notes"
                                label="Notes"
                                error={errors.notes}
                            />

                            <div className="rounded-lg border p-4">
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
                                    <p className="mt-2 text-sm text-red-600">
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
            </div>
        </>
    );
}

InvoiceCreate.layout = {
    breadcrumbs: [
        { title: 'Invoices', href: '/invoices' },
        { title: 'New', href: '/invoices/create' },
    ],
};
