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
import type { LeadRow, Option, Paginator } from '@/types/crm';

type Filters = {
    q: string;
    product_id: string;
    stage_id: string;
    source: string;
    follow_up: string;
};

const followUpLabel: Record<string, string> = {
    none: 'No date',
    due_today: 'Due today',
    overdue: 'Overdue',
    upcoming: 'Upcoming',
};

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
        { value: 'none', label: 'No date set' },
        { value: 'due_today', label: 'Due today' },
        { value: 'overdue', label: 'Overdue' },
        { value: 'upcoming', label: 'Upcoming' },
    ];

    return (
        <>
            <Head title="Leads" />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Leads
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {leads.total} in this view. A closed lead does not
                            block a new one.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/leads/create">Open a lead</Link>
                    </Button>
                </div>

                <Form
                    action="/leads"
                    method="get"
                    className="grid gap-3 rounded-lg border p-3 md:grid-cols-6"
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
                            <div className="md:col-span-6">
                                <Button type="submit" disabled={processing}>
                                    Apply filters
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                {leads.data.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-8 text-sm text-muted-foreground">
                        No leads match this view. Clear a filter or open a lead
                        from a contact.
                    </div>
                ) : (
                    <>
                        <div className="hidden md:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Contact</TableHead>
                                        <TableHead>Product</TableHead>
                                        <TableHead>Stage</TableHead>
                                        <TableHead>Follow-up</TableHead>
                                        <TableHead>Next action</TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {leads.data.map((lead) => (
                                        <TableRow key={lead.id}>
                                            <TableCell>
                                                <Link
                                                    href={`/leads/${lead.id}`}
                                                    className="font-medium hover:underline"
                                                >
                                                    {lead.contact_name}
                                                </Link>
                                                <div className="text-xs text-muted-foreground">
                                                    {lead.business_name ??
                                                        'No business name'}{' '}
                                                    · {lead.phone}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {lead.product_name}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">
                                                    {lead.stage_name}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {lead.next_follow_up_label ??
                                                    'None'}
                                                <div className="text-xs text-muted-foreground">
                                                    {
                                                        followUpLabel[
                                                            lead.follow_up_state
                                                        ]
                                                    }
                                                </div>
                                            </TableCell>
                                            <TableCell className="max-w-56 truncate">
                                                {lead.next_action ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                <RowMenu id={lead.id} />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                        <div className="grid gap-3 md:hidden">
                            {leads.data.map((lead) => (
                                <Link
                                    key={lead.id}
                                    href={`/leads/${lead.id}`}
                                    className="rounded-lg border p-3"
                                >
                                    <div className="font-medium">
                                        {lead.contact_name}
                                    </div>
                                    <div className="text-sm text-muted-foreground">
                                        {lead.business_name ?? 'No business'} ·{' '}
                                        {lead.phone}
                                    </div>
                                    <div className="mt-2 text-sm">
                                        {lead.stage_name} · {lead.product_name}
                                    </div>
                                    <div className="text-sm">
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
        <div className="flex items-center justify-between text-sm">
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
