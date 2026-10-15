import { Form, Head, Link, router } from '@inertiajs/react';
import { AreaField, SelectField, TextField } from '@/components/crm/fields';
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
}: {
    month: string;
    month_label: string;
    selected_day: string | null;
    timezone: string;
    days: DayCell[];
    activities: Activity[];
    prev_month: string;
    next_month: string;
}) {
    return (
        <>
            <Head title="Calendar" />
            <div className="laundry-calendar flex flex-col gap-6 rounded-2xl p-4 md:p-6">
                <style>{`
                    .laundry-calendar .day-cell {
                        border: 1px solid color-mix(in srgb, var(--laundry-blue) 18%, white);
                        background: var(--laundry-white);
                    }
                    .laundry-calendar .day-cell[data-active="true"] {
                        background: var(--laundry-blue);
                        color: var(--laundry-white);
                        border-color: var(--laundry-blue);
                    }
                `}</style>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight text-primary">
                            Laundry calendar
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Pick a day, filter the list, and set activity for
                            each date. Times use {timezone}. Google Calendar
                            trade can attach later via{' '}
                            <span className="font-mono text-xs">
                                external_calendar_id
                            </span>
                            .
                        </p>
                    </div>
                    <div className="flex gap-2">
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
                    </div>
                </div>

                <div className="rounded-2xl border border-blue-200 bg-white/80 p-4 shadow-none">
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="text-sm font-semibold text-[var(--laundry-blue)]">
                            {month_label}
                        </h2>
                        {selected_day ? (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    router.get('/calendar', { month })
                                }
                            >
                                Clear day filter
                            </Button>
                        ) : null}
                    </div>
                    <div className="grid grid-cols-7 gap-2 text-center text-xs font-medium text-slate-500">
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
                                    selected_day === day.date ? 'true' : 'false'
                                }
                                className="day-cell flex min-h-16 flex-col items-start rounded-xl p-2 text-left transition hover:ring-2 hover:ring-blue-300"
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
                </div>

                <div className="grid gap-6 lg:grid-cols-5">
                    <section className="rounded-2xl border border-blue-200 bg-white p-4 lg:col-span-3">
                        <h2 className="text-sm font-semibold text-[var(--laundry-blue)]">
                            {selected_day
                                ? `Activities on ${selected_day}`
                                : 'Activities this month'}
                        </h2>
                        {activities.length === 0 ? (
                            <p className="mt-3 text-sm text-slate-600">
                                Nothing planned yet. Add a laundry day task on
                                the right.
                            </p>
                        ) : (
                            <ul className="mt-3 divide-y divide-blue-100">
                                {activities.map((activity) => (
                                    <li
                                        key={activity.id}
                                        className="flex items-start justify-between gap-3 py-3"
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
                                                <span className="text-xs text-slate-500">
                                                    {activity.activity_on}
                                                </span>
                                            </div>
                                            <p className="mt-1 font-medium">
                                                {activity.title}
                                            </p>
                                            {activity.notes ? (
                                                <p className="mt-1 text-sm text-slate-600">
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
                    </section>

                    <section className="rounded-2xl border border-blue-200 bg-white p-4 lg:col-span-2">
                        <h2 className="text-sm font-semibold text-[var(--laundry-blue)]">
                            Add activity
                        </h2>
                        <Form
                            action="/calendar"
                            method="post"
                            className="mt-3 grid gap-3"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <TextField
                                        name="activity_on"
                                        label="Day"
                                        type="date"
                                        defaultValue={
                                            selected_day ??
                                            `${month}-01`
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
                                            { value: 'task', label: 'Task' },
                                            { value: 'visit', label: 'Visit' },
                                            { value: 'call', label: 'Call' },
                                            {
                                                value: 'delivery',
                                                label: 'Delivery',
                                            },
                                            { value: 'other', label: 'Other' },
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
                                    <Button type="submit" disabled={processing}>
                                        Save activity
                                    </Button>
                                </>
                            )}
                        </Form>
                    </section>
                </div>
            </div>
        </>
    );
}

CalendarIndex.layout = {
    breadcrumbs: [{ title: 'Calendar', href: '/calendar' }],
};
