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
                    <h2 className="text-lg font-semibold tracking-tight">
                        Payment methods
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Define how active subscription clients pay when you
                        follow up — M-Pesa, bank transfer, cash, and any custom
                        channel.
                    </p>
                </div>

                <Form
                    action="/settings/payment-methods"
                    method="post"
                    resetOnSuccess
                    className="grid gap-3 rounded-lg border p-4"
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
                            <input type="hidden" name="is_active" value="1" />
                            <Button type="submit" disabled={processing}>
                                Add payment method
                            </Button>
                        </>
                    )}
                </Form>

                <ul className="divide-y rounded-lg border">
                    {methods.length === 0 ? (
                        <li className="p-4 text-sm text-muted-foreground">
                            No payment methods yet.
                        </li>
                    ) : (
                        methods.map((method) => (
                            <li
                                key={method.id}
                                className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <Form
                                    action={`/settings/payment-methods/${method.id}`}
                                    method="put"
                                    className="flex flex-1 flex-col gap-2 sm:flex-row sm:items-end"
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <div className="flex-1">
                                                <TextField
                                                    name="name"
                                                    label="Name"
                                                    required
                                                    defaultValue={method.name}
                                                    error={errors.name}
                                                />
                                            </div>
                                            <TextField
                                                name="position"
                                                label="Order"
                                                type="number"
                                                defaultValue={method.position}
                                                error={errors.position}
                                            />
                                            <input
                                                type="hidden"
                                                name="is_active"
                                                value={
                                                    method.is_active ? '1' : '0'
                                                }
                                            />
                                            <div className="flex items-center gap-2">
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
