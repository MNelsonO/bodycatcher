import { Form, Head } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { SelectField } from '@/components/crm/fields';
import {
    Page,
    PageHeader,
    Surface,
    SurfaceBody,
    SurfaceHeader,
} from '@/components/crm/page-frame';
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

const code = 'rounded bg-muted px-1.5 py-0.5 font-mono text-xs text-foreground';

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
        <Surface>
            <SurfaceHeader title={title} description={description} />
            <SurfaceBody className="text-sm">{children}</SurfaceBody>
        </Surface>
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
    gateway,
    safety,
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
    gateway: { configured: boolean; active: boolean };
    safety: SafetyUsage;
}) {
    return (
        <>
            <Head title="WhatsApp" />
            <Page width="narrow">
                <PageHeader
                    title="WhatsApp"
                    description={
                        <>
                            Active driver is{' '}
                            <span className={code}>{driver}</span>. Paste
                            FlareSend / Meta keys and switch the driver on{' '}
                            <a href="/integrations" className="underline">
                                Integrations
                            </a>{' '}
                            — no .env edit needed. Secrets are encrypted and
                            never shown here.
                        </>
                    }
                />

                <GatewayPanel gateway={gateway} canManage={can_manage} />
                <SafetyPanel safety={safety} />

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
                        Set <span className={code}>LLM_PROVIDER</span> to
                        anthropic (Claude, needs only{' '}
                        <span className={code}>LLM_API_KEY</span>), openai or
                        gemini (also <span className={code}>LLM_BASE_URL</span>{' '}
                        and <span className={code}>LLM_MODEL</span>) in{' '}
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
            </Page>
        </>
    );
}

WhatsAppSettings.layout = {
    breadcrumbs: [{ title: 'WhatsApp', href: '/whatsapp' }],
};

type GatewayStatus = {
    configured: boolean;
    state: string;
    active: boolean;
    qr: string | null;
};

const STATE_LABEL: Record<string, string> = {
    open: 'Linked',
    connecting: 'Waiting for scan',
    close: 'Not linked',
    missing: 'Not linked',
    offline: 'Gateway offline',
    unconfigured: 'Not installed',
};

function gatewayXsrf(): string {
    const match = document.cookie
        .split('; ')
        .find((part) => part.startsWith('XSRF-TOKEN='));

    return match ? decodeURIComponent(match.split('=')[1]) : '';
}

function GatewayPanel({
    gateway,
    canManage,
}: {
    gateway: { configured: boolean; active: boolean };
    canManage: boolean;
}) {
    const [status, setStatus] = useState<GatewayStatus | null>(null);
    const [qr, setQr] = useState<string | null>(null);
    const [pairing, setPairing] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    const refresh = useCallback(async () => {
        try {
            const response = await fetch('/whatsapp/gateway', {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const json = (await response.json()) as GatewayStatus;
            setStatus(json);

            if (json.state === 'open') {
                setQr(null);
                setPairing(null);
            } else if (json.qr) {
                setQr(json.qr);
            }
        } catch {
            setStatus(null);
        }
    }, []);

    useEffect(() => {
        if (!gateway.configured) {
            return;
        }

        void refresh();
        const timer = setInterval(() => void refresh(), qr ? 3000 : 15000);

        return () => clearInterval(timer);
    }, [refresh, qr, gateway.configured]);

    const connect = async () => {
        setBusy(true);
        setError(null);

        try {
            const response = await fetch('/whatsapp/gateway/connect', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': gatewayXsrf(),
                },
                credentials: 'same-origin',
            });
            const json = await response.json();

            if (!response.ok) {
                setError(json?.message ?? 'Could not reach the gateway.');
            } else {
                setQr(json.qr ?? null);
                setPairing(json.pairing_code ?? null);
                void refresh();
            }
        } catch {
            setError('Could not reach the gateway.');
        } finally {
            setBusy(false);
        }
    };

    const state =
        status?.state ?? (gateway.configured ? 'close' : 'unconfigured');
    const linked = state === 'open';

    return (
        <Surface>
            <SurfaceHeader
                title="Your WhatsApp gateway"
                description="Self-hosted on this server. It captures both sides of every chat, including replies you send from your phone, and fills in recent history for people already in the desk."
                badge={
                    <Badge variant={linked ? 'default' : 'outline'}>
                        {STATE_LABEL[state] ?? state}
                    </Badge>
                }
            />
            <SurfaceBody className="grid gap-4 text-sm">
                {state === 'unconfigured' ? (
                    <p className="text-muted-foreground">
                        The gateway is not installed on the server yet.
                    </p>
                ) : linked ? (
                    <div className="grid gap-3">
                        <p>
                            Your phone is linked. New messages, yours and your
                            clients&apos;, arrive here automatically.
                        </p>
                        {gateway.active || status?.active ? (
                            <p className="text-muted-foreground">
                                The desk sends through this gateway.
                            </p>
                        ) : canManage ? (
                            <Form
                                action="/whatsapp/gateway/activate"
                                method="post"
                            >
                                {({ processing }) => (
                                    <Button type="submit" disabled={processing}>
                                        Send through this gateway
                                    </Button>
                                )}
                            </Form>
                        ) : null}
                    </div>
                ) : (
                    <div className="grid gap-4">
                        <ol className="grid gap-1 text-muted-foreground">
                            <li>1. Click Show QR code.</li>
                            <li>
                                2. On your phone open WhatsApp → Settings →
                                Linked devices → Link a device.
                            </li>
                            <li>
                                3. Scan the code. This page updates by itself.
                            </li>
                        </ol>
                        {qr ? (
                            <div className="flex flex-col items-start gap-2">
                                <img
                                    src={qr}
                                    alt="WhatsApp link QR code"
                                    className="size-64 rounded-lg border bg-white p-2"
                                />
                                {pairing ? (
                                    <p className="text-xs text-muted-foreground">
                                        Or link with a code:{' '}
                                        <span className="font-mono font-semibold text-foreground">
                                            {pairing}
                                        </span>
                                    </p>
                                ) : null}
                                <p className="text-xs text-muted-foreground">
                                    Codes expire quickly; click again for a new
                                    one.
                                </p>
                            </div>
                        ) : null}
                        {canManage ? (
                            <div>
                                <Button
                                    type="button"
                                    onClick={() => void connect()}
                                    disabled={busy}
                                >
                                    {busy
                                        ? 'Loading…'
                                        : qr
                                          ? 'New QR code'
                                          : 'Show QR code'}
                                </Button>
                            </div>
                        ) : (
                            <p className="text-muted-foreground">
                                Ask the desk owner to link WhatsApp.
                            </p>
                        )}
                        {error ? (
                            <p className="text-destructive">{error}</p>
                        ) : null}
                    </div>
                )}
            </SurfaceBody>
        </Surface>
    );
}

type SafetyUsage = {
    applies: boolean;
    automatic_today: number;
    automatic_last_hour: number;
    new_chats_today: number;
    limits: {
        auto_per_hour: number;
        auto_per_day: number;
        manual_new_chats_per_day: number;
        auto_min_gap_seconds: number;
    };
};

function SafetyMeter({
    label,
    used,
    limit,
}: {
    label: string;
    used: number;
    limit: number;
}) {
    const share =
        limit > 0 ? Math.min(100, Math.round((used / limit) * 100)) : 0;

    return (
        <div className="grid gap-1">
            <div className="flex items-baseline justify-between text-xs">
                <span className="text-muted-foreground">{label}</span>
                <span className="font-medium tabular-nums">
                    {used} / {limit}
                </span>
            </div>
            <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                <div
                    className={
                        share >= 80 ? 'h-full bg-cta' : 'h-full bg-primary'
                    }
                    style={{ width: `${share}%` }}
                />
            </div>
        </div>
    );
}

function SafetyPanel({ safety }: { safety: SafetyUsage }) {
    return (
        <Surface>
            <SurfaceHeader
                title="Safe sending"
                description="Keeps your number from looking like a bot. Automatic messages only go to people who wrote to you first, are spaced out, capped per hour and day, and never repeat the same text across many chats. Follow-ups over a limit wait and send later."
                badge={
                    <Badge variant={safety.applies ? 'default' : 'outline'}>
                        {safety.applies ? 'On' : 'Not needed for this driver'}
                    </Badge>
                }
            />
            <SurfaceBody className="grid gap-4 text-sm sm:grid-cols-3">
                <SafetyMeter
                    label="Automatic, last hour"
                    used={safety.automatic_last_hour}
                    limit={safety.limits.auto_per_hour}
                />
                <SafetyMeter
                    label="Automatic, today"
                    used={safety.automatic_today}
                    limit={safety.limits.auto_per_day}
                />
                <SafetyMeter
                    label="New chats you started today"
                    used={safety.new_chats_today}
                    limit={safety.limits.manual_new_chats_per_day}
                />
                <p className="text-xs text-muted-foreground sm:col-span-3">
                    At least {safety.limits.auto_min_gap_seconds}s between
                    automatic messages, and each one shows &ldquo;typing…&rdquo;
                    first. Replies you type yourself are not slowed down.
                </p>
            </SurfaceBody>
        </Surface>
    );
}
