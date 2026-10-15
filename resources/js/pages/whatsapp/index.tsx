import { Form, Head } from '@inertiajs/react';
import { SelectField } from '@/components/crm/fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Variable = {
    key: string;
    configured: boolean;
};

type ProductOption = {
    id: number;
    name: string;
};

export default function WhatsAppSettings({
    variables,
    webhook_path,
    automatic_sends_paused,
    inbound_product_id,
    products,
    opt_out_keywords,
    can_manage,
}: {
    variables: Variable[];
    webhook_path: string;
    automatic_sends_paused: boolean;
    inbound_product_id: number | null;
    products: ProductOption[];
    opt_out_keywords: string[];
    can_manage: boolean;
}) {
    return (
        <>
            <Head title="WhatsApp" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        WhatsApp
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Official Cloud API only. This page shows whether each
                        environment variable is set. It never shows the secret.
                    </p>
                </div>

                <section className="rounded-lg border p-4">
                    <h2 className="text-sm font-medium">Configuration</h2>
                    <ul className="mt-3 divide-y text-sm">
                        {variables.map((variable) => (
                            <li
                                key={variable.key}
                                className="flex items-center justify-between gap-3 py-2"
                            >
                                <span className="font-mono text-xs">
                                    {variable.key}
                                </span>
                                <Badge
                                    variant={
                                        variable.configured
                                            ? 'default'
                                            : 'outline'
                                    }
                                >
                                    {variable.configured
                                        ? 'Configured'
                                        : 'Missing'}
                                </Badge>
                            </li>
                        ))}
                    </ul>
                    {variables.some((variable) => !variable.configured) ? (
                        <p className="mt-3 text-sm text-muted-foreground">
                            Missing variables mean the desk cannot verify
                            webhooks or send. Nothing here marks the account as
                            connected.
                        </p>
                    ) : (
                        <p className="mt-3 text-sm text-muted-foreground">
                            All four variables are set. Delivery still depends
                            on Meta accepting each message.
                        </p>
                    )}
                </section>

                <section className="rounded-lg border p-4 text-sm">
                    <h2 className="font-medium">Webhook</h2>
                    <p className="mt-2 text-muted-foreground">
                        In the Meta app, set the callback URL to this path on
                        the public host, and subscribe to the messages field.
                    </p>
                    <p className="mt-2 font-mono text-xs">{webhook_path}</p>
                    <p className="mt-3 text-muted-foreground">
                        Verification uses WHATSAPP_VERIFY_TOKEN. Posts must
                        carry X-Hub-Signature-256 signed with
                        WHATSAPP_APP_SECRET.
                    </p>
                </section>

                <section className="rounded-lg border p-4 text-sm">
                    <h2 className="font-medium">Opt-out keywords</h2>
                    <p className="mt-2 text-muted-foreground">
                        An inbound text opts the contact out only when the
                        whole message is one of these words. Nothing else is
                        treated as an opt-out.
                    </p>
                    <p className="mt-2 font-mono text-xs">
                        {opt_out_keywords.join(', ')}
                    </p>
                </section>

                <section className="rounded-lg border p-4">
                    <h2 className="text-sm font-medium">Desk controls</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Pausing automatic sends does not block a message an
                        operator types on a lead. Inbound leads use the product
                        you pick, or the first active product if this is empty.
                    </p>
                    {can_manage ? (
                        <Form
                            action="/whatsapp"
                            method="put"
                            className="mt-4 grid max-w-md gap-3"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <SelectField
                                        name="automatic_sends_paused"
                                        label="Automatic sends"
                                        defaultValue={
                                            automatic_sends_paused ? '1' : '0'
                                        }
                                        options={[
                                            {
                                                value: '0',
                                                label: 'Running',
                                            },
                                            {
                                                value: '1',
                                                label: 'Paused for the whole desk',
                                            },
                                        ]}
                                        error={errors.automatic_sends_paused}
                                    />
                                    <SelectField
                                        name="inbound_product_id"
                                        label="Product for new WhatsApp leads"
                                        defaultValue={
                                            inbound_product_id
                                                ? String(inbound_product_id)
                                                : 'none'
                                        }
                                        options={[
                                            {
                                                value: 'none',
                                                label: 'First active product',
                                            },
                                            ...products.map((product) => ({
                                                value: String(product.id),
                                                label: product.name,
                                            })),
                                        ]}
                                        error={errors.inbound_product_id}
                                    />
                                    <Button type="submit" disabled={processing}>
                                        Save WhatsApp settings
                                    </Button>
                                </>
                            )}
                        </Form>
                    ) : (
                        <p className="mt-3 text-sm text-muted-foreground">
                            Automatic sends are{' '}
                            {automatic_sends_paused
                                ? 'paused for the whole desk'
                                : 'running'}
                            . Only the owner can change that.
                        </p>
                    )}
                </section>
            </div>
        </>
    );
}

WhatsAppSettings.layout = {
    breadcrumbs: [{ title: 'WhatsApp', href: '/whatsapp' }],
};
