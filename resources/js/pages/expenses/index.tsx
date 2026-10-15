import { Form, Head } from '@inertiajs/react';
import { AreaField, SelectField, TextField } from '@/components/crm/fields';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { Paginator } from '@/types/crm';

type ExpenseRow = {
    id: number;
    title: string;
    category: string;
    vendor: string | null;
    amount: number;
    tax_amount: number;
    gross: number;
    currency: string;
    spent_on: string | null;
    system_name: string;
    notes: string | null;
    receipt_url: string | null;
};

const categories = [
    'general',
    'rent',
    'payroll',
    'ads',
    'software',
    'transport',
    'supplies',
    'tax',
    'utilities',
    'other',
];

export default function ExpensesIndex({
    expenses,
    stats,
    by_category,
    filters,
    defaults,
}: {
    expenses: Paginator<ExpenseRow>;
    stats: {
        amount: number;
        tax_amount: number;
        gross: number;
        total_rows: number;
    };
    by_category: Array<{ category: string; gross: number; rows: number }>;
    filters: { category: string; month: string };
    defaults: { currency: string; spent_on: string };
}) {
    return (
        <>
            <Head title="Expenses" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Expenses
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Log spend by category, attach receipts, and see monthly
                        totals. Expense tax also lands in the tax log.
                    </p>
                </div>

                <div className="grid gap-3 sm:grid-cols-4">
                    <Stat label="Rows" value={String(stats.total_rows)} />
                    <Stat
                        label="Net"
                        value={stats.amount.toFixed(2)}
                    />
                    <Stat
                        label="Tax"
                        value={stats.tax_amount.toFixed(2)}
                    />
                    <Stat
                        label="Gross"
                        value={stats.gross.toFixed(2)}
                    />
                </div>

                <section className="rounded-lg border p-4">
                    <h2 className="text-sm font-medium">By category</h2>
                    <ul className="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        {by_category.length === 0 ? (
                            <li className="text-sm text-muted-foreground">
                                No spend in this filter.
                            </li>
                        ) : (
                            by_category.map((row) => (
                                <li
                                    key={row.category}
                                    className="rounded-md border px-3 py-2 text-sm"
                                >
                                    <div className="font-medium">
                                        {row.category}
                                    </div>
                                    <div className="tabular-nums text-muted-foreground">
                                        {row.gross.toFixed(2)} · {row.rows}{' '}
                                        rows
                                    </div>
                                </li>
                            ))
                        )}
                    </ul>
                </section>

                <Form
                    action="/expenses"
                    method="get"
                    className="flex flex-wrap items-end gap-3"
                >
                    {({ processing }) => (
                        <>
                            <label className="grid gap-1 text-sm">
                                <span className="text-muted-foreground">
                                    Month
                                </span>
                                <input
                                    type="month"
                                    name="month"
                                    defaultValue={filters.month}
                                    className="h-9 rounded-md border bg-background px-3"
                                />
                            </label>
                            <label className="grid gap-1 text-sm">
                                <span className="text-muted-foreground">
                                    Category
                                </span>
                                <select
                                    name="category"
                                    defaultValue={filters.category || 'all'}
                                    className="h-9 rounded-md border bg-background px-3"
                                >
                                    <option value="all">All</option>
                                    {categories.map((category) => (
                                        <option key={category} value={category}>
                                            {category}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <Button type="submit" disabled={processing}>
                                Filter
                            </Button>
                        </>
                    )}
                </Form>

                <Form
                    action="/expenses"
                    method="post"
                    encType="multipart/form-data"
                    resetOnSuccess
                    className="grid max-w-3xl gap-3 rounded-lg border p-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <h2 className="text-sm font-medium">
                                Add expense
                            </h2>
                            <TextField
                                name="title"
                                label="Title"
                                required
                                error={errors.title}
                            />
                            <div className="grid gap-3 sm:grid-cols-2">
                                <SelectField
                                    name="category"
                                    label="Category"
                                    defaultValue="general"
                                    options={categories.map((category) => ({
                                        value: category,
                                        label: category,
                                    }))}
                                    error={errors.category}
                                />
                                <TextField
                                    name="vendor"
                                    label="Vendor"
                                    error={errors.vendor}
                                />
                            </div>
                            <div className="grid gap-3 sm:grid-cols-4">
                                <TextField
                                    name="amount"
                                    label="Amount"
                                    type="number"
                                    required
                                    error={errors.amount}
                                />
                                <TextField
                                    name="tax_amount"
                                    label="Tax"
                                    type="number"
                                    defaultValue={0}
                                    error={errors.tax_amount}
                                />
                                <TextField
                                    name="currency"
                                    label="Currency"
                                    defaultValue={defaults.currency}
                                    error={errors.currency}
                                />
                                <TextField
                                    name="spent_on"
                                    label="Date"
                                    type="date"
                                    defaultValue={defaults.spent_on}
                                    error={errors.spent_on}
                                />
                            </div>
                            <TextField
                                name="system_name"
                                label="System tag"
                                defaultValue="expenses"
                                hint="Used in the tax log so every system stays separated."
                                error={errors.system_name}
                            />
                            <AreaField
                                name="notes"
                                label="Notes"
                                error={errors.notes}
                            />
                            <div className="grid gap-1">
                                <label className="text-sm">Receipt</label>
                                <input type="file" name="receipt" />
                            </div>
                            <Button type="submit" disabled={processing}>
                                Save expense
                            </Button>
                        </>
                    )}
                </Form>

                {expenses.data.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-8 text-sm text-muted-foreground">
                        No expenses yet.
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Date</TableHead>
                                <TableHead>Title</TableHead>
                                <TableHead>Category</TableHead>
                                <TableHead className="text-right">
                                    Gross
                                </TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {expenses.data.map((expense) => (
                                <TableRow key={expense.id}>
                                    <TableCell>{expense.spent_on}</TableCell>
                                    <TableCell>
                                        <div className="font-medium">
                                            {expense.title}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {expense.vendor ?? '—'} ·{' '}
                                            {expense.system_name}
                                        </div>
                                    </TableCell>
                                    <TableCell>{expense.category}</TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {expense.currency}{' '}
                                        {expense.gross.toFixed(2)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Form
                                            action={`/expenses/${expense.id}`}
                                            method="delete"
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    variant="ghost"
                                                    disabled={processing}
                                                >
                                                    Remove
                                                </Button>
                                            )}
                                        </Form>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </div>
        </>
    );
}

function Stat({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border p-4">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="text-2xl tabular-nums">{value}</p>
        </div>
    );
}

ExpensesIndex.layout = {
    breadcrumbs: [{ title: 'Expenses', href: '/expenses' }],
};
