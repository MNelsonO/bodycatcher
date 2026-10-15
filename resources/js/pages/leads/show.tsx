import { Form, Head, Link } from '@inertiajs/react';
import { AreaField, SelectField, TextField } from '@/components/crm/fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { LeadDetail, Option } from '@/types/crm';

export default function LeadShow({
    lead,
    decision_maker_options,
}: {
    lead: LeadDetail;
    decision_maker_options: Option[];
}) {
    const won = lead.permitted_stages.find((stage) => stage.kind === 'won');
    const lost = lead.permitted_stages.filter((stage) => stage.kind === 'lost');

    return (
        <>
            <Head title={`${lead.contact.full_name} · ${lead.product.name}`} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            {lead.pipeline.name}
                        </p>
                        <h1 className="text-xl font-semibold tracking-tight">
                            {lead.contact.full_name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {lead.contact.business_name ?? 'No business name'} ·{' '}
                            {lead.product.name}
                        </p>
                        <div className="mt-2 flex flex-wrap gap-2">
                            <Badge>{lead.stage.name}</Badge>
                            <Badge variant="outline">{lead.outcome}</Badge>
                            {lead.contact.consent_status === 'opted_out' ? (
                                <Badge variant="outline">Opted out</Badge>
                            ) : null}
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <StageDialog lead={lead} />
                        <NoteDialog leadId={lead.id} />
                        <MessageDialog leadId={lead.id} />
                        <FollowUpDialog lead={lead} />
                        {won ? (
                            <Form
                                action={`/leads/${lead.id}/stage`}
                                method="post"
                            >
                                <input
                                    type="hidden"
                                    name="stage_id"
                                    value={won.id}
                                />
                                <Button type="submit" variant="outline">
                                    Mark won
                                </Button>
                            </Form>
                        ) : (
                            <Button type="button" variant="outline" disabled>
                                Mark won
                            </Button>
                        )}
                        <LostDialog leadId={lead.id} stages={lost} />
                    </div>
                </div>

                <div className="grid gap-6 xl:grid-cols-3">
                    <section className="space-y-4 xl:col-span-2">
                        <div className="rounded-lg border p-4">
                            <h2 className="text-sm font-medium">Contact</h2>
                            <dl className="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                                <Fact
                                    label="Phone"
                                    value={lead.contact.primary_phone}
                                />
                                <Fact
                                    label="Other phones"
                                    value={
                                        lead.contact.additional_phones.join(
                                            ', ',
                                        ) || null
                                    }
                                />
                                <Fact
                                    label="Email"
                                    value={lead.contact.email}
                                />
                                <Fact
                                    label="Location"
                                    value={lead.contact.location}
                                />
                                <Fact
                                    label="Business type"
                                    value={lead.contact.business_type}
                                />
                                <Fact
                                    label="Channel"
                                    value={lead.contact.preferred_channel}
                                />
                                <Fact
                                    label="Consent"
                                    value={lead.contact.consent_status.replaceAll(
                                        '_',
                                        ' ',
                                    )}
                                />
                                <Fact
                                    label="Opted out"
                                    value={lead.contact.opted_out_at_label}
                                />
                                <Fact
                                    label="Contact source"
                                    value={lead.contact.lead_source}
                                />
                                <Fact
                                    label="Contact added"
                                    value={lead.contact.created_at_label}
                                />
                            </dl>
                            <Button
                                variant="link"
                                className="mt-2 px-0"
                                asChild
                            >
                                <Link href={`/contacts/${lead.contact.id}`}>
                                    Open contact
                                </Link>
                            </Button>
                        </div>

                        <Form
                            action={`/leads/${lead.id}`}
                            method="put"
                            className="grid gap-4 rounded-lg border p-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <h2 className="text-sm font-medium">
                                        Opportunity
                                    </h2>
                                    <TextField
                                        name="source"
                                        label="Source"
                                        defaultValue={lead.source}
                                        error={errors.source}
                                    />
                                    <AreaField
                                        name="business_problem"
                                        label="Business problem"
                                        defaultValue={lead.business_problem}
                                        error={errors.business_problem}
                                    />
                                    <AreaField
                                        name="current_solution"
                                        label="Current solution"
                                        defaultValue={lead.current_solution}
                                        error={errors.current_solution}
                                    />
                                    <TextField
                                        name="branch_count"
                                        label="Branch count or size"
                                        type="number"
                                        defaultValue={lead.branch_count}
                                        error={errors.branch_count}
                                    />
                                    <AreaField
                                        name="budget_note"
                                        label="Budget note"
                                        defaultValue={lead.budget_note}
                                        hint="Only what the operator wrote. Blank means no figure was given."
                                        error={errors.budget_note}
                                    />
                                    <SelectField
                                        name="decision_maker_status"
                                        label="Decision maker"
                                        options={decision_maker_options}
                                        defaultValue={
                                            lead.decision_maker_status
                                        }
                                        error={errors.decision_maker_status}
                                    />
                                    <AreaField
                                        name="purchase_timeline_note"
                                        label="Purchase timeline"
                                        defaultValue={
                                            lead.purchase_timeline_note
                                        }
                                        error={errors.purchase_timeline_note}
                                    />
                                    <Button type="submit" disabled={processing}>
                                        Save details
                                    </Button>
                                </>
                            )}
                        </Form>
                    </section>

                    <aside className="space-y-4">
                        <div className="rounded-lg border p-4 text-sm">
                            <h2 className="font-medium">Next step</h2>
                            <p className="mt-2">
                                {lead.next_action ?? 'No next action written.'}
                            </p>
                            <p className="mt-2 text-muted-foreground">
                                Follow-up:{' '}
                                {lead.next_follow_up_label ?? 'no date set'}
                            </p>
                            {lead.stage.default_follow_up_delay_hours !==
                            null ? (
                                <p className="mt-2 text-xs text-muted-foreground">
                                    This stage stores a suggested delay of{' '}
                                    {lead.stage.default_follow_up_delay_hours}{' '}
                                    hours. Nothing schedules it yet.
                                </p>
                            ) : null}
                            {lead.outcome === 'lost' ? (
                                <p className="mt-3">
                                    Lost reason: {lead.lost_reason ?? '—'}
                                </p>
                            ) : null}
                            <p className="mt-3 text-xs text-muted-foreground">
                                Opened {lead.created_at_label}
                            </p>
                        </div>
                        <History history={lead.history} />
                    </aside>
                </div>

                <section className="rounded-lg border p-4">
                    <h2 className="text-sm font-medium">Conversation</h2>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Logged on this desk. Nothing is sent to WhatsApp.
                    </p>
                    {lead.messages.length === 0 ? (
                        <p className="mt-3 text-sm text-muted-foreground">
                            No messages logged yet.
                        </p>
                    ) : (
                        <ol className="mt-3 space-y-3">
                            {lead.messages.map((message) => (
                                <li
                                    key={message.id}
                                    className="rounded-md bg-muted/40 px-3 py-2 text-sm"
                                >
                                    <div className="text-xs text-muted-foreground">
                                        {message.direction} ·{' '}
                                        {message.sent_at_label} ·{' '}
                                        {message.logged_by}
                                    </div>
                                    <p className="mt-1 whitespace-pre-wrap">
                                        {message.body}
                                    </p>
                                </li>
                            ))}
                        </ol>
                    )}
                </section>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="rounded-lg border p-4">
                        <h2 className="text-sm font-medium">Notes</h2>
                        {lead.notes.length === 0 ? (
                            <p className="mt-3 text-sm text-muted-foreground">
                                No notes yet.
                            </p>
                        ) : (
                            <ul className="mt-3 space-y-3 text-sm">
                                {lead.notes.map((note) => (
                                    <li key={note.id}>
                                        <p className="whitespace-pre-wrap">
                                            {note.body}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {note.author} · {note.at}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                    <section className="rounded-lg border p-4">
                        <h2 className="text-sm font-medium">Activity</h2>
                        {lead.activities.length === 0 ? (
                            <p className="mt-3 text-sm text-muted-foreground">
                                No activity yet.
                            </p>
                        ) : (
                            <ul className="mt-3 space-y-2 text-sm">
                                {lead.activities.map((row) => (
                                    <li key={row.id}>
                                        <span>{row.label}</span>
                                        <span className="block text-xs text-muted-foreground">
                                            {row.actor ?? 'System'} · {row.at}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}

function Fact({ label, value }: { label: string; value: string | null }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd>{value && value !== '' ? value : '—'}</dd>
        </div>
    );
}

function History({ history }: { history: LeadDetail['history'] }) {
    return (
        <div className="rounded-lg border p-4">
            <h2 className="text-sm font-medium">Stage history</h2>
            {history.length === 0 ? (
                <p className="mt-2 text-sm text-muted-foreground">
                    No stage history.
                </p>
            ) : (
                <ol className="mt-3 space-y-3 text-sm">
                    {history.map((row) => (
                        <li key={row.id}>
                            <div>
                                {row.from_stage
                                    ? `${row.from_stage} → ${row.to_stage}`
                                    : row.to_stage}
                            </div>
                            {row.reason ? (
                                <p className="text-muted-foreground">
                                    {row.reason}
                                </p>
                            ) : null}
                            <p className="text-xs text-muted-foreground">
                                {row.actor} · {row.at}
                            </p>
                        </li>
                    ))}
                </ol>
            )}
        </div>
    );
}

function StageDialog({ lead }: { lead: LeadDetail }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline">Change stage</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Change stage</DialogTitle>
                    <DialogDescription>
                        Only permitted moves from {lead.stage.name} are listed.
                    </DialogDescription>
                </DialogHeader>
                {lead.permitted_stages.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No permitted transition from this stage. An owner can
                        change that on the pipeline.
                    </p>
                ) : (
                    <Form
                        action={`/leads/${lead.id}/stage`}
                        method="post"
                        className="grid gap-3"
                    >
                        {({ errors, processing }) => (
                            <>
                                <SelectField
                                    name="stage_id"
                                    label="New stage"
                                    required
                                    options={lead.permitted_stages.map(
                                        (stage) => ({
                                            value: String(stage.id),
                                            label: `${stage.name} (${stage.kind})`,
                                        }),
                                    )}
                                    error={errors.stage_id}
                                />
                                <AreaField
                                    name="lost_reason"
                                    label="Reason"
                                    hint="Required when the new stage is lost."
                                    error={errors.lost_reason ?? errors.reason}
                                />
                                <Button type="submit" disabled={processing}>
                                    Update stage
                                </Button>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}

function NoteDialog({ leadId }: { leadId: number }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline">Add note</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add note</DialogTitle>
                    <DialogDescription>Stays on this lead.</DialogDescription>
                </DialogHeader>
                <Form
                    action={`/leads/${leadId}/notes`}
                    method="post"
                    resetOnSuccess
                    className="grid gap-3"
                >
                    {({ errors, processing }) => (
                        <>
                            <AreaField
                                name="body"
                                label="Note"
                                required
                                error={errors.body}
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

function MessageDialog({ leadId }: { leadId: number }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline">Log message</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Log a message</DialogTitle>
                    <DialogDescription>
                        This writes the message to the lead. It does not send
                        it.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/leads/${leadId}/messages`}
                    method="post"
                    resetOnSuccess
                    className="grid gap-3"
                >
                    {({ errors, processing }) => (
                        <>
                            <SelectField
                                name="direction"
                                label="Direction"
                                required
                                options={[
                                    { value: 'inbound', label: 'Inbound' },
                                    { value: 'outbound', label: 'Outbound' },
                                ]}
                                defaultValue="inbound"
                                error={errors.direction}
                            />
                            <AreaField
                                name="body"
                                label="Message"
                                required
                                error={errors.body}
                            />
                            <TextField
                                name="sent_at"
                                label="Sent at"
                                type="datetime-local"
                                hint="Interpreted as Africa/Nairobi. Leave blank to use now."
                                error={errors.sent_at}
                            />
                            <Button type="submit" disabled={processing}>
                                Save message
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function FollowUpDialog({ lead }: { lead: LeadDetail }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline">Set follow-up</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Next follow-up</DialogTitle>
                    <DialogDescription>
                        You set the date. No automation runs it.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/leads/${lead.id}/follow-up`}
                    method="put"
                    className="grid gap-3"
                >
                    {({ errors, processing }) => (
                        <>
                            <TextField
                                name="next_action"
                                label="Next action"
                                defaultValue={lead.next_action}
                                error={errors.next_action}
                            />
                            <TextField
                                name="next_follow_up_on"
                                label="Follow-up date"
                                type="date"
                                defaultValue={lead.next_follow_up_on}
                                error={errors.next_follow_up_on}
                            />
                            <Button type="submit" disabled={processing}>
                                Save follow-up
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function LostDialog({
    leadId,
    stages,
}: {
    leadId: number;
    stages: LeadDetail['permitted_stages'];
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline" disabled={stages.length === 0}>
                    Mark lost
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Mark lost</DialogTitle>
                    <DialogDescription>
                        A reason is required. Disqualified is a lost stage too,
                        if the pipeline allows it from here.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/leads/${leadId}/stage`}
                    method="post"
                    className="grid gap-3"
                >
                    {({ errors, processing }) => (
                        <>
                            <SelectField
                                name="stage_id"
                                label="Lost stage"
                                required
                                options={stages.map((stage) => ({
                                    value: String(stage.id),
                                    label: stage.name,
                                }))}
                                defaultValue={String(
                                    (
                                        stages.find(
                                            (stage) => stage.name === 'Lost',
                                        ) ?? stages[0]
                                    )?.id ?? '',
                                )}
                                error={errors.stage_id}
                            />
                            <AreaField
                                name="lost_reason"
                                label="Reason"
                                required
                                error={errors.lost_reason}
                            />
                            <Button type="submit" disabled={processing}>
                                Mark lost
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

LeadShow.layout = {
    breadcrumbs: [{ title: 'Leads', href: '/leads' }],
};
