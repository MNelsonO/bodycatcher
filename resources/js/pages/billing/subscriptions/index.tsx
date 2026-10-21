import { Form, Head, Link } from '@inertiajs/react';
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

type Subscription = {
    id: number;
    label: string;
    contact_name: string;
    business_name: string | null;
    product_name: string | null;
    amount: number;
    tax_rate: number;
    next_due_on: string | null;
    last_paid_on: string | null;
    last_payment_method: string | null;
    status: string;
    alert_days_before: number;
    due_soon: boolean;
    overdue: boolean;
};

export default function SubscriptionsIndex({
    subscriptions,
    contacts,
    products,
    defaults,
    alerts,
    payment_methods,
}: {
    subscriptions: Subscription[];
    contacts: Array<{
        id: number;
        full_name: string;
        business_name: string | null;
    }>;
    products: Array<{ id: number; name: string }>;
    defaults: { tax_rate: number; next_due_on: string; paid_on: string };
    alerts: {
        due_subscriptions: Array<{
            id: number;
            label: string;
            contact_name: string;
            next_due_on: string | null;
            overdue: boolean;
        }>;
    };
    payment_methods: Array<{ id: number; name: string }>;
}) {
    return (
        <>
            <Head title="Monthly customers" />
            <Page>
                <PageHeader
                    title="Monthly customers"
                    description="Follow up on active subscriptions: record when they paid and which method they used. Define methods in Settings."
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href="/settings/payment-methods">
                                    Payment methods
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href="/invoices">Invoices</Link>
                            </Button>
                        </>
                    }
                />

                {alerts.due_subscriptions.length > 0 ? (
                    <Surface>
                        <SurfaceHeader title="Due date alerts" />
                        <SurfaceBody className="text-sm">
                            <ul className="space-y-1">
                                {alerts.due_subscriptions.map((row) => (
                                    <li key={row.id}>
                                        {row.overdue ? 'OVERDUE' : 'Due soon'} ·{' '}
                                        {row.label} · {row.contact_name} ·{' '}
                                        {row.next_due_on}
                                    </li>
                                ))}
                            </ul>
                        </SurfaceBody>
                    </Surface>
                ) : null}

                <Surface>
                    <SurfaceHeader title="Add monthly customer" />
                    <SurfaceBody>
                        <Form
                            action="/billing/subscriptions"
                            method="post"
                            resetOnSuccess
                            className="grid max-w-3xl gap-3"
                        >
                            {({ errors, processing }) => (
                                <>
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
                                    <SelectField
                                        name="product_id"
                                        label="Product"
                                        defaultValue="none"
                                        options={[
                                            {
                                                value: 'none',
                                                label: 'No product',
                                            },
                                            ...products.map((product) => ({
                                                value: String(product.id),
                                                label: product.name,
                                            })),
                                        ]}
                                        error={errors.product_id}
                                    />
                                    <TextField
                                        name="label"
                                        label="Plan label"
                                        required
                                        error={errors.label}
                                    />
                                    <div className="grid gap-3 sm:grid-cols-3">
                                        <TextField
                                            name="amount"
                                            label="Amount (ex tax)"
                                            type="number"
                                            required
                                            error={errors.amount}
                                        />
                                        <TextField
                                            name="tax_rate"
                                            label="Tax %"
                                            type="number"
                                            defaultValue={defaults.tax_rate}
                                            error={errors.tax_rate}
                                        />
                                        <TextField
                                            name="next_due_on"
                                            label="Next due"
                                            type="date"
                                            defaultValue={defaults.next_due_on}
                                            error={errors.next_due_on}
                                        />
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-3">
                                        <SelectField
                                            name="cadence"
                                            label="Cadence"
                                            defaultValue="monthly"
                                            options={[
                                                {
                                                    value: 'monthly',
                                                    label: 'Monthly',
                                                },
                                            ]}
                                            error={errors.cadence}
                                        />
                                        <SelectField
                                            name="status"
                                            label="Status"
                                            defaultValue="active"
                                            options={[
                                                {
                                                    value: 'active',
                                                    label: 'Active',
                                                },
                                                {
                                                    value: 'paused',
                                                    label: 'Paused',
                                                },
                                                {
                                                    value: 'ended',
                                                    label: 'Ended',
                                                },
                                            ]}
                                            error={errors.status}
                                        />
                                        <TextField
                                            name="alert_days_before"
                                            label="Alert days before"
                                            type="number"
                                            defaultValue={3}
                                            error={errors.alert_days_before}
                                        />
                                    </div>
                                    <AreaField
                                        name="notes"
                                        label="Notes"
                                        error={errors.notes}
                                    />
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                    >
                                        Save monthly customer
                                    </Button>
                                </>
                            )}
                        </Form>
                    </SurfaceBody>
                </Surface>

                <Surface>
                    <SurfaceHeader title="Active plans" />
                    <SurfaceBody className="p-0">
                        <ul className="divide-y divide-border">
                            {subscriptions.length === 0 ? (
                                <li className="px-5 py-6 text-sm text-muted-foreground">
                                    No monthly customers yet.
                                </li>
                            ) : (
                                subscriptions.map((sub) => (
                                    <li
                                        key={sub.id}
                                        className="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-medium">
                                                    {sub.label}
                                                </span>
                                                <Badge variant="outline">
                                                    {sub.status}
                                                </Badge>
                                                {sub.overdue ? (
                                                    <Badge>Overdue</Badge>
                                                ) : sub.due_soon ? (
                                                    <Badge variant="outline">
                                                        Due soon
                                                    </Badge>
                                                ) : null}
                                            </div>
                                            <p className="text-sm text-muted-foreground">
                                                {sub.contact_name}
                                                {sub.business_name
                                                    ? ` · ${sub.business_name}`
                                                    : ''}
                                                {sub.product_name
                                                    ? ` · ${sub.product_name}`
                                                    : ''}
                                            </p>
                                            <p className="text-sm tabular-nums">
                                                {sub.amount.toFixed(2)} +{' '}
                                                {sub.tax_rate}% tax · next due{' '}
                                                {sub.next_due_on} · alert{' '}
                                                {sub.alert_days_before}d
                                            </p>
                                            {sub.last_paid_on ? (
                                                <p className="text-sm text-muted-foreground">
                                                    Last paid {sub.last_paid_on}
                                                    {sub.last_payment_method
                                                        ? ` · ${sub.last_payment_method}`
                                                        : ''}
                                                </p>
                                            ) : null}
                                        </div>
                                        <div className="flex flex-col gap-2">
                                            {sub.status === 'active' ? (
                                                <Form
                                                    action={`/billing/subscriptions/${sub.id}/payments`}
                                                    method="post"
                                                    className="grid gap-2 rounded-md border border-border p-3 sm:grid-cols-2 lg:grid-cols-4"
                                                >
                                                    {({
                                                        errors,
                                                        processing,
                                                    }) => (
                                                        <>
                                                            <TextField
                                                                name="paid_on"
                                                                label="Paid on"
                                                                type="date"
                                                                required
                                                                defaultValue={
                                                                    defaults.paid_on
                                                                }
                                                                error={
                                                                    errors.paid_on
                                                                }
                                                            />
                                                            <SelectField
                                                                name="payment_method_id"
                                                                label="Method"
                                                                required
                                                                options={payment_methods.map(
                                                                    (
                                                                        method,
                                                                    ) => ({
                                                                        value: String(
                                                                            method.id,
                                                                        ),
                                                                        label: method.name,
                                                                    }),
                                                                )}
                                                                error={
                                                                    errors.payment_method_id
                                                                }
                                                            />
                                                            <TextField
                                                                name="reference"
                                                                label="Reference"
                                                                error={
                                                                    errors.reference
                                                                }
                                                            />
                                                            <div className="flex items-end">
                                                                <Button
                                                                    type="submit"
                                                                    size="sm"
                                                                    disabled={
                                                                        processing ||
                                                                        payment_methods.length ===
                                                                            0
                                                                    }
                                                                >
                                                                    Record
                                                                    payment
                                                                </Button>
                                                            </div>
                                                        </>
                                                    )}
                                                </Form>
                                            ) : null}
                                            <div className="flex gap-2">
                                                <Form
                                                    action={`/billing/subscriptions/${sub.id}/invoice`}
                                                    method="post"
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            type="submit"
                                                            size="sm"
                                                            variant="outline"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            Invoice now
                                                        </Button>
                                                    )}
                                                </Form>
                                                <Form
                                                    action={`/billing/subscriptions/${sub.id}`}
                                                    method="delete"
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            type="submit"
                                                            size="sm"
                                                            variant="ghost"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            Remove
                                                        </Button>
                                                    )}
                                                </Form>
                                            </div>
                                        </div>
                                    </li>
                                ))
                            )}
                        </ul>
                    </SurfaceBody>
                </Surface>
            </Page>
        </>
    );
}

SubscriptionsIndex.layout = {
    breadcrumbs: [
        { title: 'Invoices', href: '/invoices' },
        { title: 'Monthly customers', href: '/billing/subscriptions' },
    ],
};
