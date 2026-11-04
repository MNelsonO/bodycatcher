import { Form, Head, Link } from '@inertiajs/react';
import {
    CalendarClock,
    CalendarPlus,
    ChevronLeft,
    ChevronRight,
    Clock,
    MapPin,
    MessageCircle,
    Pencil,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { AppointmentDialog } from '@/components/crm/appointment-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import type { CalendarEvent, LeadOption, Option } from '@/types/crm';

const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

const TYPE_STYLES: Record<CalendarEvent['type'], string> = {
    appointment: 'bg-sky-100 text-sky-900 dark:bg-sky-900/50 dark:text-sky-100',
    follow_up:
        'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
    scheduled_message:
        'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
    task: 'bg-violet-100 text-violet-900 dark:bg-violet-900/40 dark:text-violet-100',
};

const TYPE_LABELS: Record<CalendarEvent['type'], string> = {
    appointment: 'Appointment',
    follow_up: 'Follow-up date',
    scheduled_message: 'Scheduled WhatsApp',
    task: 'Desk task',
};

function addDays(date: string, days: number): string {
    const value = new Date(`${date}T12:00:00Z`);
    value.setUTCDate(value.getUTCDate() + days);

    return value.toISOString().slice(0, 10);
}

function dayHeading(date: string): string {
    return new Date(`${date}T12:00:00Z`).toLocaleDateString('en-GB', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        timeZone: 'UTC',
    });
}

export default function CalendarPage({
    month,
    month_label,
    previous_month,
    next_month,
    grid_start,
    grid_end,
    today,
    events,
    statuses,
    leads,
}: {
    month: string;
    month_label: string;
    previous_month: string;
    next_month: string;
    grid_start: string;
    grid_end: string;
    today: string;
    events: CalendarEvent[];
    kinds: Option[];
    statuses: Option[];
    leads: LeadOption[];
    contacts: Array<{ id: number; full_name: string; primary_phone: string }>;
}) {
    const initialDay = today.startsWith(month) ? today : `${month}-01`;
    const [selected, setSelected] = useState(initialDay);
    const [opened, setOpened] = useState<CalendarEvent | null>(null);
    const [editing, setEditing] = useState<CalendarEvent | null>(null);
    const [creatingOn, setCreatingOn] = useState<string | null>(null);

    const days = useMemo(() => {
        const list: string[] = [];

        for (let day = grid_start; day <= grid_end; day = addDays(day, 1)) {
            list.push(day);
        }

        return list;
    }, [grid_start, grid_end]);

    const byDay = useMemo(() => {
        const map = new Map<string, CalendarEvent[]>();

        for (const event of events) {
            const list = map.get(event.date) ?? [];
            list.push(event);
            map.set(event.date, list);
        }

        return map;
    }, [events]);

    const selectedEvents = byDay.get(selected) ?? [];
    const counts = {
        appointments: events.filter(
            (e) => e.type === 'appointment' && e.status === 'scheduled',
        ).length,
        followUps: events.filter((e) => e.type === 'follow_up').length,
        scheduled: events.filter((e) => e.type === 'scheduled_message').length,
    };

    return (
        <>
            <Head title={`Calendar · ${month_label}`} />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Calendar
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Appointments, follow-up dates and scheduled WhatsApp
                            messages in one place. {counts.appointments} booked
                            · {counts.followUps} follow-ups · {counts.scheduled}{' '}
                            scheduled sends this view.
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="flex items-center rounded-md border">
                            <Button variant="ghost" size="icon" asChild>
                                <Link
                                    href={`/calendar?month=${previous_month}`}
                                    aria-label="Previous month"
                                    preserveScroll
                                >
                                    <ChevronLeft />
                                </Link>
                            </Button>
                            <span className="min-w-32 text-center text-sm font-medium">
                                {month_label}
                            </span>
                            <Button variant="ghost" size="icon" asChild>
                                <Link
                                    href={`/calendar?month=${next_month}`}
                                    aria-label="Next month"
                                    preserveScroll
                                >
                                    <ChevronRight />
                                </Link>
                            </Button>
                        </div>
                        <Button variant="outline" size="sm" asChild>
                            <Link href="/calendar">Today</Link>
                        </Button>
                        <Button
                            size="sm"
                            onClick={() => setCreatingOn(selected)}
                        >
                            <CalendarPlus /> Book
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-[1fr_20rem]">
                    <div className="overflow-hidden rounded-lg border">
                        <div className="grid grid-cols-7 border-b bg-muted/50 text-center text-xs font-medium text-muted-foreground">
                            {WEEKDAYS.map((weekday) => (
                                <div key={weekday} className="py-2">
                                    {weekday}
                                </div>
                            ))}
                        </div>
                        <div className="grid grid-cols-7">
                            {days.map((day) => {
                                const dayEvents = byDay.get(day) ?? [];
                                const inMonth = day.startsWith(month);
                                const isToday = day === today;
                                const isSelected = day === selected;

                                return (
                                    <button
                                        key={day}
                                        type="button"
                                        onClick={() => setSelected(day)}
                                        onDoubleClick={() => setCreatingOn(day)}
                                        className={cn(
                                            'flex min-h-20 flex-col gap-1 border-r border-b p-1.5 text-left align-top transition-colors last:border-r-0 hover:bg-muted/40 sm:min-h-28 [&:nth-child(7n)]:border-r-0',
                                            !inMonth &&
                                                'bg-muted/20 text-muted-foreground',
                                            isSelected &&
                                                'bg-primary/5 ring-2 ring-primary/40 ring-inset',
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'flex size-6 items-center justify-center rounded-full text-xs tabular-nums',
                                                isToday &&
                                                    'bg-primary font-semibold text-primary-foreground',
                                            )}
                                        >
                                            {Number(day.slice(8))}
                                        </span>
                                        <div className="hidden flex-col gap-0.5 sm:flex">
                                            {dayEvents
                                                .slice(0, 3)
                                                .map((event) => (
                                                    <span
                                                        key={event.key}
                                                        className={cn(
                                                            'truncate rounded px-1 py-0.5 text-[11px] leading-tight',
                                                            TYPE_STYLES[
                                                                event.type
                                                            ],
                                                            event.status ===
                                                                'cancelled' &&
                                                                'line-through opacity-60',
                                                        )}
                                                    >
                                                        {event.time
                                                            ? `${event.time} `
                                                            : ''}
                                                        {event.title}
                                                    </span>
                                                ))}
                                            {dayEvents.length > 3 ? (
                                                <span className="px-1 text-[11px] text-muted-foreground">
                                                    +{dayEvents.length - 3} more
                                                </span>
                                            ) : null}
                                        </div>
                                        {dayEvents.length > 0 ? (
                                            <div className="flex flex-wrap gap-0.5 sm:hidden">
                                                {dayEvents
                                                    .slice(0, 4)
                                                    .map((event) => (
                                                        <span
                                                            key={event.key}
                                                            className={cn(
                                                                'size-1.5 rounded-full',
                                                                TYPE_STYLES[
                                                                    event.type
                                                                ],
                                                            )}
                                                        />
                                                    ))}
                                            </div>
                                        ) : null}
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    <aside className="flex flex-col gap-3 rounded-lg border p-4">
                        <div className="flex items-center justify-between gap-2">
                            <h2 className="font-medium">
                                {dayHeading(selected)}
                            </h2>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setCreatingOn(selected)}
                            >
                                <CalendarPlus /> Add
                            </Button>
                        </div>
                        {selectedEvents.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nothing on this day. Double-click a day to book
                                on it.
                            </p>
                        ) : (
                            <ul className="flex flex-col gap-2">
                                {selectedEvents.map((event) => (
                                    <li key={event.key}>
                                        <button
                                            type="button"
                                            onClick={() => setOpened(event)}
                                            className="flex w-full gap-3 rounded-md border p-2.5 text-left transition-colors hover:bg-muted/50"
                                        >
                                            <span
                                                className={cn(
                                                    'w-1 shrink-0 rounded-full',
                                                    TYPE_STYLES[event.type],
                                                )}
                                            />
                                            <span className="min-w-0 flex-1">
                                                <span className="flex items-center justify-between gap-2 text-xs text-muted-foreground">
                                                    <span>
                                                        {event.time
                                                            ? `${event.time}${event.end_time ? `–${event.end_time}` : ''}`
                                                            : 'All day'}
                                                    </span>
                                                    <span>
                                                        {event.kind_label ??
                                                            TYPE_LABELS[
                                                                event.type
                                                            ]}
                                                    </span>
                                                </span>
                                                <span
                                                    className={cn(
                                                        'block truncate text-sm font-medium',
                                                        event.status ===
                                                            'cancelled' &&
                                                            'line-through opacity-60',
                                                    )}
                                                >
                                                    {event.title}
                                                </span>
                                                {event.subtitle ? (
                                                    <span className="block truncate text-xs text-muted-foreground">
                                                        {event.subtitle}
                                                    </span>
                                                ) : null}
                                            </span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                        <div className="mt-auto flex flex-wrap gap-2 border-t pt-3 text-[11px] text-muted-foreground">
                            {(
                                Object.keys(TYPE_LABELS) as Array<
                                    CalendarEvent['type']
                                >
                            ).map((type) => (
                                <span
                                    key={type}
                                    className="flex items-center gap-1"
                                >
                                    <span
                                        className={cn(
                                            'size-2.5 rounded-sm',
                                            TYPE_STYLES[type],
                                        )}
                                    />
                                    {TYPE_LABELS[type]}
                                </span>
                            ))}
                        </div>
                    </aside>
                </div>
            </div>

            <AppointmentDialog
                key={`new-${creatingOn ?? ''}`}
                open={creatingOn !== null}
                onOpenChange={(open) => !open && setCreatingOn(null)}
                leads={leads}
                defaultDate={creatingOn ?? undefined}
            />
            <AppointmentDialog
                key={`edit-${editing?.key ?? ''}`}
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
                leads={leads}
                appointment={editing}
            />
            <EventDialog
                event={opened}
                statuses={statuses}
                onClose={() => setOpened(null)}
                onEdit={(event) => {
                    setOpened(null);
                    setEditing(event);
                }}
            />
        </>
    );
}

function EventDialog({
    event,
    statuses,
    onClose,
    onEdit,
}: {
    event: CalendarEvent | null;
    statuses: Option[];
    onClose: () => void;
    onEdit: (event: CalendarEvent) => void;
}) {
    return (
        <Dialog
            open={event !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            {event ? (
                <DialogContent>
                    <DialogHeader>
                        <div className="flex items-center gap-2">
                            <Badge variant="outline">
                                {event.kind_label ?? TYPE_LABELS[event.type]}
                            </Badge>
                            {event.status_label ? (
                                <Badge variant="secondary">
                                    {event.status_label}
                                </Badge>
                            ) : null}
                        </div>
                        <DialogTitle className="pt-1">
                            {event.title}
                        </DialogTitle>
                        {event.subtitle ? (
                            <DialogDescription>
                                {event.subtitle}
                            </DialogDescription>
                        ) : null}
                    </DialogHeader>
                    <div className="grid gap-2 text-sm">
                        <p className="flex items-center gap-2">
                            <Clock className="size-4 text-muted-foreground" />
                            {dayHeading(event.date)}
                            {event.time
                                ? `, ${event.time}${event.end_time ? `–${event.end_time}` : ''}`
                                : ''}
                        </p>
                        {event.location ? (
                            <p className="flex items-center gap-2">
                                <MapPin className="size-4 text-muted-foreground" />
                                {event.location}
                            </p>
                        ) : null}
                        {event.notes ? (
                            <p className="rounded-md bg-muted p-3 whitespace-pre-wrap">
                                {event.notes}
                            </p>
                        ) : null}
                        {event.type === 'scheduled_message' ? (
                            <p className="flex items-center gap-2 text-muted-foreground">
                                <MessageCircle className="size-4" /> Sent by the
                                follow-up runner at this time.
                            </p>
                        ) : null}
                        {event.type === 'follow_up' ? (
                            <p className="flex items-center gap-2 text-muted-foreground">
                                <CalendarClock className="size-4" /> The
                                follow-up date set on the lead.
                            </p>
                        ) : null}
                    </div>

                    {event.type === 'appointment' && event.id ? (
                        <div className="flex flex-wrap gap-2 border-t pt-3">
                            {statuses
                                .filter(
                                    (status) => status.value !== event.status,
                                )
                                .map((status) => (
                                    <Form
                                        key={status.value}
                                        action={`/calendar/${event.id}/status`}
                                        method="post"
                                        options={{ preserveScroll: true }}
                                        onSuccess={onClose}
                                    >
                                        <input
                                            type="hidden"
                                            name="status"
                                            value={status.value}
                                        />
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            size="sm"
                                        >
                                            Mark {status.label.toLowerCase()}
                                        </Button>
                                    </Form>
                                ))}
                        </div>
                    ) : null}

                    <DialogFooter className="gap-2 sm:justify-between">
                        {event.type === 'appointment' && event.id ? (
                            <Form
                                action={`/calendar/${event.id}`}
                                method="delete"
                                options={{ preserveScroll: true }}
                                onSuccess={onClose}
                                onBefore={() =>
                                    confirm('Remove this appointment?')
                                }
                            >
                                <Button
                                    type="submit"
                                    variant="ghost"
                                    size="sm"
                                    className="text-destructive"
                                >
                                    <Trash2 /> Remove
                                </Button>
                            </Form>
                        ) : (
                            <span />
                        )}
                        <div className="flex gap-2">
                            {event.href ? (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={event.href}>Open lead</Link>
                                </Button>
                            ) : null}
                            {event.type === 'appointment' ? (
                                <Button size="sm" onClick={() => onEdit(event)}>
                                    <Pencil /> Edit
                                </Button>
                            ) : null}
                        </div>
                    </DialogFooter>
                </DialogContent>
            ) : null}
        </Dialog>
    );
}
