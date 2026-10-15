import { Form, Head } from '@inertiajs/react';
import { TextField } from '@/components/crm/fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Account = {
    id: number;
    provider: string;
    label: string;
    is_active: boolean;
    settings: Record<string, string | null>;
    last_synced_at: string | null;
    last_sync_error: string | null;
    configured: boolean;
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

export default function IntegrationsIndex({
    accounts,
    reports,
    env_hints,
}: {
    accounts: Account[];
    reports: Report[];
    env_hints: Record<string, boolean>;
}) {
    return (
        <>
            <Head title="Integrations" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight text-primary">
                        Traffic & Meta reports
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Connect Google Search Console and Meta Business to pull
                        traffic, engagement, and advanced report rows into the
                        desk.
                    </p>
                </div>

                <section className="rounded-lg border p-4 text-sm">
                    <h2 className="font-medium">Environment readiness</h2>
                    <ul className="mt-3 grid gap-2 sm:grid-cols-2">
                        {Object.entries(env_hints).map(([key, ready]) => (
                            <li
                                key={key}
                                className="flex items-center justify-between rounded-md border px-3 py-2"
                            >
                                <span className="font-mono text-xs">{key}</span>
                                <Badge variant={ready ? 'default' : 'outline'}>
                                    {ready ? 'Set' : 'Missing'}
                                </Badge>
                            </li>
                        ))}
                    </ul>
                    <p className="mt-3 text-muted-foreground">
                        Meta uses META_BUSINESS_TOKEN (falls back to
                        WHATSAPP_TOKEN) and META_PAGE_ID. Search Console uses
                        GSC_SITE_URL and GSC_ACCESS_TOKEN.
                    </p>
                </section>

                <div className="grid gap-4 lg:grid-cols-2">
                    {accounts.map((account) => (
                        <section
                            key={account.id}
                            className="rounded-lg border p-4"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <h2 className="font-medium">
                                        {account.label}
                                    </h2>
                                    <p className="text-xs text-muted-foreground">
                                        {account.provider}
                                    </p>
                                </div>
                                <Badge
                                    variant={
                                        account.configured
                                            ? 'default'
                                            : 'outline'
                                    }
                                >
                                    {account.configured
                                        ? 'Ready'
                                        : 'Needs keys'}
                                </Badge>
                            </div>

                            <Form
                                action={`/integrations/${account.id}`}
                                method="put"
                                className="mt-4 grid gap-3"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <TextField
                                            name="label"
                                            label="Label"
                                            defaultValue={account.label}
                                            error={errors.label}
                                        />
                                        <input
                                            type="hidden"
                                            name="is_active"
                                            value="1"
                                        />
                                        {account.provider ===
                                        'meta_business' ? (
                                            <TextField
                                                name="page_id"
                                                label="Meta Page ID"
                                                defaultValue={
                                                    account.settings.page_id ??
                                                    ''
                                                }
                                                error={errors.page_id}
                                            />
                                        ) : (
                                            <TextField
                                                name="site_url"
                                                label="Search Console site URL"
                                                defaultValue={
                                                    account.settings.site_url ??
                                                    ''
                                                }
                                                hint="e.g. https://example.com/ or sc-domain:example.com"
                                                error={errors.site_url}
                                            />
                                        )}
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                disabled={processing}
                                            >
                                                Save
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>

                            <Form
                                action={`/integrations/${account.id}/sync`}
                                method="post"
                                className="mt-3"
                            >
                                {({ processing }) => (
                                    <Button type="submit" disabled={processing}>
                                        Pull latest report
                                    </Button>
                                )}
                            </Form>

                            {account.last_sync_error ? (
                                <p className="mt-3 text-sm text-red-600">
                                    {account.last_sync_error}
                                </p>
                            ) : account.last_synced_at ? (
                                <p className="mt-3 text-xs text-muted-foreground">
                                    Last synced {account.last_synced_at}
                                </p>
                            ) : null}
                        </section>
                    ))}
                </div>

                <section className="rounded-lg border p-4">
                    <h2 className="text-sm font-medium">
                        Advanced report details
                    </h2>
                    <div className="mt-4 space-y-4">
                        {reports.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No reports pulled yet. Save a connection and
                                sync.
                            </p>
                        ) : (
                            reports.map((report) => (
                                <article
                                    key={report.id}
                                    className="rounded-md border p-3"
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
                                    {report.rows.length > 0 ? (
                                        <div className="mt-3 overflow-x-auto">
                                            <table className="w-full text-left text-xs">
                                                <thead>
                                                    <tr className="border-b">
                                                        {Object.keys(
                                                            report.rows[0] ??
                                                                {},
                                                        ).map((key) => (
                                                            <th
                                                                key={key}
                                                                className="px-2 py-1 font-medium"
                                                            >
                                                                {key}
                                                            </th>
                                                        ))}
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {report.rows
                                                        .slice(0, 15)
                                                        .map((row, index) => (
                                                            <tr
                                                                key={index}
                                                                className="border-b"
                                                            >
                                                                {Object.values(
                                                                    row,
                                                                ).map(
                                                                    (
                                                                        value,
                                                                        i,
                                                                    ) => (
                                                                        <td
                                                                            key={
                                                                                i
                                                                            }
                                                                            className="px-2 py-1"
                                                                        >
                                                                            {String(
                                                                                value ??
                                                                                    '',
                                                                            )}
                                                                        </td>
                                                                    ),
                                                                )}
                                                            </tr>
                                                        ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    ) : null}
                                </article>
                            ))
                        )}
                    </div>
                </section>
            </div>
        </>
    );
}

IntegrationsIndex.layout = {
    breadcrumbs: [{ title: 'Integrations', href: '/integrations' }],
};
