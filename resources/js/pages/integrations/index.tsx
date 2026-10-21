import { Form, Head } from '@inertiajs/react';
import { useMemo, useState, type ReactNode } from 'react';
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

type CatalogItem = {
    provider: string;
    title: string;
    tagline: string;
    pitch: string;
    demos: string[];
    outcomes: string[];
    setup_steps: string[];
    secret_fields: Array<{ key: string; label: string; hint?: string }>;
    public_fields: Array<{ key: string; label: string; hint?: string }>;
    supports_sync: boolean;
    can_set_driver: boolean;
};

type Account = {
    id: number;
    provider: string;
    label: string;
    is_active: boolean;
    settings: Record<string, string | null>;
    credential_flags: Record<string, boolean>;
    last_synced_at: string | null;
    last_sync_error: string | null;
    configured: boolean;
    supports_sync: boolean;
    can_set_driver: boolean;
};

type Report = {
    id: number;
    provider: string | null;
    label: string | null;
    report_type: string;
    period_start: string | null;
    period_end: string | null;
    metrics: Record<string, number | string>;
    rows: Array<Record<string, string | number | null>>;
    fetched_at: string | null;
};

function Panel({
    title,
    description,
    children,
    aside,
}: {
    title: string;
    description?: ReactNode;
    children: ReactNode;
    aside?: ReactNode;
}) {
    return (
        <Surface>
            <SurfaceHeader
                title={title}
                description={description}
                action={aside}
            />
            <SurfaceBody className="text-sm">{children}</SurfaceBody>
        </Surface>
    );
}

function IntegrationCard({
    catalog,
    account,
    canManage,
    messagingDriver,
}: {
    catalog: CatalogItem;
    account: Account;
    canManage: boolean;
    messagingDriver: string;
}) {
    const [helpOpen, setHelpOpen] = useState(false);
    const driverValue =
        catalog.provider === 'flaresend'
            ? 'flaresend'
            : catalog.provider === 'meta_whatsapp'
              ? 'meta'
              : messagingDriver;

    return (
        <Panel
            title={catalog.title}
            description={catalog.tagline}
            aside={
                <Badge variant={account.configured ? 'default' : 'outline'}>
                    {account.configured ? 'Ready' : 'Needs setup'}
                </Badge>
            }
        >
            <p className="max-w-[70ch] leading-relaxed text-foreground">
                {catalog.pitch}
            </p>

            <div className="mt-5 grid gap-5 lg:grid-cols-2">
                <div>
                    <h3 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        How it feels in a sale
                    </h3>
                    <ul className="mt-2 space-y-2 text-muted-foreground">
                        {catalog.demos.map((demo) => (
                            <li key={demo} className="leading-relaxed">
                                · {demo}
                            </li>
                        ))}
                    </ul>
                </div>
                <div>
                    <h3 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        What you unlock
                    </h3>
                    <ul className="mt-2 space-y-2 text-muted-foreground">
                        {catalog.outcomes.map((item) => (
                            <li key={item} className="leading-relaxed">
                                · {item}
                            </li>
                        ))}
                    </ul>
                </div>
            </div>

            <ol className="mt-5 list-decimal space-y-1.5 pl-5 text-muted-foreground">
                {catalog.setup_steps.map((step) => (
                    <li key={step} className="leading-relaxed">
                        {step}
                    </li>
                ))}
            </ol>

            {canManage ? (
                <Form
                    action={`/integrations/${account.id}`}
                    method="put"
                    className="mt-6 grid gap-3 border-t border-border pt-5"
                >
                    {({ errors, processing }) => (
                        <>
                            <TextField
                                name="label"
                                label="Display name"
                                defaultValue={account.label}
                                error={errors.label}
                            />

                            {catalog.provider === 'google_calendar' ? (
                                <>
                                    <input type="hidden" name="is_active" value="0" />
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            name="is_active"
                                            value="1"
                                            defaultChecked={account.is_active}
                                            className="size-4 rounded border"
                                        />
                                        Activate Google Calendar push
                                    </label>
                                </>
                            ) : (
                                <input type="hidden" name="is_active" value="1" />
                            )}

                            {catalog.can_set_driver ? (
                                <SelectField
                                    name="messaging_driver"
                                    label="Messaging driver"
                                    defaultValue={
                                        messagingDriver === driverValue
                                            ? driverValue
                                            : ''
                                    }
                                    placeholder="Keep current driver"
                                    options={[
                                        {
                                            value: driverValue,
                                            label:
                                                driverValue === 'flaresend'
                                                    ? 'Switch desk to FlareSend'
                                                    : 'Switch desk to Meta Cloud',
                                        },
                                    ]}
                                    hint={`Current driver: ${messagingDriver}. Leave blank to keep it.`}
                                    error={errors.messaging_driver}
                                />
                            ) : null}

                            {catalog.public_fields.map((field) => (
                                <TextField
                                    key={field.key}
                                    name={field.key}
                                    label={field.label}
                                    defaultValue={
                                        account.settings[field.key] ?? ''
                                    }
                                    hint={field.hint}
                                    error={errors[field.key]}
                                />
                            ))}

                            {catalog.secret_fields.map((field) => (
                                <div key={field.key} className="grid gap-2">
                                    <TextField
                                        name={`credentials[${field.key}]`}
                                        label={field.label}
                                        type="password"
                                        hint={
                                            account.credential_flags[field.key]
                                                ? 'Saved on the desk — leave blank to keep.'
                                                : (field.hint ??
                                                  'Stored encrypted. Never shown again.')
                                        }
                                        error={
                                            errors[
                                                `credentials.${field.key}`
                                            ]
                                        }
                                    />
                                </div>
                            ))}

                            <div className="flex flex-wrap gap-2">
                                <Button type="submit" disabled={processing}>
                                    Save on desk
                                </Button>
                                {catalog.supports_sync ? (
                                    <Button
                                        type="submit"
                                        form={`sync-${account.id}`}
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        {catalog.provider === 'google_calendar'
                                            ? 'Check credentials'
                                            : 'Pull latest report'}
                                    </Button>
                                ) : null}
                            </div>
                        </>
                    )}
                </Form>
            ) : (
                <p className="mt-5 text-muted-foreground">
                    Owners save keys here. You can still request a technical
                    supervisor walkthrough below.
                </p>
            )}

            {catalog.supports_sync ? (
                <Form
                    id={`sync-${account.id}`}
                    action={`/integrations/${account.id}/sync`}
                    method="post"
                    className="hidden"
                >
                    {null}
                </Form>
            ) : null}

            {account.last_sync_error ? (
                <p className="mt-3 text-sm text-destructive">
                    {account.last_sync_error}
                </p>
            ) : account.last_synced_at ? (
                <p className="mt-3 text-xs text-muted-foreground">
                    Last synced {account.last_synced_at}
                </p>
            ) : null}

            <div className="mt-6 border-t border-border pt-5">
                {!helpOpen ? (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setHelpOpen(true)}
                    >
                        Forward me to a technical supervisor
                    </Button>
                ) : (
                    <Form
                        action={`/integrations/${account.id}/help`}
                        method="post"
                        className="grid gap-3"
                    >
                        {({ errors, processing }) => (
                            <>
                                <p className="leading-relaxed text-foreground">
                                    We will tell the technical supervisor you
                                    need a guided setup for {catalog.title} —
                                    demos, keys, and how replies use
                                    conversation context.
                                </p>
                                <TextField
                                    name="stuck_on"
                                    label="Where are you stuck? (optional)"
                                    hint="e.g. webhook URL, API key location, driver switch"
                                    error={errors.stuck_on}
                                />
                                <AreaField
                                    name="note"
                                    label="What should they help with?"
                                    required
                                    hint="Plain language is fine — they will walk you through it."
                                    error={errors.note}
                                />
                                <div className="flex flex-wrap gap-2">
                                    <Button type="submit" disabled={processing}>
                                        Send to technical supervisor
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() => setHelpOpen(false)}
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </Panel>
    );
}

export default function IntegrationsIndex({
    accounts,
    reports,
    catalog,
    can_manage,
    messaging_driver,
    webhook_paths,
}: {
    accounts: Account[];
    reports: Report[];
    catalog: CatalogItem[];
    can_manage: boolean;
    messaging_driver: string;
    webhook_paths: { flaresend: string; meta: string; twilio: string };
}) {
    const byProvider = useMemo(() => {
        const map = new Map<string, Account>();
        for (const account of accounts) {
            map.set(account.provider, account);
        }
        return map;
    }, [accounts]);

    return (
        <>
            <Head title="Integrations" />
            <Page width="content">
                <PageHeader
                    title="Integrations"
                    description={
                        <>
                            Configure FlareSend, Meta, Search Console, and
                            Google Calendar on the desk — not in .env. Each
                            connection is pitched the way a salesperson would:
                            what it unlocks, how demos feel, and how replies
                            stay human with real conversation context. Stuck?
                            Forward yourself to a technical supervisor for a
                            live walkthrough. Active messaging driver:{' '}
                            <span className="font-medium text-foreground">
                                {messaging_driver}
                            </span>
                            .
                        </>
                    }
                />

                <Panel
                    title="Webhook endpoints"
                    description="Share these when a technical supervisor wires inbound messaging."
                >
                    <ul className="space-y-2">
                        <li className="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <span className="w-24 font-medium">FlareSend</span>
                            <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">
                                {webhook_paths.flaresend}
                            </code>
                        </li>
                        <li className="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <span className="w-24 font-medium">Meta</span>
                            <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">
                                {webhook_paths.meta}
                            </code>
                        </li>
                        <li className="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <span className="w-24 font-medium">Twilio</span>
                            <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">
                                {webhook_paths.twilio}
                            </code>
                        </li>
                    </ul>
                </Panel>

                <div className="grid gap-6">
                    {catalog.map((item) => {
                        const account = byProvider.get(item.provider);
                        if (!account) {
                            return null;
                        }

                        return (
                            <IntegrationCard
                                key={item.provider}
                                catalog={item}
                                account={account}
                                canManage={can_manage}
                                messagingDriver={messaging_driver}
                            />
                        );
                    })}
                </div>

                <Panel title="Latest sync snapshots">
                    {reports.length === 0 ? (
                        <p className="text-muted-foreground">
                            No reports pulled yet. Save Meta or Search Console,
                            then pull a report to use in demos.
                        </p>
                    ) : (
                        <div className="space-y-4">
                            {reports.map((report) => (
                                <article
                                    key={report.id}
                                    className="rounded-md border border-border p-3"
                                >
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h3 className="font-medium">
                                            {report.label}
                                        </h3>
                                        <Badge variant="outline">
                                            {report.report_type}
                                        </Badge>
                                        <span className="text-xs text-muted-foreground">
                                            {report.period_start} →{' '}
                                            {report.period_end}
                                        </span>
                                    </div>
                                    <dl className="mt-3 grid gap-2 sm:grid-cols-4 text-sm">
                                        {Object.entries(report.metrics).map(
                                            ([key, value]) => (
                                                <div
                                                    key={key}
                                                    className="rounded border px-2 py-1"
                                                >
                                                    <dt className="text-xs text-muted-foreground">
                                                        {key}
                                                    </dt>
                                                    <dd className="tabular-nums">
                                                        {String(value)}
                                                    </dd>
                                                </div>
                                            ),
                                        )}
                                    </dl>
                                </article>
                            ))}
                        </div>
                    )}
                </Panel>
            </Page>
        </>
    );
}

IntegrationsIndex.layout = {
    breadcrumbs: [{ title: 'Integrations', href: '/integrations' }],
};
