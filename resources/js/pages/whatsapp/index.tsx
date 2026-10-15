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

type WebhookPaths = {
    meta: string;
    flaresend: string;
    twilio: string;
};

export default function WhatsAppSettings({
    driver,
    variables,
    webhook_paths,
    automatic_sends_paused,
    live_assistant_enabled,
    llm_ready,
    llm_provider,
    inbound_product_id,
    products,
    opt_out_keywords,
    can_manage,
}: {
    driver: string;
    variables: Variable[];
    webhook_paths: WebhookPaths;
    automatic_sends_paused: boolean;
    live_assistant_enabled: boolean;
    llm_ready: boolean;
    llm_provider: string;
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
                        Active driver is{' '}
                        <span className="font-mono text-xs">{driver}</span>.
                        Set <span className="font-mono text-xs">MESSAGING_DRIVER</span>{' '}
                        to <span className="font-mono text-xs">flaresend</span>,{' '}
                        <span className="font-mono text-xs">twilio</span>, or{' '}
                        <span className="font-mono text-xs">meta</span>. Secrets
                        stay in the environment and are never shown here.
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
                </section>

                <section className="rounded-lg border p-4 text-sm">
                    <h2 className="font-medium">Webhooks</h2>
                    <p className="mt-2 text-muted-foreground">
                        Point FlareSend at the flaresend path for inbound
                        messages. Twilio Messaging can use the twilio path.
                        Meta Cloud API still uses the whatsapp path.
                    </p>
                    <ul className="mt-3 space-y-2 font-mono text-xs">
                        <li>FlareSend · {webhook_paths.flaresend}</li>
                        <li>Twilio · {webhook_paths.twilio}</li>
                        <li>Meta · {webhook_paths.meta}</li>
                    </ul>
                    <p className="mt-3 text-muted-foreground">
                        FlareSend may send{' '}
                        <span className="font-mono text-xs">
                            X-FlareSend-Secret
                        </span>{' '}
                        or a Bearer token matching{' '}
                        <span className="font-mono text-xs">
                            FLARESEND_WEBHOOK_SECRET
                        </span>
                        . Twilio signs with{' '}
                        <span className="font-mono text-xs">
                            X-Twilio-Signature
                        </span>
                        .
                    </p>
                </section>

                <section className="rounded-lg border p-4 text-sm">
                    <h2 className="font-medium">Opt-out keywords</h2>
                    <p className="mt-2 text-muted-foreground">
                        An inbound text opts the contact out only when the
                        whole message is one of these words.
                    </p>
                    <p className="mt-2 font-mono text-xs">
                        {opt_out_keywords.join(', ')}
                    </p>
                </section>

                <section className="rounded-lg border p-4">
                    <h2 className="text-sm font-medium">
                        Live AI assistant
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        When on, inbound webhook messages are answered by{' '}
                        <span className="font-mono text-xs">{llm_provider}</span>{' '}
                        with your product catalog and the running conversation
                        topic. LLM must be configured (
                        {llm_ready ? 'ready' : 'missing keys'}).
                    </p>
                    <p className="mt-2 text-xs text-muted-foreground">
                        Set LLM_PROVIDER to openai or gemini, plus LLM_API_KEY,
                        LLM_BASE_URL, and LLM_MODEL in .env.
                    </p>
                </section>

                <section className="rounded-lg border p-4">
                    <h2 className="text-sm font-medium">Desk controls</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Pausing automatic sends blocks the live assistant and
                        rule-based replies. Manual operator sends still work.
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
                                        name="live_assistant_enabled"
                                        label="Live AI assistant"
                                        defaultValue={
                                            live_assistant_enabled ? '1' : '0'
                                        }
                                        options={[
                                            {
                                                value: '0',
                                                label: 'Off — use Intelligence rules only',
                                            },
                                            {
                                                value: '1',
                                                label: 'On — reply on webhook with topic memory',
                                            },
                                        ]}
                                        error={errors.live_assistant_enabled}
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
                            . Live assistant is{' '}
                            {live_assistant_enabled ? 'on' : 'off'}. Only the
                            owner can change that.
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
