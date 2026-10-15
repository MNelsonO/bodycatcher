import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { AreaField, SelectField } from '@/components/crm/fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { ContactSummary } from '@/types/crm';

export default function ContactShow({
    contact,
    notes,
    leads,
    merge_candidates,
}: {
    contact: ContactSummary;
    notes: Array<{
        id: number;
        body: string;
        author: string;
        at: string | null;
    }>;
    leads: Array<{
        id: number;
        product_name: string;
        stage_name: string;
        outcome: string;
    }>;
    merge_candidates: Array<{ id: number; label: string }>;
}) {
    return (
        <>
            <Head title={contact.full_name} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            {contact.full_name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {contact.business_name ?? 'No business name'} ·{' '}
                            {contact.primary_phone}
                            {contact.country ? ` · ${contact.country}` : ''}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={`/contacts/${contact.id}/edit`}>
                                Edit
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href={`/leads/create?contact=${contact.id}`}>
                                Open a lead
                            </Link>
                        </Button>
                    </div>
                </div>

                <dl className="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <Item label="Country" value={contact.country} />
                    <Item
                        label="Conversation area"
                        value={contact.conversation_area}
                    />
                    <Item label="Business type" value={contact.business_type} />
                    <Item label="Location" value={contact.location} />
                    <Item label="Email" value={contact.email} />
                    <Item
                        label="Preferred channel"
                        value={contact.preferred_channel}
                    />
                    <Item
                        label="Consent"
                        value={contact.consent_status.replaceAll('_', ' ')}
                    />
                    <Item
                        label="Opted out"
                        value={contact.opted_out_at_label}
                    />
                    <Item label="Lead source" value={contact.lead_source} />
                    <Item label="Added" value={contact.created_at_label} />
                    <Item
                        label="Other phones"
                        value={
                            contact.additional_phones.length
                                ? contact.additional_phones.join(', ')
                                : null
                        }
                    />
                </dl>

                <section className="space-y-3">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 className="text-sm font-medium">
                                Client notes
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Standing context the live AI reads so it knows
                                this client&apos;s conversation area.
                            </p>
                        </div>
                        <AddNoteDialog contactId={contact.id} />
                    </div>
                    {notes.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No client notes yet.
                        </p>
                    ) : (
                        <ul className="divide-y rounded-lg border">
                            {notes.map((note) => (
                                <li key={note.id} className="px-3 py-2 text-sm">
                                    <p className="whitespace-pre-wrap">
                                        {note.body}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {note.author} · {note.at}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="space-y-2">
                    <h2 className="text-sm font-medium">Leads</h2>
                    {leads.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No leads for this contact yet.
                        </p>
                    ) : (
                        <ul className="divide-y rounded-lg border">
                            {leads.map((lead) => (
                                <li key={lead.id}>
                                    <Link
                                        href={`/leads/${lead.id}`}
                                        className="flex items-center justify-between px-3 py-2 text-sm hover:bg-muted/40"
                                    >
                                        <span>
                                            {lead.product_name} ·{' '}
                                            {lead.stage_name}
                                        </span>
                                        <Badge variant="outline">
                                            {lead.outcome}
                                        </Badge>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="max-w-xl space-y-2">
                    <h2 className="text-sm font-medium">
                        Merge into this contact
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Leads, notes, and the conversation move here. Message
                        history is kept. This does not run because two people
                        share a name.
                    </p>
                    {merge_candidates.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            There is no other contact to merge.
                        </p>
                    ) : (
                        <MergeDialog
                            contact={contact}
                            merge_candidates={merge_candidates}
                        />
                    )}
                </section>
            </div>
        </>
    );
}

function AddNoteDialog({ contactId }: { contactId: number }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <Button
                type="button"
                variant="outline"
                className="shrink-0"
                onClick={() => setOpen(true)}
            >
                Add note
            </Button>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add client note</DialogTitle>
                    <DialogDescription>
                        Keep it short. The assistant will use this on the next
                        WhatsApp reply and on lead analysis.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/contacts/${contactId}/notes`}
                    method="post"
                    className="grid gap-3"
                    onSuccess={() => setOpen(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <AreaField
                                name="body"
                                label="Note"
                                required
                                error={errors.body}
                                hint="e.g. Speaks Luganda, quoted in UGX, came from Kampala FB ads."
                            />
                            <Button type="submit" disabled={processing}>
                                Save note
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function MergeDialog({
    contact,
    merge_candidates,
}: {
    contact: ContactSummary;
    merge_candidates: Array<{ id: number; label: string }>;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <Button
                type="button"
                variant="outline"
                onClick={() => setOpen(true)}
            >
                Merge a contact
            </Button>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Merge a contact</DialogTitle>
                    <DialogDescription>
                        {contact.full_name} stays. The other record is marked
                        merged and is no longer a separate person.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/contacts/${contact.id}/merge`}
                    method="post"
                    className="grid gap-3"
                >
                    {({ errors, processing }) => (
                        <>
                            <SelectField
                                name="merged_contact_id"
                                label="Contact to merge"
                                options={merge_candidates.map((candidate) => ({
                                    value: String(candidate.id),
                                    label: candidate.label,
                                }))}
                                error={errors.merged_contact_id}
                            />
                            <Button type="submit" disabled={processing}>
                                Merge and keep history
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function Item({ label, value }: { label: string; value: string | null }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd>{value && value !== '' ? value : '—'}</dd>
        </div>
    );
}

ContactShow.layout = {
    breadcrumbs: [{ title: 'Contacts', href: '/contacts' }],
};
