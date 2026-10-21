import { Form, Head, Link, router } from '@inertiajs/react';
import { AreaField, SelectField, TextField } from '@/components/crm/fields';
import {
    Page,
    PageHeader,
    Surface,
    SurfaceBody,
    SurfaceHeader,
} from '@/components/crm/page-frame';
import { Button } from '@/components/ui/button';

type DayCell = {
    date: string;
    in_month: boolean;
    count: number;
};

type Activity = {
    id: number;
    activity_on: string;
    title: string;
    notes: string | null;
    activity_type: string;
    color: string;
    lead_id: number | null;
};

const weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

export default function CalendarIndex({
    month,
    month_label,
    selected_day,
    timezone,
    days,
    activities,
    prev_month,
    next_month,
    google_calendar,
}: {
    month: string;
    month_label: string;
    selected_day: string | null;
    timezone: string;
    days: DayCell[];
    activities: Activity[];
    prev_month: string;
    next_month: string;
    google_calendar: {
        activated: boolean;
        configured: boolean;
        label: string;
        last_synced_at: string | null;
        last_sync_error: string | null;
        calendar_id_set: boolean;
    };
}) {
    return (
        <>
            <Head title="Calendar" />
            <Page>
                <style>{`
                    .laundry-calendar .day-cell {
                        border: 1px solid var(--border);
                        background: var(--laundry-white);
                    }
                    .laundry-calendar .day-cell[data-active="true"] {
                        background: var(--primary);
                        color: var(--primary-foreground);
                        border-color: var(--primary);
                    }
                `}</style>

                <PageHeader
                    title="Laundry calendar"
                    description={
                        <>
                            Pick a day, filter the list, and set activity for
                            each date. Times use {timezone}. Google Calendar
                            trade can attach later via{' '}
                            <span className="font-mono text-xs">
                                external_calendar_id
                            </span>
                            .
                        </>
                    }
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={`/calendar?month=${prev_month}`}>
                                    Previous
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={`/calendar?month=${next_month}`}>
                                    Next
                                </Link>
                            </Button>
                        </>
                    }
                />

                <Surface>
                    <SurfaceBody>
                        <p className="font-medium text-primary">
                            Google Calendar ·{' '}
                            {google_calendar.activated ? 'Activated' : 'Off'}
                            {google_calendar.configured
                                ? ' · credentials set'
                                : ' · needs GOOGLE_CALENDAR_* env'}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Activate on{' '}
                            <Link href="/integrations" className="underline">
                                Integrations
                            </Link>
                            . When on, new activities and marked lead dates push
                            to Google. Local board always stays visible.
                        </p>
                        {google_calendar.last_sync_error ? (
                            <p className="mt-1 text-sm text-destructive">
                                {google_calendar.last_sync_error}
                            </p>
                        ) : null}
                    </SurfaceBody>
                </Surface>

                <Surface className="laundry-calendar">
                    <SurfaceHeader
                        title={month_label}
                        action={
                            selected_day ? (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        router.get('/calendar', { month })
                                    }
                                >
                                    Clear day filter
                                </Button>
                            ) : null
                        }
                    />
                    <SurfaceBody>
                        <div className="grid grid-cols-7 gap-2 text-center text-xs font-medium text-muted-foreground">
                            {weekdays.map((label) => (
                                <div key={label}>{label}</div>
                            ))}
                        </div>
                        <div className="mt-2 grid grid-cols-7 gap-2">
                            {days.map((day) => (
                                <button
                                    key={day.date}
                                    type="button"
                                    data-active={
                                        selected_day === day.date
                                            ? 'true'
                                            : 'false'
                                    }
                                    className="day-cell flex min-h-16 flex-col items-start rounded-xl p-2 text-left transition hover:ring-2 hover:ring-primary/30"
                                    style={{
                                        opacity: day.in_month ? 1 : 0.45,
                                    }}
                                    onClick={() =>
                                        router.get('/calendar', {
                                            month,
                                            day: day.date,
                                        })
                                    }
                                >
                                    <span className="text-sm font-medium">
                                        {day.date.slice(-2)}
                                    </span>
                                    {day.count > 0 ? (
                                        <span className="mt-auto text-[10px] opacity-80">
                                            {day.count} item
                                            {day.count === 1 ? '' : 's'}
                                        </span>
                                    ) : null}
                                </button>
                            ))}
                        </div>
                    </SurfaceBody>
                </Surface>

                <div className="grid gap-6 lg:grid-cols-5">
                    <Surface className="lg:col-span-3">
                        <SurfaceHeader
                            title={
                                selected_day
                                    ? `Activities on ${selected_day}`
                                    : 'Activities this month'
                            }
                        />
                        <SurfaceBody>
                            {activities.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Nothing planned yet. Add a laundry day task
                                    on the right.
                                </p>
                            ) : (
                                <ul className="divide-y divide-border">
                                    {activities.map((activity) => (
                                        <li
                                            key={activity.id}
                                            className="flex items-start justify-between gap-3 py-3 first:pt-0 last:pb-0"
                                        >
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <span
                                                        className={
                                                            activity.color ===
                                                            'white'
                                                                ? 'pill-white rounded-full px-2 py-0.5 text-[10px] uppercase tracking-wide'
                                                                : 'pill-blue rounded-full px-2 py-0.5 text-[10px] uppercase tracking-wide'
                                                        }
                                                    >
                                                        {activity.activity_type}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {activity.activity_on}
                                                    </span>
                                                </div>
                                                <p className="mt-1 font-medium">
                                                    {activity.title}
                                                </p>
                                                {activity.notes ? (
                                                    <p className="mt-1 text-sm text-muted-foreground">
                                                        {activity.notes}
                                                    </p>
                                                ) : null}
                                            </div>
                                            <Form
                                                action={`/calendar/${activity.id}`}
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
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </SurfaceBody>
                    </Surface>

                    <Surface className="lg:col-span-2">
                        <SurfaceHeader title="Add activity" />
                        <SurfaceBody>
                            <Form
                                action="/calendar"
                                method="post"
                                className="grid gap-3"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <TextField
                                            name="activity_on"
                                            label="Day"
                                            type="date"
                                            defaultValue={
                                                selected_day ?? `${month}-01`
                                            }
                                            error={errors.activity_on}
                                        />
                                        <TextField
                                            name="title"
                                            label="Title"
                                            error={errors.title}
                                        />
                                        <SelectField
                                            name="activity_type"
                                            label="Type"
                                            defaultValue="task"
                                            options={[
                                                {
                                                    value: 'task',
                                                    label: 'Task',
                                                },
                                                {
                                                    value: 'visit',
                                                    label: 'Visit',
                                                },
                                                {
                                                    value: 'call',
                                                    label: 'Call',
                                                },
                                                {
                                                    value: 'delivery',
                                                    label: 'Delivery',
                                                },
                                                {
                                                    value: 'other',
                                                    label: 'Other',
                                                },
                                            ]}
                                            error={errors.activity_type}
                                        />
                                        <SelectField
                                            name="color"
                                            label="Laundry color"
                                            defaultValue="blue"
                                            options={[
                                                {
                                                    value: 'blue',
                                                    label: 'Blue',
                                                },
                                                {
                                                    value: 'white',
                                                    label: 'White',
                                                },
                                            ]}
                                            error={errors.color}
                                        />
                                        <AreaField
                                            name="notes"
                                            label="Notes"
                                            error={errors.notes}
                                        />
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Save activity
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </SurfaceBody>
                    </Surface>
                </div>
            </Page>
        </>
    );
}

CalendarIndex.layout = {
    breadcrumbs: [{ title: 'Calendar', href: '/calendar' }],
};
