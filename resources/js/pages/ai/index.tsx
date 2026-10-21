import { Form, Head, Link } from '@inertiajs/react';
import {
    BookOpen,
    CheckCircle2,
    CircleAlert,
    HelpCircle,
    MessageCircle,
    Plus,
    RefreshCw,
    Search,
    Settings2,
    Trash2,
    Zap,
} from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';
import { useMemo, useState } from 'react';
import { AreaField, SelectField, TextField } from '@/components/crm/fields';
import {
    Page,
    PageHeader,
    surfaceClass,
} from '@/components/crm/page-frame';
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
    category?: string;
    product_id: number | null;
    product_name: string | null;
    is_active: boolean;
    source: string;
    synced_at: string | null;
};

type KnowledgeGap = {
    id: number;
    question: string;
    context_snippet: string | null;
    source: string;
    contact_name: string | null;
    product_name: string | null;
    stage_name: string | null;
    lead_status: string | null;
    lead_id: number | null;
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

type TabKey = 'questions' | 'knowledge' | 'replies' | 'sync' | 'config';

const card = surfaceClass;

function formatWhen(value: string | null): string | null {
    if (!value) {
        return null;
    }
    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? value
        : date.toLocaleString(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          });
}

const matchLabels: Record<string, string> = {
    contains: 'Contains',
    exact: 'Exactly',
    any: 'Any message',
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
    knowledge_gaps = [],
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
    knowledge_gaps?: KnowledgeGap[];
}) {
    const [tab, setTab] = useState<TabKey>(
        knowledge_gaps.length > 0 ? 'questions' : 'knowledge',
    );
    const [query, setQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState<
        'all' | 'active' | 'inactive'
    >('all');

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
    const categoryOptions: Option[] = [
        { value: 'fact', label: 'Fact' },
        { value: 'lesson', label: 'Lesson' },
        { value: 'session', label: 'Physical session' },
    ];

    const configuredCount = variables.filter((v) => v.configured).length;
    const activeRules = auto_reply_rules.filter((r) => r.is_active).length;

    const filteredArticles = useMemo(() => {
        const needle = query.trim().toLowerCase();

        return articles.filter((article) => {
            if (statusFilter === 'active' && !article.is_active) {
                return false;
            }
            if (statusFilter === 'inactive' && article.is_active) {
                return false;
            }
            if (!needle) {
                return true;
            }

            return (
                article.title.toLowerCase().includes(needle) ||
                article.body.toLowerCase().includes(needle) ||
                (article.product_name ?? '').toLowerCase().includes(needle)
            );
        });
    }, [articles, query, statusFilter]);

    const tabs: Array<{
        key: TabKey;
        label: string;
        icon: ComponentType<{ className?: string }>;
        count?: number;
        alert?: boolean;
    }> = [
        {
            key: 'questions',
            label: 'Questions',
            icon: HelpCircle,
            count: knowledge_gaps.length,
            alert: knowledge_gaps.length > 0,
        },
        {
            key: 'knowledge',
            label: 'Knowledge',
            icon: BookOpen,
            count: articles.length,
        },
        {
            key: 'replies',
            label: 'Auto-replies',
            icon: Zap,
            count: activeRules,
        },
        { key: 'sync', label: 'WhatsApp sync', icon: MessageCircle },
        {
            key: 'config',
            label: 'Configuration',
            icon: Settings2,
            alert: !configured,
        },
    ];

    return (
        <>
            <Head title="Intelligence" />
            <Page width="content">
                <PageHeader
                    title="Intelligence"
                    description="Teach the desk what it should know, review what it could not answer, and control when it replies on WhatsApp."
                    actions={
                        <>
                            <Badge
                                variant="outline"
                                className="font-mono text-xs"
                            >
                                {provider}
                            </Badge>
                            <Badge
                                variant={
                                    configured ? 'default' : 'destructive'
                                }
                            >
                                {configured
                                    ? 'Provider ready'
                                    : `${configuredCount}/${variables.length} configured`}
                            </Badge>
                        </>
                    }
                />

                {!can_manage ? (
                    <p
                        role="note"
                        className="rounded-lg border border-border bg-muted/60 px-4 py-3 text-sm text-muted-foreground"
                    >
                        You have view-only access. Only the owner can add or
                        change notes, answers and auto-reply rules.
                    </p>
                ) : null}

                {/* Tabs */}
                <div
                    role="tablist"
                    aria-label="Intelligence sections"
                    className="-mx-4 flex gap-1 overflow-x-auto border-b border-border px-4 md:mx-0 md:px-0"
                >
                    {tabs.map((item) => {
                        const Icon = item.icon;
                        const selected = tab === item.key;

                        return (
                            <button
                                key={item.key}
                                type="button"
                                role="tab"
                                id={`tab-${item.key}`}
                                aria-selected={selected}
                                aria-controls={`panel-${item.key}`}
                                onClick={() => setTab(item.key)}
                                className={`-mb-px inline-flex shrink-0 items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-medium outline-none transition-colors focus-visible:ring-2 focus-visible:ring-ring ${
                                    selected
                                        ? 'border-primary text-foreground'
                                        : 'border-transparent text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                <Icon className="size-4" />
                                {item.label}
                                {item.count !== undefined ? (
                                    <span
                                        className={`rounded-full px-1.5 py-0.5 text-xs font-semibold tabular-nums ${
                                            item.alert
                                                ? 'bg-destructive text-white'
                                                : 'bg-muted text-muted-foreground'
                                        }`}
                                    >
                                        {item.count}
                                    </span>
                                ) : item.alert ? (
                                    <span className="size-2 rounded-full bg-destructive" />
                                ) : null}
                            </button>
                        );
                    })}
                </div>

                {/* Questions ------------------------------------------------ */}
                {tab === 'questions' ? (
                    <Panel
                        id="questions"
                        title="Unfamiliar questions"
                        hint="Raised from live chats and inbox sync when the desk had nothing to ground an answer on. Feed a fact, lesson or physical-session answer so it learns."
                    >
                        {knowledge_gaps.length === 0 ? (
                            <Empty
                                icon={CheckCircle2}
                                title="Nothing to answer"
                                body="No open unfamiliar questions. New ones appear here as chats come in."
                            />
                        ) : (
                            <ul className="grid gap-4">
                                {knowledge_gaps.map((gap) => (
                                    <li key={gap.id} className={card}>
                                        <div className="p-5">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Badge variant="outline">
                                                    {gap.source}
                                                </Badge>
                                                {gap.product_name ? (
                                                    <Badge variant="laundry">
                                                        {gap.product_name}
                                                    </Badge>
                                                ) : null}
                                                {gap.stage_name ? (
                                                    <Badge variant="outline">
                                                        {gap.stage_name}
                                                    </Badge>
                                                ) : null}
                                                {gap.lead_status ? (
                                                    <Badge variant="lead">
                                                        {gap.lead_status}
                                                    </Badge>
                                                ) : null}
                                            </div>
                                            <p className="mt-3 text-base font-medium leading-snug">
                                                {gap.contact_name ? (
                                                    <span className="text-muted-foreground">
                                                        {gap.contact_name}:{' '}
                                                    </span>
                                                ) : null}
                                                {gap.question}
                                            </p>
                                            {gap.context_snippet &&
                                            gap.context_snippet !==
                                                gap.question ? (
                                                <p className="mt-1.5 border-l-2 border-border pl-3 text-sm text-muted-foreground">
                                                    {gap.context_snippet}
                                                </p>
                                            ) : null}
                                            {gap.lead_id ? (
                                                <Link
                                                    href={`/leads/${gap.lead_id}`}
                                                    className="mt-2 inline-block text-sm font-medium text-primary hover:underline"
                                                >
                                                    Open lead →
                                                </Link>
                                            ) : null}
                                        </div>

                                        {can_manage ? (
                                            <Form
                                                action={`/ai/gaps/${gap.id}/answer`}
                                                method="post"
                                                className="grid gap-4 border-t border-border bg-muted/30 p-5 md:grid-cols-2"
                                            >
                                                {({ errors, processing }) => (
                                                    <>
                                                        <AreaField
                                                            name="answer"
                                                            label="Your fact / answer"
                                                            required
                                                            error={
                                                                errors.answer
                                                            }
                                                        />
                                                        <div className="grid content-start gap-3">
                                                            <SelectField
                                                                name="category"
                                                                label="Save as"
                                                                options={
                                                                    categoryOptions
                                                                }
                                                                defaultValue="fact"
                                                                error={
                                                                    errors.category
                                                                }
                                                            />
                                                            <TextField
                                                                name="title"
                                                                label="Optional title"
                                                                error={
                                                                    errors.title
                                                                }
                                                            />
                                                            <Button
                                                                type="submit"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                {processing
                                                                    ? 'Saving…'
                                                                    : 'Save lesson'}
                                                            </Button>
                                                        </div>
                                                    </>
                                                )}
                                            </Form>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                ) : null}

                {/* Knowledge ------------------------------------------------ */}
                {tab === 'knowledge' ? (
                    <Panel
                        id="knowledge"
                        title="Knowledge notes"
                        hint="Active notes are matched by shared words, with a boost when tied to the lead's product. They are quoted into the prompt and never sent to WhatsApp."
                    >
                        {can_manage ? (
                            <details
                                className={card}
                                open={articles.length === 0}
                            >
                                <summary className="flex cursor-pointer select-none items-center gap-2 px-5 py-4 text-sm font-semibold outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                    <Plus className="size-4 text-primary" />
                                    Add a note
                                </summary>
                                <Form
                                    action="/ai/articles"
                                    method="post"
                                    resetOnSuccess
                                    className="grid gap-4 border-t border-border p-5 md:grid-cols-2"
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <div className="md:col-span-2">
                                                <TextField
                                                    name="title"
                                                    label="Title"
                                                    required
                                                    error={errors.title}
                                                />
                                            </div>
                                            <div className="md:col-span-2">
                                                <AreaField
                                                    name="body"
                                                    label="Note"
                                                    required
                                                    error={errors.body}
                                                />
                                            </div>
                                            <SelectField
                                                name="category"
                                                label="Type"
                                                options={categoryOptions}
                                                defaultValue="fact"
                                                error={errors.category}
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
                                            <div className="flex items-end md:justify-end">
                                                <Button
                                                    type="submit"
                                                    disabled={processing}
                                                    className="w-full md:w-auto"
                                                >
                                                    {processing
                                                        ? 'Saving…'
                                                        : 'Save note'}
                                                </Button>
                                            </div>
                                        </>
                                    )}
                                </Form>
                            </details>
                        ) : null}

                        {articles.length > 0 ? (
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                                <div className="relative flex-1">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <input
                                        type="search"
                                        value={query}
                                        onChange={(e) =>
                                            setQuery(e.target.value)
                                        }
                                        placeholder="Search notes by title, text or product"
                                        aria-label="Search notes"
                                        className="h-10 w-full rounded-md border border-input bg-card pr-3 pl-9 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    />
                                </div>
                                <div
                                    role="group"
                                    aria-label="Filter by status"
                                    className="inline-flex rounded-md border border-border bg-card p-0.5 text-sm"
                                >
                                    {(
                                        ['all', 'active', 'inactive'] as const
                                    ).map((value) => (
                                        <button
                                            key={value}
                                            type="button"
                                            aria-pressed={statusFilter === value}
                                            onClick={() =>
                                                setStatusFilter(value)
                                            }
                                            className={`rounded px-3 py-1.5 font-medium capitalize outline-none focus-visible:ring-2 focus-visible:ring-ring ${
                                                statusFilter === value
                                                    ? 'bg-primary text-primary-foreground'
                                                    : 'text-muted-foreground hover:text-foreground'
                                            }`}
                                        >
                                            {value}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        ) : null}

                        {articles.length === 0 ? (
                            <Empty
                                icon={BookOpen}
                                title="No knowledge notes yet"
                                body={
                                    can_manage
                                        ? 'Add your first note above, or answer an unfamiliar question.'
                                        : 'An owner adds and edits knowledge notes.'
                                }
                            />
                        ) : filteredArticles.length === 0 ? (
                            <Empty
                                icon={Search}
                                title="No notes match"
                                body="Try a different search or status filter."
                            />
                        ) : (
                            <ul className="grid gap-3">
                                {filteredArticles.map((article) => (
                                    <li key={article.id} className={card}>
                                        <div className="p-5">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h3 className="font-semibold">
                                                    {article.title}
                                                </h3>
                                                <StatusBadge
                                                    active={article.is_active}
                                                />
                                                {article.category ? (
                                                    <Badge
                                                        variant="outline"
                                                        className="capitalize"
                                                    >
                                                        {article.category}
                                                    </Badge>
                                                ) : null}
                                                <Badge variant="outline">
                                                    {article.source}
                                                </Badge>
                                            </div>
                                            <p className="mt-2 line-clamp-3 text-sm whitespace-pre-wrap text-muted-foreground">
                                                {article.body}
                                            </p>
                                            <p className="mt-2 text-xs text-muted-foreground">
                                                {article.product_name ??
                                                    'Any product'}
                                                {article.synced_at
                                                    ? ` · synced ${formatWhen(article.synced_at)}`
                                                    : ''}
                                            </p>
                                        </div>

                                        {can_manage ? (
                                            <details className="border-t border-border">
                                                <summary className="cursor-pointer select-none px-5 py-3 text-sm font-medium text-primary outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                                    Edit note
                                                </summary>
                                                <Form
                                                    action={`/ai/articles/${article.id}`}
                                                    method="put"
                                                    className="grid gap-4 border-t border-border bg-muted/30 p-5 md:grid-cols-2"
                                                >
                                                    {({
                                                        errors,
                                                        processing,
                                                    }) => (
                                                        <>
                                                            <div className="md:col-span-2">
                                                                <TextField
                                                                    name="title"
                                                                    label="Title"
                                                                    required
                                                                    defaultValue={
                                                                        article.title
                                                                    }
                                                                    error={
                                                                        errors.title
                                                                    }
                                                                />
                                                            </div>
                                                            <div className="md:col-span-2">
                                                                <AreaField
                                                                    name="body"
                                                                    label="Note"
                                                                    required
                                                                    defaultValue={
                                                                        article.body
                                                                    }
                                                                    error={
                                                                        errors.body
                                                                    }
                                                                />
                                                            </div>
                                                            <SelectField
                                                                name="product_id"
                                                                label="Product"
                                                                options={
                                                                    productOptions
                                                                }
                                                                defaultValue={
                                                                    article.product_id
                                                                        ? String(
                                                                              article.product_id,
                                                                          )
                                                                        : 'none'
                                                                }
                                                                error={
                                                                    errors.product_id
                                                                }
                                                            />
                                                            <SelectField
                                                                name="is_active"
                                                                label="Status"
                                                                options={
                                                                    activeOptions
                                                                }
                                                                defaultValue={
                                                                    article.is_active
                                                                        ? '1'
                                                                        : '0'
                                                                }
                                                                error={
                                                                    errors.is_active
                                                                }
                                                            />
                                                            <div className="md:col-span-2">
                                                                <Button
                                                                    type="submit"
                                                                    disabled={
                                                                        processing
                                                                    }
                                                                >
                                                                    {processing
                                                                        ? 'Updating…'
                                                                        : 'Update note'}
                                                                </Button>
                                                            </div>
                                                        </>
                                                    )}
                                                </Form>
                                            </details>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                ) : null}

                {/* Auto-replies --------------------------------------------- */}
                {tab === 'replies' ? (
                    <Panel
                        id="replies"
                        title="Conditional auto-replies"
                        hint="Inbound WhatsApp stays silent unless a rule matches. Template mode sends your exact wording. LLM mode only runs after a match, and only when LLM_* is configured."
                    >
                        {can_manage ? (
                            <details
                                className={card}
                                open={auto_reply_rules.length === 0}
                            >
                                <summary className="flex cursor-pointer select-none items-center gap-2 px-5 py-4 text-sm font-semibold outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                    <Plus className="size-4 text-primary" />
                                    Add a rule
                                </summary>
                                <Form
                                    action="/ai/auto-replies"
                                    method="post"
                                    resetOnSuccess
                                    className="grid gap-4 border-t border-border p-5 md:grid-cols-2"
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <div className="md:col-span-2">
                                                <TextField
                                                    name="name"
                                                    label="Rule name"
                                                    required
                                                    error={errors.name}
                                                />
                                            </div>
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
                                                error={
                                                    errors.pipeline_stage_id
                                                }
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
                                            <div className="md:col-span-2">
                                                <AreaField
                                                    name="template_body"
                                                    label="Template body"
                                                    hint="Placeholders: {contact}, {business}, {message}"
                                                    error={
                                                        errors.template_body
                                                    }
                                                />
                                            </div>
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
                                                    { value: '1', label: 'Yes' },
                                                    { value: '0', label: 'No' },
                                                ]}
                                                error={
                                                    errors.require_open_window
                                                }
                                            />
                                            <div className="flex items-end md:justify-end">
                                                <Button
                                                    type="submit"
                                                    disabled={processing}
                                                    className="w-full md:w-auto"
                                                >
                                                    {processing
                                                        ? 'Saving…'
                                                        : 'Save rule'}
                                                </Button>
                                            </div>
                                        </>
                                    )}
                                </Form>
                            </details>
                        ) : null}

                        {auto_reply_rules.length === 0 ? (
                            <Empty
                                icon={Zap}
                                title="No rules yet"
                                body="Without a rule, the desk never auto-replies."
                            />
                        ) : (
                            <ul className="grid gap-3">
                                {[...auto_reply_rules]
                                    .sort((a, b) => a.priority - b.priority)
                                    .map((rule) => (
                                        <li
                                            key={rule.id}
                                            className={`${card} flex flex-col gap-3 p-5 sm:flex-row sm:items-start sm:justify-between ${
                                                rule.is_active
                                                    ? ''
                                                    : 'opacity-70'
                                            }`}
                                        >
                                            <div className="min-w-0">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="text-xs font-semibold text-muted-foreground tabular-nums">
                                                        #{rule.priority}
                                                    </span>
                                                    <h3 className="font-semibold">
                                                        {rule.name}
                                                    </h3>
                                                    <StatusBadge
                                                        active={rule.is_active}
                                                    />
                                                    <Badge variant="outline">
                                                        {rule.reply_mode ===
                                                        'llm'
                                                            ? 'LLM'
                                                            : 'Template'}
                                                    </Badge>
                                                </div>
                                                <p className="mt-1.5 text-sm text-muted-foreground">
                                                    When message{' '}
                                                    <strong className="font-medium text-foreground">
                                                        {(
                                                            matchLabels[
                                                                rule.match_type
                                                            ] ?? rule.match_type
                                                        ).toLowerCase()}
                                                    </strong>
                                                    {rule.match_value
                                                        ? ` “${rule.match_value}”`
                                                        : ''}{' '}
                                                    · stage{' '}
                                                    {rule.stage_name ?? 'any'}
                                                    {rule.require_open_window
                                                        ? ' · 24h window only'
                                                        : ''}
                                                </p>
                                                {rule.template_body ? (
                                                    <p className="mt-2 rounded-md bg-muted/60 px-3 py-2 text-xs whitespace-pre-wrap text-muted-foreground">
                                                        {rule.template_body}
                                                    </p>
                                                ) : null}
                                            </div>
                                            {can_manage ? (
                                                <Form
                                                    action={`/ai/auto-replies/${rule.id}`}
                                                    method="delete"
                                                    onBefore={() =>
                                                        window.confirm(
                                                            `Remove the rule “${rule.name}”?`,
                                                        )
                                                    }
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            type="submit"
                                                            variant="ghost"
                                                            size="sm"
                                                            disabled={
                                                                processing
                                                            }
                                                            className="text-destructive hover:text-destructive"
                                                        >
                                                            <Trash2 className="size-4" />
                                                            Remove
                                                        </Button>
                                                    )}
                                                </Form>
                                            ) : null}
                                        </li>
                                    ))}
                            </ul>
                        )}
                    </Panel>
                ) : null}

                {/* WhatsApp sync -------------------------------------------- */}
                {tab === 'sync' ? (
                    <Panel
                        id="sync"
                        title="WhatsApp / Meta knowledge pull"
                        hint="Syncs your Meta WhatsApp Business profile fields and mines frequent inbound questions your agent inbox already collected into notes the assistant can reuse."
                        action={
                            can_manage ? (
                                <Form action="/ai/whatsapp-sync" method="post">
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            <RefreshCw
                                                className={`size-4 ${processing ? 'animate-spin' : ''}`}
                                            />
                                            {processing
                                                ? 'Pulling…'
                                                : 'Pull knowledge'}
                                        </Button>
                                    )}
                                </Form>
                            ) : undefined
                        }
                    >
                        {whatsapp_syncs.length === 0 ? (
                            <Empty
                                icon={MessageCircle}
                                title="No sync runs yet"
                                body="Run your first pull to import knowledge from WhatsApp."
                            />
                        ) : (
                            <ul className={`${card} divide-y divide-border`}>
                                {whatsapp_syncs.map((run) => {
                                    const failed = Boolean(run.error);

                                    return (
                                        <li key={run.id} className="p-5">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Badge
                                                    variant={
                                                        failed
                                                            ? 'destructive'
                                                            : 'outline'
                                                    }
                                                    className="capitalize"
                                                >
                                                    {run.status}
                                                </Badge>
                                                <span className="text-sm font-medium tabular-nums">
                                                    {run.articles_created}{' '}
                                                    notes ·{' '}
                                                    {run.messages_scanned}{' '}
                                                    messages
                                                </span>
                                                {run.completed_at ? (
                                                    <span className="text-xs text-muted-foreground sm:ml-auto">
                                                        {formatWhen(
                                                            run.completed_at,
                                                        )}
                                                    </span>
                                                ) : null}
                                            </div>
                                            <p
                                                className={`mt-1.5 text-sm ${failed ? 'text-destructive' : 'text-muted-foreground'}`}
                                            >
                                                {run.summary ?? run.error ?? '—'}
                                            </p>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </Panel>
                ) : null}

                {/* Configuration -------------------------------------------- */}
                {tab === 'config' ? (
                    <Panel
                        id="config"
                        title="Configuration"
                        hint={
                            configured
                                ? 'All variables are set. A read still waits for you to apply fields or send a reply.'
                                : 'Missing variables mean a read is stored as unavailable. The desk does not invent a summary or a score.'
                        }
                    >
                        <ul className={`${card} divide-y divide-border`}>
                            {variables.map((variable) => (
                                <li
                                    key={variable.key}
                                    className="flex items-center justify-between gap-3 px-5 py-3.5"
                                >
                                    <span className="flex items-center gap-2.5">
                                        {variable.configured ? (
                                            <CheckCircle2 className="size-4 text-success" />
                                        ) : (
                                            <CircleAlert className="size-4 text-destructive" />
                                        )}
                                        <span className="font-mono text-sm">
                                            {variable.key}
                                        </span>
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
                        <p className="text-xs text-muted-foreground">
                            LLM_BASE_URL is the OpenAI-compatible root, for
                            example the provider&apos;s /v1 path. The desk posts
                            to /chat/completions on that root.
                        </p>
                    </Panel>
                ) : null}
            </Page>
        </>
    );
}

/* ------------------------------------------------------------------ */

function Panel({
    id,
    title,
    hint,
    action,
    children,
}: {
    id: TabKey;
    title: string;
    hint?: string;
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section
            role="tabpanel"
            id={`panel-${id}`}
            aria-labelledby={`tab-${id}`}
            className="flex flex-col gap-4"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 className="text-lg font-semibold tracking-tight">
                        {title}
                    </h2>
                    {hint ? (
                        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                            {hint}
                        </p>
                    ) : null}
                </div>
                {action}
            </div>
            {children}
        </section>
    );
}

function StatusBadge({ active }: { active: boolean }) {
    return active ? (
        <Badge className="bg-success/15 text-success hover:bg-success/15">
            Active
        </Badge>
    ) : (
        <Badge variant="outline">Off</Badge>
    );
}

function Empty({
    icon: Icon,
    title,
    body,
}: {
    icon: ComponentType<{ className?: string }>;
    title: string;
    body: string;
}) {
    return (
        <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed border-border px-5 py-10 text-center">
            <span className="rounded-full bg-muted p-3 text-muted-foreground">
                <Icon className="size-5" />
            </span>
            <p className="font-semibold">{title}</p>
            <p className="max-w-sm text-sm text-muted-foreground">{body}</p>
        </div>
    );
}