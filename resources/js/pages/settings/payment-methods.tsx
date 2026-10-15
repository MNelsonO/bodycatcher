import { Form, Head } from '@inertiajs/react';
import { TextField } from '@/components/crm/fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Method = {
    id: number;
    name: string;
    slug: string;
    is_active: boolean;
    position: number;
};

const surface =
    'overflow-hidden rounded-xl border border-border bg-card shadow-[var(--shadow-card)]';

export default function PaymentMethodsSettings({
    methods,
}: {
    methods: Method[];
}) {
    return (
        <>
            <Head title="Payment methods" />
            <div className="space-y-6">
                <div>
                    <h2 className="text-xl font-semibold tracking-tight text-foreground">
                        Payment methods
                    </h2>
                    <p className="mt-1 max-w-[65ch] text-sm leading-relaxed text-muted-foreground">
                        Define how active subscription clients pay when you
                        follow up — M-Pesa, bank transfer, cash, and any custom
                        channel.
                    </p>
                </div>

                {/* Add method */}
                <section className={surface}>
                    <div className="border-b border-border bg-card px-5 py-4">
                        <h3 className="text-base font-semibold tracking-tight text-foreground">
                            Add a method
                        </h3>
                    </div>
                    <Form
                        action="/settings/payment-methods"
                        method="post"
                        resetOnSuccess
                        className="grid gap-4 bg-card p-5"
                    >
                        {({ errors, processing }) => (
                            <>
                                <TextField
                                    name="name"
                                    label="New method"
                                    required
                                    error={errors.name}
                                    hint="Shown when recording a subscription payout."
                                />
                                <input
                                    type="hidden"
                                    name="is_active"
                                    value="1"
                                />
                                <div>
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                    >
                                        Add payment method
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </section>

                {/* Existing methods */}
                <section className={surface}>
                    <div className="flex items-center justify-between border-b border-border bg-secondary px-5 py-3">
                        <h3 className="text-sm font-semibold text-secondary-foreground">
                            Your methods
                        </h3>
                        <span className="text-sm tabular-nums text-secondary-foreground">
                            {methods.length}
                        </span>
                    </div>
                    <ul className="divide-y divide-border">
                        {methods.length === 0 ? (
                            <li className="bg-card px-5 py-8 text-sm text-muted-foreground">
                                No payment methods yet.
                            </li>
                        ) : (
                            methods.map((method) => (
                                <li
                                    key={method.id}
                                    className="flex flex-col gap-4 bg-card px-5 py-4 lg:flex-row lg:items-end lg:justify-between"
                                >
                                    <Form
                                        action={`/settings/payment-methods/${method.id}`}
                                        method="put"
                                        className="flex flex-1 flex-col gap-3 sm:flex-row sm:items-end"
                                    >
                                        {({ errors, processing }) => (
                                            <>
                                                <div className="flex-1">
                                                    <TextField
                                                        name="name"
                                                        label="Name"
                                                        required
                                                        defaultValue={
                                                            method.name
                                                        }
                                                        error={errors.name}
                                                    />
                                                </div>
                                                <div className="sm:w-28">
                                                    <TextField
                                                        name="position"
                                                        label="Order"
                                                        type="number"
                                                        defaultValue={
                                                            method.position
                                                        }
                                                        error={errors.position}
                                                    />
                                                </div>
                                                <input
                                                    type="hidden"
                                                    name="is_active"
                                                    value={
                                                        method.is_active
                                                            ? '1'
                                                            : '0'
                                                    }
                                                />
                                                <div className="flex items-center gap-3">
                                                    <Badge
                                                        variant={
                                                            method.is_active
                                                                ? 'default'
                                                                : 'outline'
                                                        }
                                                    >
                                                        {method.is_active
                                                            ? 'Active'
                                                            : 'Off'}
                                                    </Badge>
                                                    <Button
                                                        type="submit"
                                                        size="sm"
                                                        variant="outline"
                                                        disabled={processing}
                                                    >
                                                        Save
                                                    </Button>
                                                </div>
                                            </>
                                        )}
                                    </Form>
                                    {method.is_active ? (
                                        <Form
                                            action={`/settings/payment-methods/${method.id}`}
                                            method="delete"
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    variant="ghost"
                                                    disabled={processing}
                                                >
                                                    Deactivate
                                                </Button>
                                            )}
                                        </Form>
                                    ) : (
                                        <Form
                                            action={`/settings/payment-methods/${method.id}`}
                                            method="put"
                                        >
                                            {({ processing }) => (
                                                <>
                                                    <input
                                                        type="hidden"
                                                        name="name"
                                                        value={method.name}
                                                    />
                                                    <input
                                                        type="hidden"
                                                        name="position"
                                                        value={method.position}
                                                    />
                                                    <input
                                                        type="hidden"
                                                        name="is_active"
                                                        value="1"
                                                    />
                                                    <Button
                                                        type="submit"
                                                        size="sm"
                                                        variant="outline"
                                                        disabled={processing}
                                                    >
                                                        Activate
                                                    </Button>
                                                </>
                                            )}
                                        </Form>
                                    )}
                                </li>
                            ))
                        )}
                    </ul>
                </section>
            </div>
        </>
    );
}

PaymentMethodsSettings.layout = {
    breadcrumbs: [
        { title: 'Settings', href: '/settings/profile' },
        { title: 'Payment methods', href: '/settings/payment-methods' },
    ],
};