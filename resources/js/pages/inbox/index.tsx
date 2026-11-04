import { Form, Head, Link, router, useForm, usePoll } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CalendarPlus,
    Check,
    CheckCheck,
    Clock,
    ExternalLink,
    Loader2,
    MessageSquarePlus,
    NotebookPen,
    Paperclip,
    Search,
    Send,
    Sparkles,
    X,
} from 'lucide-react';
import { Fragment, useEffect, useMemo, useRef, useState } from 'react';
import type { FormEvent, KeyboardEvent } from 'react';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import type { InboxRow, InboxThread, ThreadMessage } from '@/types/crm';

type Filters = { q: string; filter: string };
type ContactOption = {
    id: number;
    full_name: string;
    business_name: string | null;
    primary_phone: string;
};

const FILTERS = [
    { value: 'all', label: 'All' },
    { value: 'unread', label: 'Unread' },
    { value: 'open', label: 'Open leads' },
    { value: 'attention', label: 'Attention' },
];

function queryString(filters: Filters): string {
    const params = new URLSearchParams();

    if (filters.q) {
        params.set('q', filters.q);
    }

    if (filters.filter && filters.filter !== 'all') {
        params.set('filter', filters.filter);
    }

    const value = params.toString();

    return value ? `?${value}` : '';
}

export default function Inbox({
    conversations,
    thread,
    filters,
    contacts,
    products,
}: {
    conversations: InboxRow[];
    thread: InboxThread | null;
    filters: Filters;
    contacts?: ContactOption[];
    products: Array<{ id: number; name: string }>;
}) {
    usePoll(5000, { only: ['conversations', 'thread', 'inboxUnread'] });

    return (
        <>
            <Head
                title={thread ? `${thread.contact.full_name} · Inbox` : 'Inbox'}
            />
            <div className="flex h-[calc(100svh-4rem)] min-h-0 overflow-hidden md:h-[calc(100svh-5rem)]">
                <aside
                    className={cn(
                        'flex w-full min-w-0 flex-col border-r md:w-72 md:shrink-0 2xl:w-96',
                        thread ? 'hidden md:flex' : 'flex',
                    )}
                >
                    <ConversationList
                        conversations={conversations}
                        activeId={thread?.id ?? null}
                        filters={filters}
                        contacts={contacts}
                        products={products}
                    />
                </aside>
                <section
                    className={cn(
                        'min-w-0 flex-1 flex-col',
                        thread ? 'flex' : 'hidden md:flex',
                    )}
                >
                    {thread ? (
                        <ThreadView
                            key={thread.id}
                            thread={thread}
                            filters={filters}
                        />
                    ) : (
                        <EmptyThread />
                    )}
                </section>
                {thread ? (
                    <aside className="hidden w-72 shrink-0 overflow-y-auto border-l xl:block">
                        <ThreadDetails thread={thread} />
                    </aside>
                ) : null}
            </div>
        </>
    );
}

function ConversationList({
    conversations,
    activeId,
    filters,
    contacts,
    products,
}: {
    conversations: InboxRow[];
    activeId: number | null;
    filters: Filters;
    contacts?: ContactOption[];
    products: Array<{ id: number; name: string }>;
}) {
    const [search, setSearch] = useState(filters.q);
    const base = activeId ? `/inbox/${activeId}` : '/inbox';

    useEffect(() => {
        if (search === filters.q) {
            return;
        }

        const timer = setTimeout(() => {
            router.get(
                base,
                {
                    q: search || undefined,
                    filter:
                        filters.filter !== 'all' ? filters.filter : undefined,
                },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: ['conversations', 'filters'],
                },
            );
        }, 300);

        return () => clearTimeout(timer);
    }, [search]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
        <>
            <div className="flex flex-col gap-3 border-b p-3">
                <div className="flex items-center justify-between gap-2">
                    <h1 className="text-lg font-semibold tracking-tight">
                        Inbox
                    </h1>
                    <NewChatDialog contacts={contacts} products={products} />
                </div>
                <div className="relative">
                    <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search name, business or number"
                        className="pl-8"
                        aria-label="Search conversations"
                    />
                </div>
                <div className="flex gap-1 overflow-x-auto">
                    {FILTERS.map((option) => (
                        <Link
                            key={option.value}
                            href={`${base}${queryString({ q: filters.q, filter: option.value })}`}
                            preserveState
                            preserveScroll
                            only={['conversations', 'filters']}
                            className={cn(
                                'rounded-full px-3 py-1 text-xs font-medium whitespace-nowrap transition-colors',
                                filters.filter === option.value
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {option.label}
                        </Link>
                    ))}
                </div>
            </div>
            <div className="min-h-0 flex-1 overflow-y-auto">
                {conversations.length === 0 ? (
                    <div className="p-6 text-center text-sm text-muted-foreground">
                        {filters.q || filters.filter !== 'all'
                            ? 'No chats match this view.'
                            : 'No chats yet. Inbound WhatsApp messages appear here, or start one with New chat.'}
                    </div>
                ) : (
                    <ul>
                        {conversations.map((row) => (
                            <ConversationRow
                                key={row.id}
                                row={row}
                                active={row.id === activeId}
                                href={`/inbox/${row.id}${queryString(filters)}`}
                            />
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

function ConversationRow({
    row,
    active,
    href,
}: {
    row: InboxRow;
    active: boolean;
    href: string;
}) {
    const getInitials = useInitials();
    const unread = row.unread_count > 0;
    const preview = row.last_message
        ? `${row.last_message.direction === 'outbound' ? 'You: ' : ''}${row.last_message.body}`
        : 'No messages yet';

    return (
        <li>
            <Link
                href={href}
                preserveScroll
                className={cn(
                    'flex items-start gap-3 border-b px-3 py-3 transition-colors hover:bg-muted/60',
                    active && 'bg-muted',
                )}
            >
                <Avatar className="size-10 shrink-0">
                    <AvatarFallback className="bg-emerald-100 text-sm font-medium text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200">
                        {getInitials(row.contact_name)}
                    </AvatarFallback>
                </Avatar>
                <div className="min-w-0 flex-1">
                    <div className="flex items-baseline justify-between gap-2">
                        <span
                            className={cn(
                                'truncate text-sm',
                                unread ? 'font-semibold' : 'font-medium',
                            )}
                        >
                            {row.contact_name}
                        </span>
                        <span
                            className={cn(
                                'shrink-0 text-[11px] tabular-nums',
                                unread
                                    ? 'font-medium text-emerald-600 dark:text-emerald-400'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {row.last_message_label}
                        </span>
                    </div>
                    <div className="flex items-center justify-between gap-2">
                        <p
                            className={cn(
                                'line-clamp-1 text-xs',
                                unread
                                    ? 'text-foreground'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {row.last_message?.direction === 'outbound' ? (
                                <StatusIcon
                                    status={row.last_message.status}
                                    className="mr-1 inline size-3.5 align-[-2px]"
                                />
                            ) : null}
                            {preview}
                        </p>
                        {unread ? (
                            <span className="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-emerald-600 px-1.5 text-[11px] font-semibold text-white">
                                {row.unread_count}
                            </span>
                        ) : null}
                    </div>
                    <div className="mt-1 flex flex-wrap items-center gap-1 text-[11px] text-muted-foreground">
                        <span className="truncate">
                            {row.business_name ?? row.phone} · {row.stage}
                        </span>
                        {row.needs_attention ? (
                            <AlertTriangle
                                className="size-3 text-amber-500"
                                aria-label="Needs attention"
                            />
                        ) : null}
                        {row.opted_out ? (
                            <span className="text-destructive">Opted out</span>
                        ) : null}
                    </div>
                </div>
            </Link>
        </li>
    );
}

function EmptyThread() {
    return (
        <div className="flex flex-1 flex-col items-center justify-center gap-3 bg-muted/30 p-8 text-center">
            <div className="flex size-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                <MessageSquarePlus className="size-6" />
            </div>
            <div>
                <p className="font-medium">Pick a chat</p>
                <p className="max-w-sm text-sm text-muted-foreground">
                    Every WhatsApp message and every message you log by hand is
                    kept here with the full history. The list refreshes on its
                    own every few seconds.
                </p>
            </div>
        </div>
    );
}

function ThreadView({
    thread,
    filters,
}: {
    thread: InboxThread;
    filters: Filters;
}) {
    const getInitials = useInitials();
    const scroller = useRef<HTMLDivElement>(null);
    const lastId = thread.messages.at(-1)?.id;

    useEffect(() => {
        const element = scroller.current;

        if (element) {
            element.scrollTop = element.scrollHeight;
        }
    }, [lastId]);

    return (
        <>
            <header className="flex items-center gap-3 border-b px-3 py-2">
                <Button
                    variant="ghost"
                    size="icon"
                    className="md:hidden"
                    asChild
                >
                    <Link
                        href={`/inbox${queryString(filters)}`}
                        aria-label="Back to chats"
                    >
                        <ArrowLeft />
                    </Link>
                </Button>
                <Avatar className="size-9">
                    <AvatarFallback className="bg-emerald-100 text-sm font-medium text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200">
                        {getInitials(thread.contact.full_name)}
                    </AvatarFallback>
                </Avatar>
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-semibold">
                        {thread.contact.full_name}
                    </p>
                    <p className="truncate text-xs text-muted-foreground">
                        {thread.contact.primary_phone}
                        {' · '}
                        {thread.lead.product} · {thread.lead.stage}
                    </p>
                </div>
                <WindowBadge thread={thread} />
                <BookDialog
                    leadId={thread.lead.id}
                    title={`Call with ${thread.contact.full_name}`}
                    trigger={
                        <Button
                            variant="outline"
                            size="sm"
                            className="hidden sm:inline-flex"
                        >
                            <CalendarPlus /> Book
                        </Button>
                    }
                />
                <Button variant="outline" size="sm" asChild>
                    <Link href={`/leads/${thread.lead.id}`}>
                        <ExternalLink />{' '}
                        <span className="hidden sm:inline">Lead</span>
                    </Link>
                </Button>
            </header>

            <div
                ref={scroller}
                className="min-h-0 flex-1 overflow-y-auto bg-[radial-gradient(circle_at_1px_1px,var(--color-border)_1px,transparent_0)] [background-size:22px_22px] px-3 py-4 sm:px-6"
            >
                {thread.hidden_earlier > 0 ? (
                    <p className="mb-4 text-center text-xs text-muted-foreground">
                        {thread.hidden_earlier} earlier messages are on the{' '}
                        <Link
                            href={`/leads/${thread.lead.id}`}
                            className="underline"
                        >
                            lead page
                        </Link>
                        .
                    </p>
                ) : null}
                {thread.messages.length === 0 ? (
                    <div className="mx-auto mt-10 max-w-sm rounded-lg bg-background/90 p-4 text-center text-sm text-muted-foreground shadow-sm">
                        No messages yet. On FlareSend you can write first. With
                        the Meta or Twilio drivers, WhatsApp only allows
                        free-form text within 24 hours of the customer&apos;s
                        last message, so a brand-new chat usually starts with
                        the customer writing first, or with a message you log by
                        hand.
                    </div>
                ) : null}
                <MessageList messages={thread.messages} />
            </div>

            <ScheduledStrip thread={thread} />
            <Composer thread={thread} />
        </>
    );
}

function MessageList({ messages }: { messages: ThreadMessage[] }) {
    return (
        <div className="mx-auto flex max-w-3xl flex-col gap-1.5">
            {messages.map((message, index) => {
                const previous = messages[index - 1];
                const newDay =
                    !previous || previous.day_label !== message.day_label;

                return (
                    <Fragment key={message.id}>
                        {newDay ? (
                            <div className="sticky top-0 z-10 my-2 flex justify-center">
                                <span className="rounded-md bg-background/95 px-2.5 py-1 text-[11px] font-medium text-muted-foreground shadow-sm">
                                    {message.day_label}
                                </span>
                            </div>
                        ) : null}
                        <Bubble message={message} />
                    </Fragment>
                );
            })}
        </div>
    );
}

function Bubble({ message }: { message: ThreadMessage }) {
    const outbound = message.direction === 'outbound';
    const refused = message.status === 'refused' || message.status === 'failed';
    const logged = message.channel === 'desk';

    return (
        <div className={cn('flex', outbound ? 'justify-end' : 'justify-start')}>
            <div
                className={cn(
                    'relative max-w-[85%] rounded-lg px-3 py-1.5 text-sm shadow-sm sm:max-w-[70%]',
                    outbound
                        ? 'rounded-tr-none bg-emerald-100 text-emerald-950 dark:bg-emerald-900/60 dark:text-emerald-50'
                        : 'rounded-tl-none bg-background',
                    refused &&
                        'bg-red-50 ring-1 ring-red-200 dark:bg-red-950/40 dark:ring-red-900',
                )}
            >
                {logged ? (
                    <p className="mb-0.5 flex items-center gap-1 text-[10px] font-medium tracking-wide text-muted-foreground uppercase">
                        <NotebookPen className="size-3" />
                        Logged
                        {message.logged_by ? ` by ${message.logged_by}` : ''}
                    </p>
                ) : null}
                {message.origin === 'automatic' ? (
                    <p className="mb-0.5 text-[10px] font-medium tracking-wide text-muted-foreground uppercase">
                        Automatic follow-up
                    </p>
                ) : null}
                {message.attachments.map((attachment) => (
                    <p
                        key={attachment.id}
                        className="mb-1 flex items-center gap-1 text-xs text-muted-foreground"
                    >
                        <Paperclip className="size-3" />
                        {attachment.mime_type ?? 'Attachment'}
                    </p>
                ))}
                <p className="break-words whitespace-pre-wrap">
                    {message.body}
                </p>
                <div className="mt-0.5 flex items-center justify-end gap-1 text-[10px] text-muted-foreground">
                    <span className="tabular-nums">{message.time_label}</span>
                    {outbound && !logged ? (
                        <StatusIcon
                            status={message.status}
                            className="size-3.5"
                        />
                    ) : null}
                </div>
                {refused && message.refusal_reason ? (
                    <p className="mt-1 border-t border-red-200 pt-1 text-xs text-red-700 dark:border-red-900 dark:text-red-300">
                        Not sent: {message.refusal_reason}
                    </p>
                ) : null}
                {message.status === 'failed' && !message.refusal_reason ? (
                    <p className="mt-1 text-xs text-red-700 dark:text-red-300">
                        WhatsApp rejected this message.
                    </p>
                ) : null}
            </div>
        </div>
    );
}

function StatusIcon({
    status,
    className,
}: {
    status: string;
    className?: string;
}) {
    switch (status) {
        case 'queued':
            return (
                <Clock
                    className={cn('text-muted-foreground', className)}
                    aria-label="Queued"
                />
            );
        case 'sent':
            return (
                <Check
                    className={cn('text-muted-foreground', className)}
                    aria-label="Sent"
                />
            );
        case 'delivered':
            return (
                <CheckCheck
                    className={cn('text-muted-foreground', className)}
                    aria-label="Delivered"
                />
            );
        case 'read':
            return (
                <CheckCheck
                    className={cn('text-sky-500', className)}
                    aria-label="Read"
                />
            );
        case 'failed':
        case 'refused':
        case 'unknown':
            return (
                <AlertTriangle
                    className={cn('text-red-500', className)}
                    aria-label={status}
                />
            );
        default:
            return null;
    }
}

function WindowBadge({ thread }: { thread: InboxThread }) {
    if (thread.whatsapp.opted_out) {
        return <Badge variant="destructive">Opted out</Badge>;
    }

    if (thread.whatsapp.conversations_stopped) {
        return (
            <Badge variant="outline" className="hidden 2xl:inline-flex">
                Conversations stopped
            </Badge>
        );
    }

    if (!thread.whatsapp.enforces_service_window) {
        return null;
    }

    if (thread.whatsapp.window_open) {
        return (
            <Badge
                variant="outline"
                className="hidden border-emerald-300 text-emerald-700 2xl:inline-flex dark:border-emerald-800 dark:text-emerald-300"
                title={
                    thread.whatsapp.window_closes_label
                        ? `Closes ${thread.whatsapp.window_closes_label}`
                        : undefined
                }
            >
                Reply window open
            </Badge>
        );
    }

    return (
        <Badge variant="outline" className="hidden 2xl:inline-flex">
            Window closed
        </Badge>
    );
}

function ScheduledStrip({ thread }: { thread: InboxThread }) {
    if (thread.scheduled.length === 0) {
        return null;
    }

    return (
        <div className="border-t bg-amber-50/70 px-3 py-2 text-xs dark:bg-amber-950/20">
            {thread.scheduled.map((task) => (
                <div key={task.id} className="flex items-center gap-2 py-0.5">
                    <Clock className="size-3.5 shrink-0 text-amber-600" />
                    <span className="shrink-0 font-medium">
                        {task.due_label}
                    </span>
                    <span className="line-clamp-1 flex-1 text-muted-foreground">
                        {task.body}
                    </span>
                    {task.held_reason ? (
                        <span className="text-amber-700">Held</span>
                    ) : null}
                    <Form
                        action={`/follow-ups/tasks/${task.id}/cancel`}
                        method="post"
                        options={{ preserveScroll: true }}
                    >
                        <Button
                            type="submit"
                            variant="ghost"
                            size="icon"
                            className="size-6"
                            aria-label="Cancel scheduled message"
                        >
                            <X className="size-3.5" />
                        </Button>
                    </Form>
                </div>
            ))}
        </div>
    );
}

type Mode = 'whatsapp' | 'log_outbound' | 'log_inbound';

function newKey(): string {
    return typeof crypto !== 'undefined' && 'randomUUID' in crypto
        ? crypto.randomUUID()
        : `${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function Composer({ thread }: { thread: InboxThread }) {
    const canWhatsApp =
        thread.whatsapp.configured &&
        thread.whatsapp.can_send &&
        !thread.whatsapp.opted_out &&
        !thread.whatsapp.conversations_stopped;
    const [mode, setMode] = useState<Mode>(
        canWhatsApp ? 'whatsapp' : 'log_outbound',
    );
    const [scheduling, setScheduling] = useState(false);
    const form = useForm({
        mode: mode as string,
        body: '',
        idempotency_key: newKey(),
        sent_at: '',
    });
    const draft = thread.intelligence.latest?.draft ?? null;
    const reason = useMemo(() => {
        if (thread.whatsapp.opted_out) {
            return 'This contact opted out. You can still log messages by hand.';
        }

        if (!thread.whatsapp.configured) {
            return 'WhatsApp is not configured yet, so messages can only be logged by hand.';
        }

        if (thread.whatsapp.conversations_stopped) {
            return 'Conversations are stopped on this lead. Resume them on the lead page to send on WhatsApp.';
        }

        if (!thread.whatsapp.can_send) {
            return 'The 24-hour reply window is closed. WhatsApp only allows free-form text after the customer writes again.';
        }

        if (
            !thread.whatsapp.can_send_free_form &&
            thread.whatsapp.template_ready
        ) {
            return `The reply window is closed, so this is sent with the ${thread.whatsapp.template_name} template.`;
        }

        return null;
    }, [thread.whatsapp]);

    useEffect(() => {
        form.setData('mode', mode);
    }, [mode]); // eslint-disable-line react-hooks/exhaustive-deps

    const submit = (event?: FormEvent) => {
        event?.preventDefault();

        if (form.processing || form.data.body.trim() === '') {
            return;
        }

        form.post(`/inbox/${thread.id}/messages`, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('body', 'sent_at');
                form.setData('idempotency_key', newKey());
            },
        });
    };

    const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (
            event.key === 'Enter' &&
            !event.shiftKey &&
            !event.nativeEvent.isComposing
        ) {
            event.preventDefault();
            submit();
        }
    };

    const modes: Array<{ value: Mode; label: string; disabled?: boolean }> = [
        { value: 'whatsapp', label: 'WhatsApp', disabled: !canWhatsApp },
        { value: 'log_outbound', label: 'Log sent' },
        { value: 'log_inbound', label: 'Log received' },
    ];

    return (
        <div className="border-t bg-background p-3">
            {reason ? (
                <p className="mb-2 flex items-start gap-1.5 text-xs text-muted-foreground">
                    <AlertTriangle className="mt-0.5 size-3.5 shrink-0 text-amber-500" />
                    {reason}
                </p>
            ) : null}

            {draft && draft.status !== 'sent' ? (
                <div className="mb-2 flex items-start gap-2 rounded-md border border-violet-200 bg-violet-50 p-2 text-xs dark:border-violet-900 dark:bg-violet-950/30">
                    <Sparkles className="mt-0.5 size-3.5 shrink-0 text-violet-600" />
                    <p className="line-clamp-2 flex-1">{draft.body}</p>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="h-7"
                        onClick={() => form.setData('body', draft.body)}
                    >
                        Use
                    </Button>
                </div>
            ) : null}

            <form onSubmit={submit} className="flex flex-col gap-2">
                <div className="flex flex-wrap items-center gap-1">
                    {modes.map((option) => (
                        <button
                            key={option.value}
                            type="button"
                            disabled={option.disabled}
                            onClick={() => setMode(option.value)}
                            className={cn(
                                'rounded-full px-3 py-1 text-xs font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-40',
                                mode === option.value
                                    ? option.value === 'whatsapp'
                                        ? 'bg-emerald-600 text-white'
                                        : 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {option.label}
                        </button>
                    ))}
                    <div className="ml-auto flex items-center gap-1">
                        <Form
                            action={`/leads/${thread.lead.id}/analyses`}
                            method="post"
                            options={{ preserveScroll: true }}
                        >
                            {({ processing }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="return_to"
                                        value="inbox"
                                    />
                                    <Button
                                        type="submit"
                                        variant="ghost"
                                        size="sm"
                                        className="h-7 text-xs"
                                        disabled={
                                            processing ||
                                            !thread.intelligence.configured
                                        }
                                        title={
                                            thread.intelligence.configured
                                                ? 'Ask the model to read this chat and draft a reply'
                                                : 'The language model is not configured'
                                        }
                                    >
                                        {processing ? (
                                            <Loader2 className="animate-spin" />
                                        ) : (
                                            <Sparkles />
                                        )}
                                        Suggest reply
                                    </Button>
                                </>
                            )}
                        </Form>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-7 text-xs"
                            disabled={thread.lead.outcome !== 'open'}
                            onClick={() => setScheduling(true)}
                        >
                            <Clock /> Schedule
                        </Button>
                    </div>
                </div>

                {mode !== 'whatsapp' ? (
                    <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                        <Label
                            htmlFor="sent_at"
                            className="text-xs font-normal"
                        >
                            When it happened
                        </Label>
                        <Input
                            id="sent_at"
                            type="datetime-local"
                            className="h-7 w-auto text-xs"
                            value={form.data.sent_at}
                            onChange={(event) =>
                                form.setData('sent_at', event.target.value)
                            }
                        />
                        <span className="hidden 2xl:inline">
                            Blank means now. Nothing is sent.
                        </span>
                    </div>
                ) : null}

                <div className="flex items-end gap-2">
                    <Textarea
                        value={form.data.body}
                        onChange={(event) =>
                            form.setData('body', event.target.value)
                        }
                        onKeyDown={onKeyDown}
                        rows={2}
                        placeholder={
                            mode === 'whatsapp'
                                ? 'Type a WhatsApp message. Enter sends, Shift+Enter adds a line.'
                                : mode === 'log_inbound'
                                  ? 'What did the customer say? (call, SMS, in person…)'
                                  : 'What did you tell the customer?'
                        }
                        className="max-h-48 min-h-11 resize-none"
                        aria-label="Message"
                    />
                    <Button
                        type="submit"
                        size="icon"
                        className={cn(
                            'size-11 shrink-0',
                            mode === 'whatsapp' &&
                                'bg-emerald-600 hover:bg-emerald-700',
                        )}
                        disabled={
                            form.processing || form.data.body.trim() === ''
                        }
                        aria-label={
                            mode === 'whatsapp'
                                ? 'Send on WhatsApp'
                                : 'Add to history'
                        }
                    >
                        {form.processing ? (
                            <Loader2 className="animate-spin" />
                        ) : mode === 'whatsapp' ? (
                            <Send />
                        ) : (
                            <NotebookPen />
                        )}
                    </Button>
                </div>
                <InputError message={form.errors.body} />
            </form>

            <ScheduleDialog
                open={scheduling}
                onOpenChange={setScheduling}
                conversationId={thread.id}
                body={form.data.body}
                onScheduled={() => form.reset('body')}
            />
        </div>
    );
}

function ScheduleDialog({
    open,
    onOpenChange,
    conversationId,
    body,
    onScheduled,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    conversationId: number;
    body: string;
    onScheduled: () => void;
}) {
    const tomorrow = new Date(Date.now() + 24 * 3600 * 1000);
    const pad = (value: number) => String(value).padStart(2, '0');
    const defaultAt = `${tomorrow.getFullYear()}-${pad(tomorrow.getMonth() + 1)}-${pad(tomorrow.getDate())}T09:00`;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Schedule a WhatsApp message</DialogTitle>
                    <DialogDescription>
                        It is sent by the follow-up runner at that time, with
                        the same opt-out and 24-hour checks as a send you make
                        by hand. If the window has closed by then, it is refused
                        and noted on the lead.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/inbox/${conversationId}/schedule`}
                    method="post"
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        onScheduled();
                        onOpenChange(false);
                    }}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="schedule_body">Message</Label>
                                <Textarea
                                    id="schedule_body"
                                    name="body"
                                    required
                                    rows={4}
                                    defaultValue={body}
                                />
                                <InputError message={errors.body} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="send_at">
                                    Send at (desk time)
                                </Label>
                                <Input
                                    id="send_at"
                                    name="send_at"
                                    type="datetime-local"
                                    required
                                    defaultValue={defaultAt}
                                />
                                <InputError message={errors.send_at} />
                            </div>
                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    <Clock /> Schedule
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function NewChatDialog({
    contacts,
    products,
}: {
    contacts?: ContactOption[];
    products: Array<{ id: number; name: string }>;
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [contactId, setContactId] = useState<number | null>(null);

    useEffect(() => {
        if (open && contacts === undefined) {
            router.reload({ only: ['contacts'] });
        }
    }, [open, contacts]);

    const matches = useMemo(() => {
        const needle = query.trim().toLowerCase();

        if (!contacts || needle === '') {
            return [];
        }

        const digits = needle.replace(/\D+/g, '');

        return contacts
            .filter(
                (contact) =>
                    contact.full_name.toLowerCase().includes(needle) ||
                    (contact.business_name ?? '')
                        .toLowerCase()
                        .includes(needle) ||
                    (digits.length >= 3 &&
                        contact.primary_phone
                            .replace(/\D+/g, '')
                            .includes(digits.replace(/^0/, ''))),
            )
            .slice(0, 6);
    }, [contacts, query]);

    const selected =
        contacts?.find((contact) => contact.id === contactId) ?? null;

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    setQuery('');
                    setContactId(null);
                }
            }}
        >
            <DialogTrigger asChild>
                <Button
                    size="sm"
                    className="bg-emerald-600 hover:bg-emerald-700"
                >
                    <MessageSquarePlus /> New chat
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Start a conversation</DialogTitle>
                    <DialogDescription>
                        Pick a contact or enter a new name and number. The chat
                        opens on their open lead, or a new lead on the default
                        pipeline.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action="/inbox"
                    method="post"
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            {selected ? (
                                <div className="flex items-center justify-between rounded-md border p-3 text-sm">
                                    <div>
                                        <p className="font-medium">
                                            {selected.full_name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {selected.primary_phone}
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setContactId(null)}
                                    >
                                        Change
                                    </Button>
                                    <input
                                        type="hidden"
                                        name="contact_id"
                                        value={selected.id}
                                    />
                                </div>
                            ) : (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="find_contact">
                                            Existing contact
                                        </Label>
                                        <Input
                                            id="find_contact"
                                            value={query}
                                            onChange={(event) =>
                                                setQuery(event.target.value)
                                            }
                                            placeholder={
                                                contacts
                                                    ? 'Search by name or number'
                                                    : 'Loading contacts…'
                                            }
                                            autoComplete="off"
                                        />
                                        {matches.length > 0 ? (
                                            <ul className="divide-y rounded-md border text-sm">
                                                {matches.map((contact) => (
                                                    <li key={contact.id}>
                                                        <button
                                                            type="button"
                                                            className="flex w-full items-center justify-between px-3 py-2 text-left hover:bg-muted"
                                                            onClick={() =>
                                                                setContactId(
                                                                    contact.id,
                                                                )
                                                            }
                                                        >
                                                            <span>
                                                                {
                                                                    contact.full_name
                                                                }
                                                                {contact.business_name ? (
                                                                    <span className="text-muted-foreground">
                                                                        {' '}
                                                                        ·{' '}
                                                                        {
                                                                            contact.business_name
                                                                        }
                                                                    </span>
                                                                ) : null}
                                                            </span>
                                                            <span className="text-xs text-muted-foreground">
                                                                {
                                                                    contact.primary_phone
                                                                }
                                                            </span>
                                                        </button>
                                                    </li>
                                                ))}
                                            </ul>
                                        ) : null}
                                    </div>
                                    <div className="relative text-center text-xs text-muted-foreground">
                                        <span className="bg-background px-2">
                                            or a new number
                                        </span>
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="full_name">
                                                Name
                                            </Label>
                                            <Input
                                                id="full_name"
                                                name="full_name"
                                                placeholder="Jane Wanjiku"
                                            />
                                            <InputError
                                                message={errors.full_name}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="phone">
                                                WhatsApp number
                                            </Label>
                                            <Input
                                                id="phone"
                                                name="phone"
                                                placeholder="0712 345 678"
                                                inputMode="tel"
                                            />
                                            <InputError
                                                message={errors.phone}
                                            />
                                        </div>
                                    </div>
                                </>
                            )}
                            {products.length > 1 ? (
                                <div className="grid gap-2">
                                    <Label htmlFor="product_id">Product</Label>
                                    <select
                                        id="product_id"
                                        name="product_id"
                                        className="h-9 rounded-md border bg-transparent px-3 text-sm"
                                        defaultValue=""
                                    >
                                        <option value="">
                                            Their open lead, or the default
                                            product
                                        </option>
                                        {products.map((product) => (
                                            <option
                                                key={product.id}
                                                value={product.id}
                                            >
                                                {product.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.product_id} />
                                </div>
                            ) : null}
                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    Open chat
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function ThreadDetails({ thread }: { thread: InboxThread }) {
    const latest = thread.intelligence.latest;

    return (
        <div className="flex flex-col gap-5 p-4 text-sm">
            <section>
                <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    Contact
                </h2>
                <dl className="mt-2 grid gap-1.5">
                    <Detail label="Name" value={thread.contact.full_name} />
                    <Detail
                        label="Phone"
                        value={thread.contact.primary_phone}
                    />
                    <Detail
                        label="Business"
                        value={thread.contact.business_name}
                    />
                    <Detail label="Email" value={thread.contact.email} />
                    <Detail label="Location" value={thread.contact.location} />
                </dl>
                <Link
                    href={`/contacts/${thread.contact.id}`}
                    className="mt-2 inline-block text-xs underline"
                >
                    Open contact
                </Link>
            </section>

            <section>
                <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    Lead
                </h2>
                <div className="mt-2 flex flex-wrap gap-1.5">
                    <Badge>{thread.lead.stage}</Badge>
                    <Badge variant="outline">{thread.lead.product}</Badge>
                    {latest?.status === 'completed' && latest.score !== null ? (
                        <Badge variant="secondary">Score {latest.score}</Badge>
                    ) : null}
                </div>
                {thread.lead.next_action || thread.lead.next_follow_up_label ? (
                    <p className="mt-2 text-xs text-muted-foreground">
                        Next: {thread.lead.next_action ?? 'Follow up'}
                        {thread.lead.next_follow_up_label
                            ? ` · ${thread.lead.next_follow_up_label}`
                            : ''}
                    </p>
                ) : null}
                {latest?.summary ? (
                    <p className="mt-2 rounded-md bg-muted p-2 text-xs leading-relaxed">
                        {latest.summary}
                    </p>
                ) : null}
            </section>

            <section>
                <div className="flex items-center justify-between">
                    <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Appointments
                    </h2>
                    <BookDialog
                        leadId={thread.lead.id}
                        title={`Call with ${thread.contact.full_name}`}
                        trigger={
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-7 text-xs"
                            >
                                <CalendarPlus /> Book
                            </Button>
                        }
                    />
                </div>
                {thread.appointments.length === 0 ? (
                    <p className="mt-1 text-xs text-muted-foreground">
                        Nothing booked.
                    </p>
                ) : (
                    <ul className="mt-2 grid gap-2">
                        {thread.appointments.map((appointment) => (
                            <li
                                key={appointment.id}
                                className="rounded-md border p-2"
                            >
                                <p className="text-xs font-medium">
                                    {appointment.title}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {appointment.kind_label} ·{' '}
                                    {appointment.starts_at_label}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section>
                <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    WhatsApp
                </h2>
                <dl className="mt-2 grid gap-1.5">
                    <Detail
                        label="Last message from them"
                        value={thread.whatsapp.last_inbound_label ?? 'Never'}
                    />
                    <Detail
                        label="Sending via"
                        value={thread.whatsapp.driver || null}
                    />
                    {thread.whatsapp.enforces_service_window ? (
                        <Detail
                            label="Reply window"
                            value={
                                thread.whatsapp.window_open
                                    ? `Open until ${thread.whatsapp.window_closes_label}`
                                    : 'Closed'
                            }
                        />
                    ) : null}
                    {thread.whatsapp.taken_over ? (
                        <Detail
                            label="Takeover"
                            value="On — automation blocked"
                        />
                    ) : null}
                    {thread.whatsapp.automation_paused ? (
                        <Detail
                            label="Automation"
                            value="Paused on this lead"
                        />
                    ) : null}
                </dl>
            </section>

            {thread.other_leads.length > 0 ? (
                <section>
                    <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Other leads
                    </h2>
                    <ul className="mt-2 grid gap-1">
                        {thread.other_leads.map((lead) => (
                            <li key={lead.id}>
                                <Link
                                    href={`/leads/${lead.id}`}
                                    className="text-xs underline"
                                >
                                    {lead.product} · {lead.stage}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            ) : null}
        </div>
    );
}

function Detail({ label, value }: { label: string; value: string | null }) {
    return (
        <div className="grid grid-cols-[7rem_1fr] gap-2 text-xs">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="break-words">{value ?? '—'}</dd>
        </div>
    );
}

const BOOK_TYPES = [
    { value: 'call', label: 'Call' },
    { value: 'visit', label: 'Visit' },
    { value: 'task', label: 'Task' },
    { value: 'delivery', label: 'Delivery' },
    { value: 'other', label: 'Other' },
];

function BookDialog({
    leadId,
    title,
    trigger,
}: {
    leadId: number;
    title: string;
    trigger: React.ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const today = new Date();
    const pad = (value: number) => String(value).padStart(2, '0');
    const day = `${today.getFullYear()}-${pad(today.getMonth() + 1)}-${pad(today.getDate())}`;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Book on the calendar</DialogTitle>
                    <DialogDescription>
                        Saved on your calendar and linked to this lead. It is
                        pushed to Google Calendar when that is switched on. The
                        customer is not messaged.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action="/calendar"
                    method="post"
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <input
                                type="hidden"
                                name="return_to"
                                value="back"
                            />
                            <input
                                type="hidden"
                                name="lead_id"
                                value={leadId}
                            />
                            <input type="hidden" name="color" value="blue" />
                            <div className="grid gap-2">
                                <Label htmlFor="book_title">Title</Label>
                                <Input
                                    id="book_title"
                                    name="title"
                                    required
                                    defaultValue={title}
                                />
                                <InputError message={errors.title} />
                            </div>
                            <div className="grid gap-4 sm:grid-cols-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="book_day">Day</Label>
                                    <Input
                                        id="book_day"
                                        name="activity_on"
                                        type="date"
                                        required
                                        defaultValue={day}
                                    />
                                    <InputError message={errors.activity_on} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="book_time">Time</Label>
                                    <Input
                                        id="book_time"
                                        name="start_time"
                                        type="time"
                                        defaultValue="10:00"
                                    />
                                    <InputError message={errors.start_time} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="book_length">Minutes</Label>
                                    <Input
                                        id="book_length"
                                        name="duration_minutes"
                                        type="number"
                                        min={5}
                                        step={5}
                                        defaultValue={30}
                                    />
                                    <InputError
                                        message={errors.duration_minutes}
                                    />
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="book_type">Type</Label>
                                <select
                                    id="book_type"
                                    name="activity_type"
                                    defaultValue="call"
                                    className="h-9 rounded-md border bg-transparent px-3 text-sm"
                                >
                                    {BOOK_TYPES.map((type) => (
                                        <option
                                            key={type.value}
                                            value={type.value}
                                        >
                                            {type.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="book_location">Where</Label>
                                <Input
                                    id="book_location"
                                    name="location"
                                    placeholder="Address, phone number or meeting link"
                                />
                                <InputError message={errors.location} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="book_notes">Notes</Label>
                                <Textarea
                                    id="book_notes"
                                    name="notes"
                                    rows={3}
                                />
                            </div>
                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    <CalendarPlus /> Book
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
