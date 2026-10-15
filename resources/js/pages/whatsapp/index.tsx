import { Form, Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
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

const surface =
    'overflow-hidden rounded-xl border border-border bg-card shadow-[var(--shadow-card)]';

const code =
    'rounded bg-muted px-1.5 py-0.5 font-mono text-xs text-foreground';

function Panel({
    title,
    description,
    children,
}: {
    title: string;
    description?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className={surface}>
            <div className="border-b border-border bg-card px-5 py-4">
                <h2 className="text-lg font-semibold tracking-tight text-foreground">
                    {title}
                </h2>
                {description ? (
                    <p className="mt-1 max-w-[70ch] text-sm leading-relaxed text-muted-foreground">
                        {description}
                    </p>
                ) : null}
            </div>
            <div className="bg-card px-5 py-5 text-sm">{children}</div>
        </section>
    );
}

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
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:gap-8 md:p-8">
                {/* Page header */}
                <div className="border-b border-border pb-5">
                    <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                        WhatsApp
                    </h1>
                    <p className="mt-2 max-w-[70ch] text-sm leading-relaxed text-muted-foreground">
                        Active driver is <span className={code}>{driver}</span>.
                        Paste FlareSend / Meta keys and switch the driver on{' '}
                        <a href="/integrations" className="underline">
                            Integrations
                        </a>{' '}
                        — no .env edit needed. Secrets are encrypted and never
                        shown here.
                    </p>
                </div>

                <Panel title="Configuration">
                    <ul className="divide-y divide-border">
                        {variables.map((variable) => (
                            <li
                                key={variable.key}
                                className="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                            >
                                <span className={code}>{variable.key}</span>
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
                </Panel>

                <Panel
                    title="Webhooks"
                    description="Point FlareSend at the flaresend path for inbound messages. Twilio Messaging can use the twilio path. Meta Cloud API still uses the whatsapp path."
                >
                    <ul className="space-y-2">
                        <li className="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <span className="w-20 font-medium">FlareSend</span>
                            <span className={code}>
                                {webhook_paths.flaresend}
                            </span>
                        </li>
                        <li className="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <span className="w-20 font-medium">Twilio</span>
                            <span className={code}>{webhook_paths.twilio}</span>
                        </li>
                        <li className="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <span className="w-20 font-medium">Meta</span>
                            <span className={code}>{webhook_paths.meta}</span>
                        </li>
                    </ul>
                    <p className="mt-4 max-w-[70ch] leading-relaxed text-muted-foreground">
                        FlareSend may send{' '}
                        <span className={code}>X-FlareSend-Secret</span> or a
                        Bearer token matching{' '}
                        <span className={code}>FLARESEND_WEBHOOK_SECRET</span>.
                        Twilio signs with{' '}
                        <span className={code}>X-Twilio-Signature</span>.
                    </p>
                </Panel>

                <Panel
                    title="Opt-out keywords"
                    description="An inbound text opts the contact out only when the whole message is one of these words."
                >
                    <p className="flex flex-wrap gap-2">
                        {opt_out_keywords.map((keyword) => (
                            <span key={keyword} className={code}>
                                {keyword}
                            </span>
                        ))}
                    </p>
                </Panel>

                <Panel
                    title="Live AI assistant"
                    description={
                        <>
                            When on, inbound webhook messages are answered by{' '}
                            <span className={code}>{llm_provider}</span> with
                            your product catalog and the running conversation
                            topic.
                        </>
                    }
                >
                    <div className="flex flex-wrap items-center gap-3">
                        <span className="font-medium">LLM status</span>
                        <Badge variant={llm_ready ? 'default' : 'outline'}>
                            {llm_ready ? 'Ready' : 'Missing keys'}
                        </Badge>
                    </div>
                    <p className="mt-3 max-w-[70ch] leading-relaxed text-muted-foreground">
                        Set <span className={code}>LLM_PROVIDER</span> to openai
                        or gemini, plus <span className={code}>LLM_API_KEY</span>
                        , <span className={code}>LLM_BASE_URL</span>, and{' '}
                        <span className={code}>LLM_MODEL</span> in{' '}
                        <span className={code}>.env</span>.
                    </p>
                </Panel>

                <Panel
                    title="Desk controls"
                    description="Pausing automatic sends blocks the live assistant and rule-based replies. Manual operator sends still work."
                >
                    {can_manage ? (
                        <Form
                            action="/whatsapp"
                            method="put"
                            className="grid max-w-md gap-4"
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
                                    <div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Save WhatsApp settings
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    ) : (
                        <p className="leading-relaxed text-muted-foreground">
                            Automatic sends are{' '}
                            {automatic_sends_paused
                                ? 'paused for the whole desk'
                                : 'running'}
                            . Live assistant is{' '}
                            {live_assistant_enabled ? 'on' : 'off'}. Only the
                            owner can change that.
                        </p>
                    )}
                </Panel>
            </div>
        </>
    );
}

WhatsAppSettings.layout = {
    breadcrumbs: [{ title: 'WhatsApp', href: '/whatsapp' }],
};