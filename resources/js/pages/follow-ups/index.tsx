import { Form, Head } from '@inertiajs/react';
import { AreaField, SelectField, TextField } from '@/components/crm/fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { Option } from '@/types/crm';

type ProductOption = {
    id: number;
    name: string;
};

type Rule = {
    id: number;
    name: string;
    trigger: string;
    action: string;
    delay_hours: number | null;
    local_time: string | null;
    body: string;
    use_conversation_memory: boolean;
    product_id: number | null;
    product_name: string | null;
    is_active: boolean;
};

type Task = {
    id: number;
    lead_id: number;
    contact_name: string;
    rule: string | null;
    action: string;
    status: string;
    due_label: string | null;
    held_reason: string | null;
    failure_reason: string | null;
};

const triggerOptions: Option[] = [
    { value: 'no_response', label: 'No response after an outbound WhatsApp' },
    { value: 'follow_up_due', label: 'Follow-up date on the lead' },
    { value: 'stage_delay', label: 'Delay stored on the current stage' },
];

const actionOptions: Option[] = [
    { value: 'send_whatsapp', label: 'Send WhatsApp' },
    { value: 'operator_task', label: 'Desk task' },
];

const activeOptions: Option[] = [
    { value: '1', label: 'Active' },
    { value: '0', label: 'Inactive' },
];

export default function FollowUps({
    can_manage,
    products,
    rules,
    tasks,
}: {
    can_manage: boolean;
    products: ProductOption[];
    rules: Rule[];
    tasks: Task[];
}) {
    const productOptions: Option[] = [
        { value: 'none', label: 'Any product' },
        ...products.map((product) => ({
            value: String(product.id),
            label: product.name,
        })),
    ];

    return (
        <>
            <Head title="Follow-ups" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Follow-ups
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Rules schedule tasks.{' '}
                        <code className="text-xs">php artisan follow-ups:run</code>{' '}
                        sends the WhatsApp ones that are due and still allowed.
                        The scheduler calls that command every minute when{' '}
                        <code className="text-xs">php artisan schedule:work</code>{' '}
                        or cron is running. This page does not start the
                        scheduler.
                    </p>
                </div>

                <section className="grid gap-4">
                    <div>
                        <h2 className="text-sm font-medium">Rules</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Use {'{contact}'}, {'{business}'}, and {'{action}'}
                            in the message. No-response and stage-delay tasks
                            are cancelled when the lead receives a WhatsApp
                            reply. A follow-up date uses the rule&apos;s Nairobi
                            time. Stage delay uses the hours stored on the
                            stage, not a number in this form.
                        </p>
                    </div>

                    {can_manage ? (
                        <RuleForm
                            action="/follow-ups"
                            method="post"
                            productOptions={productOptions}
                            submitLabel="Save rule"
                        />
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            An owner adds and edits rules.
                        </p>
                    )}

                    {rules.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No rules yet. Nothing is scheduled.
                        </p>
                    ) : (
                        rules.map((rule) => (
                            <article
                                key={rule.id}
                                className="rounded-lg border p-4"
                            >
                                {can_manage ? (
                                    <RuleForm
                                        action={`/follow-ups/${rule.id}`}
                                        method="put"
                                        productOptions={productOptions}
                                        submitLabel="Update rule"
                                        rule={rule}
                                    />
                                ) : (
                                    <RuleSummary rule={rule} />
                                )}
                            </article>
                        ))
                    )}
                </section>

                <section className="grid gap-3">
                    <h2 className="text-sm font-medium">Recent tasks</h2>
                    {tasks.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No tasks yet.
                        </p>
                    ) : (
                        <ul className="divide-y rounded-lg border">
                            {tasks.map((task) => (
                                <li
                                    key={task.id}
                                    className="flex flex-col gap-1 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <a
                                            href={`/leads/${task.lead_id}`}
                                            className="font-medium hover:underline"
                                        >
                                            {task.contact_name}
                                        </a>
                                        <p className="text-xs text-muted-foreground">
                                            {task.rule ?? 'Follow-up'} · due{' '}
                                            {task.due_label ?? 'unscheduled'}
                                        </p>
                                        {task.held_reason ? (
                                            <p className="text-xs">
                                                {task.held_reason}
                                            </p>
                                        ) : null}
                                        {task.failure_reason ? (
                                            <p className="text-xs text-destructive">
                                                {task.failure_reason}
                                            </p>
                                        ) : null}
                                    </div>
                                    <Badge variant="outline">{task.status}</Badge>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

function RuleSummary({ rule }: { rule: Rule }) {
    return (
        <div className="grid gap-2 text-sm">
            <div className="flex flex-wrap items-center gap-2">
                <h3 className="font-medium">{rule.name}</h3>
                <Badge variant="outline">
                    {rule.is_active ? 'Active' : 'Inactive'}
                </Badge>
            </div>
            <p className="text-muted-foreground">
                {rule.trigger} · {rule.action}
                {rule.delay_hours !== null ? ` · ${rule.delay_hours}h` : ''}
                {rule.local_time ? ` · ${rule.local_time} Nairobi` : ''}
                {rule.product_name ? ` · ${rule.product_name}` : ''}
                {rule.use_conversation_memory
                    ? ' · chat memory on'
                    : ' · template only'}
            </p>
            <p className="whitespace-pre-wrap">{rule.body}</p>
        </div>
    );
}

function RuleForm({
    action,
    method,
    productOptions,
    submitLabel,
    rule,
}: {
    action: string;
    method: 'post' | 'put';
    productOptions: Option[];
    submitLabel: string;
    rule?: Rule;
}) {
    return (
        <Form action={action} method={method} className="grid gap-3">
            {({ errors, processing }) => (
                <>
                    <TextField
                        name="name"
                        label="Name"
                        required
                        defaultValue={rule?.name}
                        error={errors.name}
                    />
                    <SelectField
                        name="trigger"
                        label="When"
                        options={triggerOptions}
                        defaultValue={rule?.trigger ?? 'no_response'}
                        error={errors.trigger}
                    />
                    <SelectField
                        name="action"
                        label="Do"
                        options={actionOptions}
                        defaultValue={rule?.action ?? 'operator_task'}
                        error={errors.action}
                    />
                    <TextField
                        name="delay_hours"
                        label="Hours after the last outbound"
                        type="number"
                        defaultValue={rule?.delay_hours ?? ''}
                        error={errors.delay_hours}
                        hint="Required for no response. Ignored for the other triggers."
                    />
                    <TextField
                        name="local_time"
                        label="Nairobi time"
                        type="time"
                        defaultValue={rule?.local_time ?? ''}
                        error={errors.local_time}
                        hint="Required when the trigger is the follow-up date."
                    />
                    <AreaField
                        name="body"
                        label="Message or task"
                        required
                        defaultValue={rule?.body}
                        error={errors.body}
                        hint="Used as fallback. With chat memory on, WhatsApp follow-ups are written from this person's history + knowledge."
                    />
                    <SelectField
                        name="use_conversation_memory"
                        label="Chat memory / RAG"
                        options={[
                            {
                                value: '1',
                                label: 'On — read prior chats and reply in context',
                            },
                            {
                                value: '0',
                                label: 'Off — send the template only',
                            },
                        ]}
                        defaultValue={
                            rule
                                ? rule.use_conversation_memory
                                    ? '1'
                                    : '0'
                                : '1'
                        }
                        error={errors.use_conversation_memory}
                    />
                    <SelectField
                        name="product_id"
                        label="Product"
                        options={productOptions}
                        defaultValue={
                            rule?.product_id ? String(rule.product_id) : 'none'
                        }
                        error={errors.product_id}
                    />
                    <SelectField
                        name="is_active"
                        label="Status"
                        options={activeOptions}
                        defaultValue={rule ? (rule.is_active ? '1' : '0') : '1'}
                        error={errors.is_active}
                    />
                    <div>
                        <Button type="submit" disabled={processing}>
                            {submitLabel}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
