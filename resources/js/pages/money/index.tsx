import { Form, Head } from '@inertiajs/react';
import type { InputHTMLAttributes, ReactNode } from 'react';
import {
    Page,
    PageHeader,
    surfaceClass,
} from '@/components/crm/page-frame';
import { Button } from '@/components/ui/button';

type Account = {
    id: number;
    name: string;
    institution: string | null;
    account_type: string;
    account_number: string | null;
    currency: string;
    balance: number;
    is_active: boolean;
    notes: string | null;
};

type Debt = {
    id: number;
    direction: 'payable' | 'receivable';
    party_name: string;
    title: string;
    amount: number;
    amount_paid: number;
    remaining: number;
    currency: string;
    due_on: string | null;
    status: string;
    money_account_id: number | null;
    account_name: string | null;
    notes: string | null;
};

type Recurring = {
    id: number;
    direction: 'outflow' | 'inflow';
    title: string;
    party_name: string | null;
    amount: number;
    currency: string;
    cadence: string;
    day_of_month: number | null;
    next_due_on: string | null;
    status: string;
    money_account_id: number | null;
    account_name: string | null;
    notes: string | null;
    is_overdue: boolean;
    is_due_soon: boolean;
};

const control =
    'h-10 w-full rounded-md border border-input bg-card px-3 text-sm text-foreground placeholder:text-muted-foreground focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';

function money(currency: string, amount: number): string {
    return `${currency} ${amount.toLocaleString(undefined, {
        maximumFractionDigits: 0,
    })}`;
}

export default function MoneyIndex({
    summary,
    accounts,
    debts,
    recurrings,
    defaults,
}: {
    summary: {
        cash: number;
        i_owe: number;
        owed_to_me: number;
        net: number;
        monthly_out: number;
        monthly_in: number;
        currency: string;
    };
    accounts: Account[];
    debts: Debt[];
    recurrings: Recurring[];
    defaults: { currency: string; next_due_on: string };
}) {
    const payables = debts.filter((d) => d.direction === 'payable');
    const receivables = debts.filter((d) => d.direction === 'receivable');

    return (
        <>
            <Head title="Money" />
            <Page>
                <PageHeader
                    title="Money"
                    description="Track banks, who you owe, who owes you, and monthly recurring cash moves."
                />

                <section className="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
                    <Stat
                        label="In accounts"
                        value={money(summary.currency, summary.cash)}
                        tone="blue"
                    />
                    <Stat
                        label="I owe"
                        value={money(summary.currency, summary.i_owe)}
                        tone="red"
                    />
                    <Stat
                        label="Owed to me"
                        value={money(summary.currency, summary.owed_to_me)}
                        tone="green"
                    />
                    <Stat
                        label="Net position"
                        value={money(summary.currency, summary.net)}
                        tone="lead"
                    />
                    <Stat
                        label="Monthly out"
                        value={money(summary.currency, summary.monthly_out)}
                        tone="red"
                    />
                    <Stat
                        label="Monthly in"
                        value={money(summary.currency, summary.monthly_in)}
                        tone="green"
                    />
                </section>

                {/* Accounts */}
                <section className="grid gap-4 lg:grid-cols-5">
                    <Panel
                        title="Payment accounts"
                        subtitle="Banks, M-Pesa, cash — where money sits."
                        className="lg:col-span-3"
                    >
                        {accounts.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No accounts yet. Add Equity, KCB, M-Pesa, or
                                cash.
                            </p>
                        ) : (
                            <ul className="divide-y divide-border">
                                {accounts.map((account) => (
                                    <li
                                        key={account.id}
                                        className="flex flex-wrap items-center justify-between gap-3 py-4 first:pt-0 last:pb-0"
                                    >
                                        <div>
                                            <p className="font-semibold text-foreground">
                                                {account.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {account.institution ||
                                                    account.account_type}
                                                {account.account_number
                                                    ? ` · ${account.account_number}`
                                                    : ''}
                                                {!account.is_active
                                                    ? ' · inactive'
                                                    : ''}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <span className="text-lg font-semibold tabular-nums">
                                                {money(
                                                    account.currency,
                                                    account.balance,
                                                )}
                                            </span>
                                            <Form
                                                action={`/money/accounts/${account.id}`}
                                                method="delete"
                                            >
                                                <Button
                                                    type="submit"
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    Remove
                                                </Button>
                                            </Form>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>

                    <Panel
                        title="Add account"
                        subtitle="Keep balances current."
                        className="lg:col-span-2"
                    >
                        <Form
                            action="/money/accounts"
                            method="post"
                            className="grid gap-4"
                            resetOnSuccess
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Field
                                        label="Name"
                                        name="name"
                                        required
                                        placeholder="Equity current"
                                        error={errors.name}
                                    />
                                    <Field
                                        label="Bank / provider"
                                        name="institution"
                                        placeholder="Equity Bank"
                                        error={errors.institution}
                                    />
                                    <label className="grid gap-1.5 text-sm">
                                        <span className="font-medium text-foreground">
                                            Type
                                        </span>
                                        <select
                                            name="account_type"
                                            defaultValue="bank"
                                            className={control}
                                        >
                                            <option value="bank">Bank</option>
                                            <option value="mobile">
                                                Mobile money
                                            </option>
                                            <option value="cash">Cash</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </label>
                                    <Field
                                        label="Account number"
                                        name="account_number"
                                        placeholder="Optional"
                                        error={errors.account_number}
                                    />
                                    <div className="grid grid-cols-2 gap-3">
                                        <Field
                                            label="Currency"
                                            name="currency"
                                            defaultValue={defaults.currency}
                                            required
                                            error={errors.currency}
                                        />
                                        <Field
                                            label="Balance"
                                            name="balance"
                                            type="number"
                                            step="0.01"
                                            defaultValue="0"
                                            required
                                            error={errors.balance}
                                        />
                                    </div>
                                    <Button type="submit" disabled={processing}>
                                        Save account
                                    </Button>
                                </>
                            )}
                        </Form>
                    </Panel>
                </section>

                {/* Debts */}
                <section className="grid gap-4 lg:grid-cols-2">
                    <DebtColumn
                        title="I owe"
                        tone="red"
                        empty="Nobody you owe right now."
                        debts={payables}
                        accounts={accounts}
                        defaults={defaults}
                        direction="payable"
                    />
                    <DebtColumn
                        title="Owed to me"
                        tone="green"
                        empty="Nobody owes you right now."
                        debts={receivables}
                        accounts={accounts}
                        defaults={defaults}
                        direction="receivable"
                    />
                </section>

                {/* Recurring */}
                <section className="grid gap-4 lg:grid-cols-5">
                    <Panel
                        title="Recurring monthly"
                        subtitle="Rent, salaries, retainers, tools — mark paid to roll the next due date."
                        className="lg:col-span-3"
                    >
                        {recurrings.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No recurring items yet.
                            </p>
                        ) : (
                            <ul className="divide-y divide-border">
                                {recurrings.map((item) => (
                                    <li
                                        key={item.id}
                                        className="flex flex-wrap items-start justify-between gap-3 py-4 first:pt-0 last:pb-0"
                                    >
                                        <div>
                                            <p className="font-semibold">
                                                {item.title}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {item.direction === 'outflow'
                                                    ? 'You pay'
                                                    : 'You receive'}
                                                {item.party_name
                                                    ? ` · ${item.party_name}`
                                                    : ''}
                                                {` · ${item.cadence}`}
                                                {item.account_name
                                                    ? ` · ${item.account_name}`
                                                    : ''}
                                            </p>
                                            <p
                                                className={`mt-1 text-xs font-semibold ${
                                                    item.is_overdue
                                                        ? 'text-destructive'
                                                        : item.is_due_soon
                                                          ? 'text-primary'
                                                          : 'text-muted-foreground'
                                                }`}
                                            >
                                                Next {item.next_due_on}
                                                {item.is_overdue
                                                    ? ' · OVERDUE'
                                                    : item.is_due_soon
                                                      ? ' · due soon'
                                                      : ''}
                                            </p>
                                        </div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span
                                                className={`font-semibold tabular-nums ${
                                                    item.direction === 'outflow'
                                                        ? 'text-destructive'
                                                        : 'text-success'
                                                }`}
                                            >
                                                {money(
                                                    item.currency,
                                                    item.amount,
                                                )}
                                            </span>
                                            {item.status === 'active' ? (
                                                <Form
                                                    action={`/money/recurrings/${item.id}/paid`}
                                                    method="post"
                                                >
                                                    <Button
                                                        type="submit"
                                                        size="sm"
                                                    >
                                                        Mark paid
                                                    </Button>
                                                </Form>
                                            ) : null}
                                            <Form
                                                action={`/money/recurrings/${item.id}`}
                                                method="delete"
                                            >
                                                <Button
                                                    type="submit"
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    Remove
                                                </Button>
                                            </Form>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>

                    <Panel
                        title="Add recurring"
                        subtitle="Repeats every month (or week/year)."
                        className="lg:col-span-2"
                    >
                        <Form
                            action="/money/recurrings"
                            method="post"
                            className="grid gap-4"
                            resetOnSuccess
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Field
                                        label="Title"
                                        name="title"
                                        required
                                        placeholder="Office rent"
                                        error={errors.title}
                                    />
                                    <Field
                                        label="Person / vendor"
                                        name="party_name"
                                        placeholder="Optional"
                                        error={errors.party_name}
                                    />
                                    <label className="grid gap-1.5 text-sm">
                                        <span className="font-medium text-foreground">
                                            Direction
                                        </span>
                                        <select
                                            name="direction"
                                            defaultValue="outflow"
                                            className={control}
                                        >
                                            <option value="outflow">
                                                I pay (outflow)
                                            </option>
                                            <option value="inflow">
                                                I receive (inflow)
                                            </option>
                                        </select>
                                    </label>
                                    <div className="grid grid-cols-2 gap-3">
                                        <Field
                                            label="Amount"
                                            name="amount"
                                            type="number"
                                            step="0.01"
                                            required
                                            error={errors.amount}
                                        />
                                        <Field
                                            label="Currency"
                                            name="currency"
                                            defaultValue={defaults.currency}
                                            required
                                            error={errors.currency}
                                        />
                                    </div>
                                    <label className="grid gap-1.5 text-sm">
                                        <span className="font-medium text-foreground">
                                            Cadence
                                        </span>
                                        <select
                                            name="cadence"
                                            defaultValue="monthly"
                                            className={control}
                                        >
                                            <option value="monthly">
                                                Monthly
                                            </option>
                                            <option value="weekly">
                                                Weekly
                                            </option>
                                            <option value="yearly">
                                                Yearly
                                            </option>
                                        </select>
                                    </label>
                                    <div className="grid grid-cols-2 gap-3">
                                        <Field
                                            label="Day of month"
                                            name="day_of_month"
                                            type="number"
                                            min="1"
                                            max="28"
                                            placeholder="1-28"
                                            error={errors.day_of_month}
                                        />
                                        <Field
                                            label="Next due"
                                            name="next_due_on"
                                            type="date"
                                            defaultValue={defaults.next_due_on}
                                            required
                                            error={errors.next_due_on}
                                        />
                                    </div>
                                    <AccountSelect accounts={accounts} />
                                    <Button type="submit" disabled={processing}>
                                        Save recurring
                                    </Button>
                                </>
                            )}
                        </Form>
                    </Panel>
                </section>
            </Page>
        </>
    );
}

function DebtColumn({
    title,
    tone,
    empty,
    debts,
    accounts,
    defaults,
    direction,
}: {
    title: string;
    tone: 'red' | 'green';
    empty: string;
    debts: Debt[];
    accounts: Account[];
    defaults: { currency: string; next_due_on: string };
    direction: 'payable' | 'receivable';
}) {
    return (
        <Panel
            title={title}
            subtitle={
                direction === 'payable'
                    ? 'People and bills you still need to pay.'
                    : 'People who still need to pay you.'
            }
            accent={tone}
        >
            {debts.length === 0 ? (
                <p className="mb-5 text-sm text-muted-foreground">{empty}</p>
            ) : (
                <ul className="mb-5 divide-y divide-border">
                    {debts.map((debt) => (
                        <li
                            key={debt.id}
                            className="flex flex-wrap items-start justify-between gap-3 py-4 first:pt-0"
                        >
                            <div>
                                <p className="font-semibold">{debt.title}</p>
                                <p className="text-xs text-muted-foreground">
                                    {debt.party_name}
                                    {debt.due_on ? ` · due ${debt.due_on}` : ''}
                                    {debt.account_name
                                        ? ` · ${debt.account_name}`
                                        : ''}
                                    {` · ${debt.status}`}
                                </p>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <span
                                    className={`font-semibold tabular-nums ${
                                        tone === 'red'
                                            ? 'text-destructive'
                                            : 'text-success'
                                    }`}
                                >
                                    {money(debt.currency, debt.remaining)}
                                </span>
                                {debt.status !== 'settled' ? (
                                    <Form
                                        action={`/money/debts/${debt.id}/settle`}
                                        method="post"
                                    >
                                        <Button type="submit" size="sm">
                                            Settle
                                        </Button>
                                    </Form>
                                ) : null}
                                <Form
                                    action={`/money/debts/${debt.id}`}
                                    method="delete"
                                >
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        size="sm"
                                    >
                                        Remove
                                    </Button>
                                </Form>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            <Form
                action="/money/debts"
                method="post"
                className="grid gap-4 rounded-lg border border-border bg-muted p-4"
                resetOnSuccess
            >
                {({ processing, errors }) => (
                    <>
                        <input type="hidden" name="direction" value={direction} />
                        <Field
                            label="Title"
                            name="title"
                            required
                            placeholder={
                                direction === 'payable'
                                    ? 'Supplier invoice'
                                    : 'Customer balance'
                            }
                            error={errors.title}
                        />
                        <Field
                            label="Person / company"
                            name="party_name"
                            required
                            placeholder="Name"
                            error={errors.party_name}
                        />
                        <div className="grid grid-cols-2 gap-3">
                            <Field
                                label="Amount"
                                name="amount"
                                type="number"
                                step="0.01"
                                required
                                error={errors.amount}
                            />
                            <Field
                                label="Currency"
                                name="currency"
                                defaultValue={defaults.currency}
                                required
                                error={errors.currency}
                            />
                        </div>
                        <Field
                            label="Due on"
                            name="due_on"
                            type="date"
                            error={errors.due_on}
                        />
                        <AccountSelect accounts={accounts} />
                        <Button type="submit" disabled={processing}>
                            Add {direction === 'payable' ? 'payable' : 'receivable'}
                        </Button>
                    </>
                )}
            </Form>
        </Panel>
    );
}

function Panel({
    title,
    subtitle,
    children,
    className = '',
    accent,
}: {
    title: string;
    subtitle?: string;
    children: ReactNode;
    className?: string;
    accent?: 'red' | 'green';
}) {
    const bar =
        accent === 'red'
            ? 'bg-destructive'
            : accent === 'green'
              ? 'bg-success'
              : 'bg-primary';

    return (
        <div className={`${surfaceClass} ${className}`}>
            <div className={`h-1.5 w-full ${bar}`} />
            <div className="border-b border-border bg-card px-5 py-4">
                <h2 className="text-lg font-semibold tracking-tight text-foreground">
                    {title}
                </h2>
                {subtitle ? (
                    <p className="mt-1 max-w-[60ch] text-sm leading-relaxed text-muted-foreground">
                        {subtitle}
                    </p>
                ) : null}
            </div>
            <div className="bg-card px-5 py-5">{children}</div>
        </div>
    );
}

function Stat({
    label,
    value,
    tone,
}: {
    label: string;
    value: string;
    tone: 'blue' | 'green' | 'red' | 'lead';
}) {
    const fill =
        tone === 'blue'
            ? 'bg-primary text-primary-foreground'
            : tone === 'green'
              ? 'bg-success text-success-foreground'
              : tone === 'red'
                ? 'bg-destructive text-white'
                : 'bg-lead text-lead-foreground';

    return (
        <div className={`rounded-xl px-4 py-5 shadow-[var(--shadow-card)] ${fill}`}>
            <p className="text-sm font-medium opacity-90">{label}</p>
            <p className="mt-1.5 text-xl font-semibold tracking-tight tabular-nums md:text-2xl">
                {value}
            </p>
        </div>
    );
}

function Field({
    label,
    name,
    error,
    ...props
}: {
    label: string;
    name: string;
    error?: string;
} & InputHTMLAttributes<HTMLInputElement>) {
    return (
        <label className="grid gap-1.5 text-sm">
            <span className="font-medium text-foreground">{label}</span>
            <input name={name} className={control} {...props} />
            {error ? (
                <span className="text-xs font-medium text-destructive">
                    {error}
                </span>
            ) : null}
        </label>
    );
}

function AccountSelect({ accounts }: { accounts: Account[] }) {
    return (
        <label className="grid gap-1.5 text-sm">
            <span className="font-medium text-foreground">Linked account</span>
            <select
                name="money_account_id"
                defaultValue=""
                className={control}
            >
                <option value="">None</option>
                {accounts.map((account) => (
                    <option key={account.id} value={account.id}>
                        {account.name}
                    </option>
                ))}
            </select>
        </label>
    );
}

MoneyIndex.layout = {
    breadcrumbs: [{ title: 'Money', href: '/money' }],
};