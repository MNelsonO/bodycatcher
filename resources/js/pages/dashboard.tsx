import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { DashboardMetrics } from '@/types/crm';

const cards = [
    { key: 'total_leads', label: 'Total leads' },
    { key: 'new_leads_today', label: 'New leads today' },
    { key: 'follow_ups_due_today', label: 'Follow-ups due today' },
    { key: 'overdue_follow_ups', label: 'Overdue follow-ups' },
    { key: 'won_count', label: 'Won' },
    { key: 'lost_count', label: 'Lost' },
] as const;

export default function Dashboard({ metrics }: { metrics: DashboardMetrics }) {
    const empty = metrics.total_leads === 0;

    return (
        <>
            <Head title="Command centre" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-1">
                    <h1 className="text-xl font-semibold tracking-tight">
                        Command centre
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Counts for {metrics.today}, {metrics.timezone}.
                        Follow-up dates are the ones you set by hand.
                    </p>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {cards.map((card) => (
                        <Card key={card.key} className="shadow-none">
                            <CardHeader className="pb-2">
                                <CardDescription>{card.label}</CardDescription>
                                <CardTitle className="text-3xl tabular-nums">
                                    {metrics[card.key]}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                    ))}
                </div>

                <div className="grid gap-6 lg:grid-cols-5">
                    <Card className="shadow-none lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Leads by stage</CardTitle>
                            <CardDescription>
                                Every open and closed lead, grouped by its
                                current stage.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {empty ? (
                                <p className="text-sm text-muted-foreground">
                                    No leads yet. When you open one, the stage
                                    counts appear here.
                                </p>
                            ) : (
                                <ul className="divide-y">
                                    {metrics.leads_by_stage.map((row) => (
                                        <li
                                            key={row.stage_id}
                                            className="flex items-center justify-between gap-3 py-2 text-sm"
                                        >
                                            <span>
                                                <span className="font-medium">
                                                    {row.stage_name}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {row.pipeline_name}
                                                </span>
                                            </span>
                                            <span className="tabular-nums">
                                                {row.total}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="shadow-none lg:col-span-3">
                        <CardHeader>
                            <CardTitle>Follow-ups</CardTitle>
                            <CardDescription>
                                Due today and overdue. Each row says why it is
                                listed.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {metrics.follow_up_rows.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Nothing is due today and nothing is overdue.
                                </p>
                            ) : (
                                <ul className="divide-y">
                                    {metrics.follow_up_rows.map((row) => (
                                        <li key={row.id} className="py-3">
                                            <Link
                                                href={`/leads/${row.id}`}
                                                className="font-medium hover:underline"
                                            >
                                                {row.contact_name}
                                                {row.business_name
                                                    ? ` · ${row.business_name}`
                                                    : ''}
                                            </Link>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                <Badge variant="outline">
                                                    {row.reason}
                                                </Badge>{' '}
                                                {row.follow_up_label} ·{' '}
                                                {row.stage_name} ·{' '}
                                                {row.product_name}
                                            </p>
                                            <p className="mt-1 text-sm">
                                                {row.next_action
                                                    ? row.next_action
                                                    : 'No next action written.'}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Command centre', href: '/dashboard' }],
};
