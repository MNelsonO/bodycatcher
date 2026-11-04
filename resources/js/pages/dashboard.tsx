import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlarmClock,
    ArrowRight,
    CalendarDays,
    CalendarPlus,
    CircleAlert,
    Inbox,
    MessageCircle,
    Plus,
    Trophy,
    UserPlus,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { AppointmentDialog } from '@/components/crm/appointment-dialog';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import type { DashboardMetrics } from '@/types/crm';

function greeting(): string {
    const hour = Number(
        new Date().toLocaleString('en-GB', {
            hour: 'numeric',
            hour12: false,
            timeZone: 'Africa/Nairobi',
        }),
    );

    if (hour < 12) {
        return 'Good morning';
    }

    if (hour < 17) {
        return 'Good afternoon';
    }

    return 'Good evening';
}

export default function Dashboard({ metrics }: { metrics: DashboardMetrics }) {
    const { auth } = usePage().props;
    const getInitials = useInitials();
    const firstName = auth.user.name.split(' ')[0];
    const closed = metrics.won_count + metrics.lost_count;
    const winRate =
        closed > 0 ? Math.round((metrics.won_count / closed) * 100) : null;
    const maxStage = Math.max(
        1,
        ...metrics.leads_by_stage.map((row) => row.total),
    );

    return (
        <>
            <Head title="Command centre" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            {metrics.today_label}
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {greeting()}, {firstName}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {summaryLine(metrics)}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            asChild
                            className="bg-emerald-600 hover:bg-emerald-700"
                        >
                            <Link href="/inbox">
                                <Inbox /> Open inbox
                                {metrics.unread_conversations > 0 ? (
                                    <span className="ml-1 rounded-full bg-white/20 px-1.5 text-xs">
                                        {metrics.unread_conversations}
                                    </span>
                                ) : null}
                            </Link>
                        </Button>
                        <AppointmentDialog
                            trigger={
                                <Button variant="outline">
                                    <CalendarPlus /> Book
                                </Button>
                            }
                        />
                        <Button variant="outline" asChild>
                            <Link href="/leads/create">
                                <Plus /> New lead
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        icon={MessageCircle}
                        tone="emerald"
                        label="Unread chats"
                        value={metrics.unread_conversations}
                        hint={`${metrics.messages_today} messages in today`}
                        href="/inbox?filter=unread"
                    />
                    <StatCard
                        icon={CalendarDays}
                        tone="sky"
                        label="Appointments today"
                        value={metrics.appointments_today}
                        hint={`${metrics.upcoming_appointments.length} coming up`}
                        href="/calendar"
                    />
                    <StatCard
                        icon={AlarmClock}
                        tone={metrics.overdue_follow_ups > 0 ? 'rose' : 'amber'}
                        label="Follow-ups due"
                        value={
                            metrics.follow_ups_due_today +
                            metrics.overdue_follow_ups
                        }
                        hint={`${metrics.follow_ups_due_today} today · ${metrics.overdue_follow_ups} overdue`}
                        href="/leads?follow_up=overdue"
                    />
                    <StatCard
                        icon={UserPlus}
                        tone="violet"
                        label="New leads today"
                        value={metrics.new_leads_today}
                        hint={`${metrics.open_leads} open of ${metrics.total_leads}`}
                        href="/leads"
                    />
                </div>

                <div className="grid gap-6 xl:grid-cols-3">
                    <Panel
                        title="Latest chats"
                        action={{ href: '/inbox', label: 'Inbox' }}
                        className="xl:col-span-2"
                    >
                        {metrics.recent_conversations.length === 0 ? (
                            <Empty
                                icon={MessageCircle}
                                text="No conversations yet. Inbound WhatsApp messages and messages you log appear here."
                            />
                        ) : (
                            <ul className="-mx-2">
                                {metrics.recent_conversations.map((row) => (
                                    <li key={row.id}>
                                        <Link
                                            href={`/inbox/${row.id}`}
                                            className="flex items-center gap-3 rounded-md px-2 py-2.5 hover:bg-muted/60"
                                        >
                                            <Avatar className="size-9">
                                                <AvatarFallback className="bg-emerald-100 text-xs text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200">
                                                    {getInitials(
                                                        row.contact_name,
                                                    )}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-baseline justify-between gap-2">
                                                    <span
                                                        className={cn(
                                                            'truncate text-sm',
                                                            row.unread_count > 0
                                                                ? 'font-semibold'
                                                                : 'font-medium',
                                                        )}
                                                    >
                                                        {row.contact_name}
                                                    </span>
                                                    <span className="shrink-0 text-xs text-muted-foreground">
                                                        {row.last_message_label}
                                                    </span>
                                                </div>
                                                <p className="line-clamp-1 text-xs text-muted-foreground">
                                                    {row.last_message
                                                        ?.direction ===
                                                    'outbound'
                                                        ? 'You: '
                                                        : ''}
                                                    {row.last_message?.body}
                                                </p>
                                            </div>
                                            {row.unread_count > 0 ? (
                                                <span className="flex h-5 min-w-5 items-center justify-center rounded-full bg-emerald-600 px-1.5 text-[11px] font-semibold text-white">
                                                    {row.unread_count}
                                                </span>
                                            ) : null}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>

                    <Panel
                        title="Coming up"
                        action={{ href: '/calendar', label: 'Calendar' }}
                    >
                        {metrics.upcoming_appointments.length === 0 ? (
                            <Empty
                                icon={CalendarDays}
                                text="Nothing booked. Book calls, demos and visits from a chat or the calendar."
                            />
                        ) : (
                            <ul className="flex flex-col gap-2">
                                {metrics.upcoming_appointments.map((event) => (
                                    <li key={event.key}>
                                        <Link
                                            href={event.href ?? '/calendar'}
                                            className="flex gap-3 rounded-md border p-2.5 hover:bg-muted/50"
                                        >
                                            <div className="flex w-12 shrink-0 flex-col items-center justify-center rounded-md bg-sky-100 py-1 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100">
                                                <span className="text-[10px] font-medium uppercase">
                                                    {
                                                        event.day_label?.split(
                                                            ' ',
                                                        )[0]
                                                    }
                                                </span>
                                                <span className="text-sm font-semibold tabular-nums">
                                                    {event.time}
                                                </span>
                                            </div>
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium">
                                                    {event.title}
                                                </p>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {event.kind_label} ·{' '}
                                                    {event.day_label}
                                                    {event.subtitle
                                                        ? ` · ${event.subtitle}`
                                                        : ''}
                                                </p>
                                            </div>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                </div>

                <div className="grid gap-6 xl:grid-cols-3">
                    <Panel
                        title="Follow-ups"
                        description="Due today and overdue, from the dates set on each lead."
                        className="xl:col-span-2"
                    >
                        {metrics.follow_up_rows.length === 0 ? (
                            <Empty
                                icon={AlarmClock}
                                text="Nothing is due today and nothing is overdue."
                            />
                        ) : (
                            <ul className="divide-y">
                                {metrics.follow_up_rows.map((row) => (
                                    <li key={row.id}>
                                        <Link
                                            href={`/leads/${row.id}`}
                                            className="flex items-center justify-between gap-3 py-2.5 text-sm hover:bg-muted/40"
                                        >
                                            <span className="min-w-0">
                                                <span className="block truncate font-medium">
                                                    {row.contact_name}
                                                    {row.business_name ? (
                                                        <span className="font-normal text-muted-foreground">
                                                            {' '}
                                                            ·{' '}
                                                            {row.business_name}
                                                        </span>
                                                    ) : null}
                                                </span>
                                                <span className="block truncate text-xs text-muted-foreground">
                                                    {row.next_action ??
                                                        'No next action written'}{' '}
                                                    · {row.product_name} ·{' '}
                                                    {row.stage_name}
                                                </span>
                                            </span>
                                            <Badge
                                                variant={
                                                    row.status === 'overdue'
                                                        ? 'destructive'
                                                        : 'outline'
                                                }
                                                className="shrink-0"
                                            >
                                                {row.status === 'overdue' ? (
                                                    <CircleAlert className="size-3" />
                                                ) : null}
                                                {row.reason} ·{' '}
                                                {row.follow_up_label}
                                            </Badge>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>

                    <Panel
                        title="Pipeline"
                        description="Every lead by its current stage."
                    >
                        {metrics.total_leads === 0 ? (
                            <Empty
                                icon={Users}
                                text="No leads yet. When you open one, the stage counts appear here."
                            />
                        ) : (
                            <>
                                <ul className="flex flex-col gap-2.5">
                                    {metrics.leads_by_stage.map((row) => (
                                        <li
                                            key={row.stage_id}
                                            className="text-sm"
                                        >
                                            <div className="flex items-center justify-between gap-2">
                                                <span className="truncate">
                                                    {row.stage_name}
                                                    <span className="text-xs text-muted-foreground">
                                                        {' '}
                                                        · {row.pipeline_name}
                                                    </span>
                                                </span>
                                                <span className="tabular-nums">
                                                    {row.total}
                                                </span>
                                            </div>
                                            <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-muted">
                                                <div
                                                    className={cn(
                                                        'h-full rounded-full',
                                                        row.kind === 'won'
                                                            ? 'bg-emerald-500'
                                                            : row.kind ===
                                                                'lost'
                                                              ? 'bg-rose-400'
                                                              : row.kind ===
                                                                  'archived_nurture'
                                                                ? 'bg-muted-foreground/40'
                                                                : 'bg-sky-500',
                                                    )}
                                                    style={{
                                                        width: `${(row.total / maxStage) * 100}%`,
                                                    }}
                                                />
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                                <div className="mt-4 flex items-center gap-3 border-t pt-3 text-sm">
                                    <Trophy className="size-4 text-emerald-600" />
                                    <span>
                                        {metrics.won_count} won ·{' '}
                                        {metrics.lost_count} lost
                                        {winRate !== null ? (
                                            <span className="text-muted-foreground">
                                                {' '}
                                                · {winRate}% win rate
                                            </span>
                                        ) : null}
                                    </span>
                                </div>
                            </>
                        )}
                    </Panel>
                </div>
            </div>
        </>
    );
}

function summaryLine(metrics: DashboardMetrics): string {
    const parts: string[] = [];

    if (metrics.unread_conversations > 0) {
        parts.push(
            `${metrics.unread_conversations} unread chat${metrics.unread_conversations === 1 ? '' : 's'}`,
        );
    }

    if (metrics.appointments_today > 0) {
        parts.push(
            `${metrics.appointments_today} appointment${metrics.appointments_today === 1 ? '' : 's'} today`,
        );
    }

    if (metrics.overdue_follow_ups > 0) {
        parts.push(
            `${metrics.overdue_follow_ups} overdue follow-up${metrics.overdue_follow_ups === 1 ? '' : 's'}`,
        );
    }

    return parts.length > 0
        ? `You have ${parts.join(', ')}. Times are ${metrics.timezone}.`
        : `You're all caught up. Times are ${metrics.timezone}.`;
}

const TONES = {
    emerald:
        'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    sky: 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
    amber: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    rose: 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
    violet: 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
};

function StatCard({
    icon: Icon,
    tone,
    label,
    value,
    hint,
    href,
}: {
    icon: LucideIcon;
    tone: keyof typeof TONES;
    label: string;
    value: number;
    hint: string;
    href: string;
}) {
    return (
        <Link
            href={href}
            className="group rounded-xl border bg-card p-4 transition-shadow hover:shadow-sm"
        >
            <div className="flex items-center justify-between">
                <span className="text-sm text-muted-foreground">{label}</span>
                <span
                    className={cn(
                        'flex size-8 items-center justify-center rounded-lg',
                        TONES[tone],
                    )}
                >
                    <Icon className="size-4" />
                </span>
            </div>
            <p className="mt-2 text-3xl font-semibold tracking-tight tabular-nums">
                {value}
            </p>
            <p className="mt-1 text-xs text-muted-foreground">{hint}</p>
        </Link>
    );
}

function Panel({
    title,
    description,
    action,
    className,
    children,
}: {
    title: string;
    description?: string;
    action?: { href: string; label: string };
    className?: string;
    children: React.ReactNode;
}) {
    return (
        <section className={cn('rounded-xl border bg-card p-4', className)}>
            <div className="mb-3 flex items-start justify-between gap-2">
                <div>
                    <h2 className="font-medium">{title}</h2>
                    {description ? (
                        <p className="text-xs text-muted-foreground">
                            {description}
                        </p>
                    ) : null}
                </div>
                {action ? (
                    <Link
                        href={action.href}
                        className="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                    >
                        {action.label} <ArrowRight className="size-3" />
                    </Link>
                ) : null}
            </div>
            {children}
        </section>
    );
}

function Empty({ icon: Icon, text }: { icon: LucideIcon; text: string }) {
    return (
        <div className="flex flex-col items-center gap-2 py-6 text-center text-sm text-muted-foreground">
            <Icon className="size-6 opacity-50" />
            <p className="max-w-xs">{text}</p>
        </div>
    );
}
