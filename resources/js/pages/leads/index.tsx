import { Form, Head, Link } from '@inertiajs/react';
import { MoreHorizontal } from 'lucide-react';
import { SelectField, TextField } from '@/components/crm/fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { followUpTone, leadOutcomeTone } from '@/lib/status-tone';
import type { LeadRow, Option, Paginator } from '@/types/crm';

type Filters = {
    q: string;
    product_id: string;
    stage_id: string;
    source: string;
    follow_up: string;
    kind: string;
    created: string;
};

const followUpLabel: Record<string, string> = {
    none: 'No date',
    due_today: 'Due today',
    overdue: 'Overdue',
    upcoming: 'Upcoming',
};

const surface =
    'overflow-hidden rounded-xl border border-border bg-card shadow-[var(--shadow-card)]';

export default function LeadsIndex({
    leads,
    filters,
    products,
    stages,
    sources,
}: {
    leads: Paginator<LeadRow>;
    filters: Filters;
    products: Array<{ id: number; name: string }>;
    stages: Array<{ id: number; name: string }>;
    sources: string[];
}) {
    const productOptions: Option[] = [
        { value: 'all', label: 'All products' },
        ...products.map((product) => ({
            value: String(product.id),
            label: product.name,
        })),
    ];
    const stageOptions: Option[] = [
        { value: 'all', label: 'All stages' },
        ...stages.map((stage) => ({
            value: String(stage.id),
            label: stage.name,
        })),
    ];
    const sourceOptions: Option[] = [
        { value: 'all', label: 'All sources' },
        ...sources.map((source) => ({ value: source, label: source })),
    ];
    const followOptions: Option[] = [
        { value: 'all', label: 'Any follow-up' },
        { value: 'ranked', label: 'Ranked follow-ups' },
        { value: 'chat', label: 'From chat dates' },
        { value: 'none', label: 'No date set' },
        { value: 'due_today', label: 'Due today' },
        { value: 'overdue', label: 'Overdue' },
        { value: 'upcoming', label: 'Upcoming' },
    ];

    return (
        <>
            <Head title="Leads" />
            <div className="mx-auto flex w-full max-w-[90rem] flex-col gap-6 p-4 md:gap-8 md:p-8">
                {/* Page header */}
                <div className="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                            Leads
                        </h1>
                        <p className="mt-2 max-w-[60ch] text-sm leading-relaxed text-muted-foreground">
                            <span className="font-semibold tabular-nums text-foreground">
                                {leads.total}
                            </span>{' '}
                            in this view. A closed lead does not block a new
                            one.
                        </p>
                    </div>
                    <Button asChild size="lg">
                        <Link href="/leads/create">Open a lead</Link>
                    </Button>
                </div>

                {/* Filters */}
                <section className={surface}>
                    <div className="border-b border-border bg-card px-5 py-3">
                        <h2 className="text-sm font-semibold text-foreground">
                            Filters
                        </h2>
                    </div>
                    <Form
                        action="/leads"
                        method="get"
                        className="grid gap-4 bg-card p-5 md:grid-cols-6"
                    >
                        {({ processing }) => (
                            <>
                                <div className="md:col-span-2">
                                    <TextField
                                        name="q"
                                        label="Search"
                                        defaultValue={filters.q}
                                        hint="Name, business, or phone"
                                    />
                                </div>
                                <SelectField
                                    name="product_id"
                                    label="Product"
                                    options={productOptions}
                                    defaultValue={filters.product_id || 'all'}
                                />
                                <SelectField
                                    name="stage_id"
                                    label="Stage"
                                    options={stageOptions}
                                    defaultValue={filters.stage_id || 'all'}
                                />
                                <SelectField
                                    name="source"
                                    label="Source"
                                    options={sourceOptions}
                                    defaultValue={filters.source || 'all'}
                                />
                                <SelectField
                                    name="follow_up"
                                    label="Follow-up"
                                    options={followOptions}
                                    defaultValue={filters.follow_up || 'all'}
                                />
                                <SelectField
                                    name="kind"
                                    label="Outcome"
                                    options={[
                                        { value: 'all', label: 'Any outcome' },
                                        { value: 'won', label: 'Won' },
                                        { value: 'lost', label: 'Lost' },
                                        { value: 'active', label: 'Active' },
                                        {
                                            value: 'archived-nurture',
                                            label: 'Archived nurture',
                                        },
                                    ]}
                                    defaultValue={filters.kind || 'all'}
                                />
                                <SelectField
                                    name="created"
                                    label="Created"
                                    options={[
                                        { value: 'all', label: 'Any day' },
                                        { value: 'today', label: 'Today' },
                                    ]}
                                    defaultValue={filters.created || 'all'}
                                />
                                <div className="flex flex-wrap items-center gap-2 border-t border-border pt-4 md:col-span-6">
                                    <Button type="submit" disabled={processing}>
                                        Apply filters
                                    </Button>
                                    <Button variant="outline" asChild>
                                        <Link href="/leads">Clear</Link>
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </section>

                {leads.data.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-input bg-card px-6 py-12 text-center text-sm text-muted-foreground">
                        No leads match this view. Clear a filter or open a lead
                        from a contact.
                    </div>
                ) : (
                    <>
                        {/* Desktop table */}
                        <div className={`hidden md:block ${surface}`}>
                            <Table>
                                <TableHeader className="bg-secondary">
                                    <TableRow>
                                        <TableHead className="px-5 py-3 text-secondary-foreground">
                                            Contact
                                        </TableHead>
                                        <TableHead className="px-5 py-3 text-secondary-foreground">
                                            Product
                                        </TableHead>
                                        <TableHead className="px-5 py-3 text-secondary-foreground">
                                            Stage
                                        </TableHead>
                                        <TableHead className="px-5 py-3 text-secondary-foreground">
                                            Follow-up
                                        </TableHead>
                                        <TableHead className="px-5 py-3 text-secondary-foreground">
                                            Next action
                                        </TableHead>
                                        <TableHead className="px-5 py-3" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {leads.data.map((lead) => (
                                        <TableRow
                                            key={lead.id}
                                            className="bg-card hover:bg-muted"
                                        >
                                            <TableCell className="px-5 py-4 align-top">
                                                <Link
                                                    href={`/leads/${lead.id}`}
                                                    className="font-semibold text-primary hover:underline"
                                                >
                                                    {lead.contact_name}
                                                </Link>
                                                <div className="mt-0.5 text-xs text-muted-foreground">
                                                    {lead.business_name ??
                                                        'No business name'}{' '}
                                                    · {lead.phone}
                                                </div>
                                            </TableCell>
                                            <TableCell className="px-5 py-4 align-top">
                                                {lead.product_name}
                                            </TableCell>
                                            <TableCell className="px-5 py-4 align-top">
                                                <div className="flex flex-wrap gap-1">
                                                    <Badge variant="laundry">
                                                        {lead.stage_name}
                                                    </Badge>
                                                    <Badge
                                                        variant={leadOutcomeTone(
                                                            lead.outcome,
                                                        )}
                                                    >
                                                        {lead.outcome}
                                                    </Badge>
                                                </div>
                                            </TableCell>
                                            <TableCell className="px-5 py-4 align-top">
                                                {lead.next_follow_up_label ??
                                                    'None'}
                                                <div className="mt-1">
                                                    <Badge
                                                        variant={followUpTone(
                                                            lead.follow_up_state,
                                                        )}
                                                    >
                                                        {
                                                            followUpLabel[
                                                                lead
                                                                    .follow_up_state
                                                            ]
                                                        }
                                                    </Badge>
                                                </div>
                                            </TableCell>
                                            <TableCell className="max-w-56 truncate px-5 py-4 align-top">
                                                {lead.next_action ?? '—'}
                                            </TableCell>
                                            <TableCell className="px-5 py-4 align-top">
                                                <RowMenu id={lead.id} />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        {/* Mobile cards */}
                        <div className="grid gap-4 md:hidden">
                            {leads.data.map((lead) => (
                                <Link
                                    key={lead.id}
                                    href={`/leads/${lead.id}`}
                                    className="block rounded-xl border border-border bg-card p-4 shadow-[var(--shadow-card)] transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <div className="font-semibold text-primary">
                                        {lead.contact_name}
                                    </div>
                                    <div className="text-sm text-muted-foreground">
                                        {lead.business_name ?? 'No business'} ·{' '}
                                        {lead.phone}
                                    </div>
                                    <div className="mt-3 text-sm font-medium">
                                        {lead.stage_name} · {lead.product_name}
                                    </div>
                                    <div className="mt-0.5 text-sm text-muted-foreground">
                                        {lead.next_follow_up_label
                                            ? `${followUpLabel[lead.follow_up_state]} · ${lead.next_follow_up_label}`
                                            : 'No follow-up date'}
                                    </div>
                                </Link>
                            ))}
                        </div>
                        <Pager paginator={leads} />
                    </>
                )}
            </div>
        </>
    );
}

function RowMenu({ id }: { id: number }) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" aria-label="Lead actions">
                    <MoreHorizontal />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem asChild>
                    <Link href={`/leads/${id}`}>Open lead</Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function Pager<T>({ paginator }: { paginator: Paginator<T> }) {
    if (paginator.last_page <= 1) {
        return null;
    }

    return (
        <div className="flex items-center justify-between rounded-xl border border-border bg-card px-5 py-3 text-sm shadow-[var(--shadow-card)]">
            <span className="text-muted-foreground">
                Page {paginator.current_page} of {paginator.last_page}
            </span>
            <div className="flex gap-2">
                {paginator.prev_page_url ? (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={paginator.prev_page_url}>Previous</Link>
                    </Button>
                ) : null}
                {paginator.next_page_url ? (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={paginator.next_page_url}>Next</Link>
                    </Button>
                ) : null}
            </div>
        </div>
    );
}

LeadsIndex.layout = {
    breadcrumbs: [{ title: 'Leads', href: '/leads' }],
};