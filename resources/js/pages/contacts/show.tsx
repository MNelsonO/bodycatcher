import { Form, Head, Link } from '@inertiajs/react';
import { AreaField, SelectField, TextField } from '@/components/crm/fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { ContactSummary } from '@/types/crm';

export default function ContactShow({
    contact,
    notes,
    leads,
    subscriptions = [],
    payment_methods = [],
    payment_defaults = { paid_on: '' },
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
    subscriptions?: Array<{
        id: number;
        label: string;
        product_name: string | null;
        amount: number;
        next_due_on: string | null;
        last_paid_on: string | null;
        last_payment_method: string | null;
        overdue: boolean;
        due_soon: boolean;
    }>;
    payment_methods?: Array<{ id: number; name: string }>;
    payment_defaults?: { paid_on: string };
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
                    <div>
                        <h2 className="text-sm font-medium">Client notes</h2>
                        <p className="text-sm text-muted-foreground">
                            Standing context the live AI reads so it knows this
                            client&apos;s conversation area. Keep notes short —
                            they feed the next WhatsApp reply and lead analysis.
                        </p>
                    </div>
                    <Form
                        id="contact-note-form"
                        action={`/contacts/${contact.id}/notes`}
                        method="post"
                        resetOnSuccess
                        className="grid max-w-xl gap-3 rounded-lg border p-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <AreaField
                                    name="body"
                                    label="Add a note"
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

                <section className="space-y-3">
                    <div className="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2 className="text-sm font-medium">
                                Active subscriptions
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                When following up, record the payout date and
                                payment method. Methods are managed in Settings.
                            </p>
                        </div>
                        <Button variant="outline" asChild>
                            <Link href="/billing/subscriptions">
                                All monthly customers
                            </Link>
                        </Button>
                    </div>
                    {subscriptions.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No active subscriptions for this client.
                        </p>
                    ) : (
                        <ul className="space-y-3">
                            {subscriptions.map((sub) => (
                                <li
                                    key={sub.id}
                                    className="rounded-lg border p-4"
                                >
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium">
                                            {sub.label}
                                        </span>
                                        {sub.overdue ? (
                                            <Badge variant="destructive">
                                                Overdue
                                            </Badge>
                                        ) : sub.due_soon ? (
                                            <Badge variant="outline">
                                                Due soon
                                            </Badge>
                                        ) : null}
                                    </div>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {sub.product_name
                                            ? `${sub.product_name} · `
                                            : ''}
                                        {sub.amount.toFixed(2)} · next due{' '}
                                        {sub.next_due_on}
                                        {sub.last_paid_on
                                            ? ` · last paid ${sub.last_paid_on}`
                                            : ''}
                                        {sub.last_payment_method
                                            ? ` · ${sub.last_payment_method}`
                                            : ''}
                                    </p>
                                    <Form
                                        action={`/billing/subscriptions/${sub.id}/payments`}
                                        method="post"
                                        className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                                    >
                                        {({ errors, processing }) => (
                                            <>
                                                <TextField
                                                    name="paid_on"
                                                    label="Paid on"
                                                    type="date"
                                                    required
                                                    defaultValue={
                                                        payment_defaults.paid_on
                                                    }
                                                    error={errors.paid_on}
                                                />
                                                <SelectField
                                                    name="payment_method_id"
                                                    label="Payment method"
                                                    required
                                                    options={payment_methods.map(
                                                        (method) => ({
                                                            value: String(
                                                                method.id,
                                                            ),
                                                            label: method.name,
                                                        }),
                                                    )}
                                                    error={
                                                        errors.payment_method_id
                                                    }
                                                />
                                                <TextField
                                                    name="reference"
                                                    label="Reference"
                                                    error={errors.reference}
                                                />
                                                <div className="flex items-end">
                                                    <Button
                                                        type="submit"
                                                        disabled={
                                                            processing ||
                                                            payment_methods.length ===
                                                                0
                                                        }
                                                    >
                                                        Record payment
                                                    </Button>
                                                </div>
                                            </>
                                        )}
                                    </Form>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="max-w-xl space-y-3">
                    <div>
                        <h2 className="text-sm font-medium">
                            Merge into this contact
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Leads, notes, and the conversation move here.
                            Message history is kept. This does not run because
                            two people share a name.
                        </p>
                    </div>
                    {merge_candidates.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            There is no other contact to merge.
                        </p>
                    ) : (
                        <Form
                            id="contact-merge-form"
                            action={`/contacts/${contact.id}/merge`}
                            method="post"
                            className="grid gap-3 rounded-lg border p-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <SelectField
                                        name="merged_contact_id"
                                        label="Contact to merge"
                                        options={merge_candidates.map(
                                            (candidate) => ({
                                                value: String(candidate.id),
                                                label: candidate.label,
                                            }),
                                        )}
                                        error={errors.merged_contact_id}
                                    />
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                    >
                                        Merge and keep history
                                    </Button>
                                </>
                            )}
                        </Form>
                    )}
                </section>
            </div>
        </>
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
