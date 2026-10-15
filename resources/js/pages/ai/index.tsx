import { Form, Head } from '@inertiajs/react';
import { AreaField, SelectField, TextField } from '@/components/crm/fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { Option } from '@/types/crm';

type Variable = {
    key: string;
    configured: boolean;
};

type ProductOption = {
    id: number;
    name: string;
};

type Article = {
    id: number;
    title: string;
    body: string;
    product_id: number | null;
    product_name: string | null;
    is_active: boolean;
    source: string;
    synced_at: string | null;
};

type WhatsAppSync = {
    id: number;
    status: string;
    articles_created: number;
    messages_scanned: number;
    summary: string | null;
    error: string | null;
    completed_at: string | null;
};

type AutoReplyRule = {
    id: number;
    name: string;
    is_active: boolean;
    priority: number;
    match_type: string;
    match_value: string | null;
    pipeline_stage_id: number | null;
    stage_name: string | null;
    reply_mode: string;
    template_body: string | null;
    require_open_window: boolean;
};

export default function IntelligenceSettings({
    variables,
    configured,
    provider,
    can_manage,
    products,
    articles,
    stages,
    auto_reply_rules,
    whatsapp_syncs = [],
}: {
    variables: Variable[];
    configured: boolean;
    provider: string;
    can_manage: boolean;
    products: ProductOption[];
    articles: Article[];
    stages: Array<{ id: number; name: string }>;
    auto_reply_rules: AutoReplyRule[];
    whatsapp_syncs?: WhatsAppSync[];
}) {
    const productOptions: Option[] = [
        { value: 'none', label: 'Any product' },
        ...products.map((product) => ({
            value: String(product.id),
            label: product.name,
        })),
    ];
    const activeOptions: Option[] = [
        { value: '1', label: 'Active' },
        { value: '0', label: 'Inactive' },
    ];

    return (
        <>
            <Head title="Intelligence" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Intelligence
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Provider:{' '}
                        <span className="font-mono text-xs">{provider}</span>.
                        Pull knowledge from your WhatsApp Business / Meta agent
                        inbox, keep product notes, and optionally run a live
                        webhook assistant.
                    </p>
                </div>

                <section className="rounded-lg border p-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 className="text-sm font-medium">
                                WhatsApp / Meta knowledge pull
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Syncs Meta WhatsApp Business profile fields and
                                mines frequent inbound questions your Meta
                                agent / desk already collected into knowledge
                                notes the assistant can reuse.
                            </p>
                        </div>
                        {can_manage ? (
                            <Form action="/ai/whatsapp-sync" method="post">
                                {({ processing }) => (
                                    <Button type="submit" disabled={processing}>
                                        Pull WhatsApp knowledge
                                    </Button>
                                )}
                            </Form>
                        ) : null}
                    </div>
                    <ul className="mt-4 divide-y text-sm">
                        {whatsapp_syncs.length === 0 ? (
                            <li className="py-2 text-muted-foreground">
                                No sync runs yet.
                            </li>
                        ) : (
                            whatsapp_syncs.map((run) => (
                                <li key={run.id} className="py-2">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge variant="outline">
                                            {run.status}
                                        </Badge>
                                        <span>
                                            {run.articles_created} notes ·{' '}
                                            {run.messages_scanned} messages
                                        </span>
                                    </div>
                                    <p className="mt-1 text-muted-foreground">
                                        {run.summary ?? run.error ?? '—'}
                                    </p>
                                </li>
                            ))
                        )}
                    </ul>
                </section>

                <section className="rounded-lg border p-4">
                    <h2 className="text-sm font-medium">
                        Conditional auto-replies
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Inbound WhatsApp is silent unless a rule matches.
                        Template mode sends your wording. LLM mode only runs
                        after a match when LLM_* is configured.
                    </p>

                    {can_manage ? (
                        <Form
                            action="/ai/auto-replies"
                            method="post"
                            resetOnSuccess
                            className="mt-4 grid gap-3"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <TextField
                                        name="name"
                                        label="Rule name"
                                        required
                                        error={errors.name}
                                    />
                                    <SelectField
                                        name="match_type"
                                        label="When message"
                                        defaultValue="contains"
                                        options={[
                                            {
                                                value: 'contains',
                                                label: 'Contains text',
                                            },
                                            {
                                                value: 'exact',
                                                label: 'Exact text',
                                            },
                                            {
                                                value: 'any',
                                                label: 'Any inbound text',
                                            },
                                        ]}
                                        error={errors.match_type}
                                    />
                                    <TextField
                                        name="match_value"
                                        label="Match text"
                                        hint="Leave empty only for “any”."
                                        error={errors.match_value}
                                    />
                                    <SelectField
                                        name="pipeline_stage_id"
                                        label="Only in stage"
                                        defaultValue="none"
                                        options={[
                                            {
                                                value: 'none',
                                                label: 'Any stage',
                                            },
                                            ...stages.map((stage) => ({
                                                value: String(stage.id),
                                                label: stage.name,
                                            })),
                                        ]}
                                        error={errors.pipeline_stage_id}
                                    />
                                    <SelectField
                                        name="reply_mode"
                                        label="Reply with"
                                        defaultValue="template"
                                        options={[
                                            {
                                                value: 'template',
                                                label: 'Fixed template',
                                            },
                                            {
                                                value: 'llm',
                                                label: 'Live LLM under this rule',
                                            },
                                        ]}
                                        error={errors.reply_mode}
                                    />
                                    <AreaField
                                        name="template_body"
                                        label="Template body"
                                        hint="{contact}, {business}, {message}"
                                        error={errors.template_body}
                                    />
                                    <TextField
                                        name="priority"
                                        label="Priority"
                                        type="number"
                                        defaultValue={100}
                                        hint="Lower runs first."
                                        error={errors.priority}
                                    />
                                    <SelectField
                                        name="is_active"
                                        label="Status"
                                        defaultValue="1"
                                        options={activeOptions}
                                        error={errors.is_active}
                                    />
                                    <SelectField
                                        name="require_open_window"
                                        label="Require 24h window"
                                        defaultValue="1"
                                        options={[
                                            {
                                                value: '1',
                                                label: 'Yes',
                                            },
                                            {
                                                value: '0',
                                                label: 'No',
                                            },
                                        ]}
                                        error={errors.require_open_window}
                                    />
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                    >
                                        Save auto-reply rule
                                    </Button>
                                </>
                            )}
                        </Form>
                    ) : (
                        <p className="mt-3 text-sm text-muted-foreground">
                            Only the owner can add auto-reply rules.
                        </p>
                    )}

                    <ul className="mt-4 divide-y text-sm">
                        {auto_reply_rules.length === 0 ? (
                            <li className="py-2 text-muted-foreground">
                                No rules yet. Without a rule, the desk never
                                auto-replies.
                            </li>
                        ) : (
                            auto_reply_rules.map((rule) => (
                                <li
                                    key={rule.id}
                                    className="flex flex-col gap-2 py-3 sm:flex-row sm:items-start sm:justify-between"
                                >
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-medium">
                                                {rule.name}
                                            </span>
                                            <Badge variant="outline">
                                                {rule.is_active
                                                    ? 'Active'
                                                    : 'Off'}
                                            </Badge>
                                            <Badge variant="outline">
                                                {rule.reply_mode}
                                            </Badge>
                                        </div>
                                        <p className="mt-1 text-muted-foreground">
                                            {rule.match_type}
                                            {rule.match_value
                                                ? `: ${rule.match_value}`
                                                : ''}{' '}
                                            · stage{' '}
                                            {rule.stage_name ?? 'any'} ·
                                            priority {rule.priority}
                                        </p>
                                        {rule.template_body ? (
                                            <p className="mt-1 whitespace-pre-wrap text-xs text-muted-foreground">
                                                {rule.template_body}
                                            </p>
                                        ) : null}
                                    </div>
                                    {can_manage ? (
                                        <Form
                                            action={`/ai/auto-replies/${rule.id}`}
                                            method="delete"
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    variant="ghost"
                                                    size="sm"
                                                    disabled={processing}
                                                >
                                                    Remove
                                                </Button>
                                            )}
                                        </Form>
                                    ) : null}
                                </li>
                            ))
                        )}
                    </ul>
                </section>

                <section className="rounded-lg border p-4">
                    <h2 className="text-sm font-medium">Configuration</h2>
                    <ul className="mt-3 divide-y text-sm">
                        {variables.map((variable) => (
                            <li
                                key={variable.key}
                                className="flex items-center justify-between gap-3 py-2"
                            >
                                <span className="font-mono text-xs">
                                    {variable.key}
                                </span>
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
                    <p className="mt-3 text-sm text-muted-foreground">
                        {configured
                            ? 'All three variables are set. A read still waits for you to apply fields or send a reply.'
                            : 'Missing variables mean a read is stored as unavailable. The desk does not invent a summary or a score.'}
                    </p>
                    <p className="mt-2 text-xs text-muted-foreground">
                        LLM_BASE_URL is the OpenAI-compatible root, for example
                        the provider&apos;s /v1 path. The desk posts to
                        /chat/completions on that root.
                    </p>
                </section>

                <section className="grid gap-4">
                    <div>
                        <h2 className="text-sm font-medium">Knowledge</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Active notes are retrieved by shared words, with a
                            boost when the note is tied to the lead&apos;s
                            product. They are quoted into the prompt. They are
                            not sent to WhatsApp.
                        </p>
                    </div>

                    {can_manage ? (
                        <Form
                            action="/ai/articles"
                            method="post"
                            resetOnSuccess
                            className="grid gap-3 rounded-lg border p-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <TextField
                                        name="title"
                                        label="Title"
                                        required
                                        error={errors.title}
                                    />
                                    <AreaField
                                        name="body"
                                        label="Note"
                                        required
                                        error={errors.body}
                                    />
                                    <SelectField
                                        name="product_id"
                                        label="Product"
                                        options={productOptions}
                                        defaultValue="none"
                                        error={errors.product_id}
                                    />
                                    <SelectField
                                        name="is_active"
                                        label="Status"
                                        options={activeOptions}
                                        defaultValue="1"
                                        error={errors.is_active}
                                    />
                                    <div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Save note
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            An owner adds and edits knowledge notes.
                        </p>
                    )}

                    {articles.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No knowledge notes yet.
                        </p>
                    ) : (
                        articles.map((article) => (
                            <article
                                key={article.id}
                                className="rounded-lg border p-4"
                            >
                                {can_manage ? (
                                    <Form
                                        action={`/ai/articles/${article.id}`}
                                        method="put"
                                        className="grid gap-3"
                                    >
                                        {({ errors, processing }) => (
                                            <>
                                                <TextField
                                                    name="title"
                                                    label="Title"
                                                    required
                                                    defaultValue={article.title}
                                                    error={errors.title}
                                                />
                                                <AreaField
                                                    name="body"
                                                    label="Note"
                                                    required
                                                    defaultValue={article.body}
                                                    error={errors.body}
                                                />
                                                <SelectField
                                                    name="product_id"
                                                    label="Product"
                                                    options={productOptions}
                                                    defaultValue={
                                                        article.product_id
                                                            ? String(
                                                                  article.product_id,
                                                              )
                                                            : 'none'
                                                    }
                                                    error={errors.product_id}
                                                />
                                                <SelectField
                                                    name="is_active"
                                                    label="Status"
                                                    options={activeOptions}
                                                    defaultValue={
                                                        article.is_active
                                                            ? '1'
                                                            : '0'
                                                    }
                                                    error={errors.is_active}
                                                />
                                                <div>
                                                    <Button
                                                        type="submit"
                                                        variant="outline"
                                                        disabled={processing}
                                                    >
                                                        Update note
                                                    </Button>
                                                </div>
                                            </>
                                        )}
                                    </Form>
                                ) : (
                                    <div className="grid gap-2 text-sm">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h3 className="font-medium">
                                                {article.title}
                                                </h3>
                                            <Badge variant="outline">
                                                {article.source}
                                            </Badge>
                                            <Badge variant="outline">
                                                {article.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </Badge>
                                        </div>
                                        <p className="whitespace-pre-wrap text-muted-foreground">
                                            {article.body}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {article.product_name ??
                                                'Any product'}
                                        </p>
                                    </div>
                                )}
                            </article>
                        ))
                    )}
                </section>
            </div>
        </>
    );
}
