import { Form } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { AreaField, SelectField, TextField } from '@/components/crm/fields';
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
import type { CalendarEvent, LeadOption, Option } from '@/types/crm';

export const APPOINTMENT_KINDS: Option[] = [
    { value: 'call', label: 'Call' },
    { value: 'meeting', label: 'Meeting' },
    { value: 'demo', label: 'Demo' },
    { value: 'site_visit', label: 'Site visit' },
    { value: 'other', label: 'Other' },
];

const DURATIONS: Option[] = [
    { value: '15', label: '15 minutes' },
    { value: '30', label: '30 minutes' },
    { value: '45', label: '45 minutes' },
    { value: '60', label: '1 hour' },
    { value: '90', label: '1½ hours' },
    { value: '120', label: '2 hours' },
    { value: '240', label: 'Half day' },
];

/** Next round half-hour, as a datetime-local value in the browser's clock. */
export function nextSlot(date?: string): string {
    const now = new Date();

    if (date) {
        return `${date}T10:00`;
    }

    now.setMinutes(now.getMinutes() < 30 ? 30 : 60, 0, 0);
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
}

export function AppointmentDialog({
    trigger,
    appointment,
    leads,
    defaultLeadId,
    defaultContactId,
    defaultTitle,
    defaultDate,
    open,
    onOpenChange,
}: {
    trigger?: ReactNode;
    appointment?: CalendarEvent | null;
    leads?: LeadOption[];
    defaultLeadId?: number | null;
    defaultContactId?: number | null;
    defaultTitle?: string;
    defaultDate?: string;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
}) {
    const [internalOpen, setInternalOpen] = useState(false);
    const isOpen = open ?? internalOpen;
    const setOpen = onOpenChange ?? setInternalOpen;
    const editing = appointment?.type === 'appointment' && appointment.id;
    const durationValue = String(appointment?.duration_minutes ?? 30);
    const durations = DURATIONS.some((d) => d.value === durationValue)
        ? DURATIONS
        : [
              ...DURATIONS,
              { value: durationValue, label: `${durationValue} minutes` },
          ];
    const leadOptions: Option[] = [
        { value: 'none', label: 'Not linked to a lead' },
        ...(leads ?? []).map((lead) => ({
            value: String(lead.id),
            label: lead.label,
        })),
    ];
    const linkedLead = appointment?.lead_id ?? defaultLeadId ?? null;
    const [leadId, setLeadId] = useState(
        linkedLead ? String(linkedLead) : 'none',
    );

    return (
        <Dialog open={isOpen} onOpenChange={setOpen}>
            {trigger ? <DialogTrigger asChild>{trigger}</DialogTrigger> : null}
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {editing ? 'Edit appointment' : 'Book an appointment'}
                    </DialogTitle>
                    <DialogDescription>
                        Times are in desk time. Booking does not message the
                        customer; send a confirmation from the chat if you want
                        one.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={
                        editing ? `/calendar/${appointment.id}` : '/calendar'
                    }
                    method={editing ? 'put' : 'post'}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <TextField
                                name="title"
                                label="Title"
                                required
                                defaultValue={
                                    appointment?.title ?? defaultTitle ?? ''
                                }
                                error={errors.title}
                            />
                            <div className="grid gap-4 sm:grid-cols-2">
                                <SelectField
                                    name="kind"
                                    label="Type"
                                    options={APPOINTMENT_KINDS}
                                    defaultValue={appointment?.kind ?? 'call'}
                                    error={errors.kind}
                                />
                                <SelectField
                                    name="duration_minutes"
                                    label="Length"
                                    options={durations}
                                    defaultValue={durationValue}
                                    error={errors.duration_minutes}
                                />
                            </div>
                            <TextField
                                name="starts_at"
                                label="Starts"
                                type="datetime-local"
                                required
                                defaultValue={
                                    appointment?.starts_at_local ??
                                    nextSlot(defaultDate)
                                }
                                error={errors.starts_at}
                            />
                            {leads ? (
                                <SelectField
                                    name="lead_select"
                                    label="Lead"
                                    options={leadOptions}
                                    defaultValue={leadId}
                                    onValueChange={setLeadId}
                                />
                            ) : null}
                            <input
                                type="hidden"
                                name="lead_id"
                                value={leadId === 'none' ? '' : leadId}
                            />
                            {defaultContactId && leadId === 'none' ? (
                                <input
                                    type="hidden"
                                    name="contact_id"
                                    value={defaultContactId}
                                />
                            ) : null}
                            <TextField
                                name="location"
                                label="Where"
                                defaultValue={appointment?.location ?? ''}
                                hint="An address, a phone number, or a meeting link."
                                error={errors.location}
                            />
                            <AreaField
                                name="notes"
                                label="Notes"
                                defaultValue={appointment?.notes ?? ''}
                                error={errors.notes}
                            />
                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    {editing ? 'Save' : 'Book'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
