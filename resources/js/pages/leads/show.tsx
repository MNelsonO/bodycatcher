import { Form, Head, Link } from '@inertiajs/react';
import { MessageCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { AreaField, SelectField, TextField } from '@/components/crm/fields';
import { LeadAnalysisPanel } from '@/components/crm/lead-analysis';
import { FollowUpTasks } from '@/components/crm/follow-up-tasks';
import {
    Page,
    PageHeader,
    Surface,
    SurfaceBody,
    SurfaceHeader,
} from '@/components/crm/page-frame';
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
    product_options,
}: {
    lead: LeadDetail;
    decision_maker_options: Option[];
    product_options: Option[];
}) {
    const won = lead.permitted_stages.find((stage) => stage.kind === 'won');
    const lost = lead.permitted_stages.filter((stage) => stage.kind === 'lost');
    const displayName =
        lead.contact.full_name.trim() !== ''
            ? lead.contact.full_name
            : lead.contact.primary_phone;

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        if (window.location.hash !== '#chat') {
            return;
        }

        document.getElementById('chat')?.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        });
    }, [lead.id, lead.message_count]);

    return (
        <>
            <Head title={`${displayName} · ${lead.product.name}`} />
            <Page width="desk">
                <PageHeader
                    eyebrow={lead.pipeline.name}
                    title={displayName}
                    description={`${lead.contact.business_name ?? 'No business name'} · ${lead.contact.primary_phone}`}
                    actions={
                        <>
                            <Button asChild>
                                <a href="#chat">
                                    <MessageCircle className="size-4" />
                                    Chat
                                    {lead.message_count > 0
                                        ? ` (${lead.message_count})`
                                        : ''}
                                </a>
                            </Button>
                            <StageDialog lead={lead} />
                            <InteractionDialog leadId={lead.id} />
                            <NoteDialog leadId={lead.id} />
                            <MessageDialog leadId={lead.id} />
                            <FollowUpDialog lead={lead} />
                            <ScheduleConversationDialog lead={lead} />
                            <MarkDateDialog lead={lead} />
                            <MessagingControls lead={lead} />
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
                                    <Button
                                        type="submit"
                                        className="bg-success text-success-foreground hover:bg-success/90"
                                    >
                                        Mark won
                                    </Button>
                                </Form>
                            ) : (
                                <Button type="button" variant="outline" disabled>
                                    Mark won
                                </Button>
                            )}
                            <LostDialog leadId={lead.id} stages={lost} />
                        </>
                    }
                />

                <div className="-mt-2 flex flex-wrap gap-2 md:-mt-4">
                    <Badge variant="laundry">{lead.stage.name}</Badge>
                    <Badge
                        variant={
                            lead.outcome === 'won'
                                ? 'success'
                                : lead.outcome === 'lost'
                                  ? 'destructive'
                                  : 'lead'
                        }
                    >
                        {lead.outcome}
                    </Badge>
                    <Badge variant={lead.contact_is_new ? 'default' : 'outline'}>
                        {lead.contact_is_new ? 'New contact' : 'Existing contact'}
                    </Badge>
                    <Badge
                        variant={lead.conversation_is_new ? 'default' : 'outline'}
                    >
                        {lead.conversation_is_new
                            ? 'New conversation'
                            : 'Continuing chat'}
                    </Badge>
                    {lead.intelligence.latest?.status === 'completed' &&
                    lead.intelligence.latest.score !== null ? (
                        <Badge variant="laundry">
                            Score {lead.intelligence.latest.score}
                        </Badge>
                    ) : null}
                    {lead.contact.consent_status === 'opted_out' ? (
                        <Badge variant="destructive">Opted out</Badge>
                    ) : null}
                </div>

                {lead.escalation ? (
                    <EscalationPanel
                        leadId={lead.id}
                        escalation={lead.escalation}
                    />
                ) : null}

                <Surface id="chat" className="scroll-mt-20">
                    <SurfaceHeader
                        title="Chat history"
                        description={`${lead.message_count} message${lead.message_count === 1 ? '' : 's'} on this lead. Full WhatsApp thread for this person.`}
                        badge={
                            <Badge variant="laundry">
                                {lead.message_count} msg
                            </Badge>
                        }
                    />
                    <SurfaceBody>
                        <WhatsAppSend lead={lead} />
                        {lead.messages.length === 0 ? (
                            <p className="mt-3 text-sm text-muted-foreground">
                                No messages yet on this lead. When WhatsApp
                                inbound lands, the thread shows here. You can
                                also log a desk message below.
                            </p>
                        ) : (
                            <ol className="mt-4 space-y-3">
                                {lead.messages.map((message) => {
                                    const inbound =
                                        message.direction === 'inbound';

                                    return (
                                        <li
                                            key={message.id}
                                            className={`flex ${inbound ? 'justify-start' : 'justify-end'}`}
                                        >
                                            <div
                                                className={`max-w-[min(40rem,92%)] rounded-xl px-3.5 py-2.5 text-sm ${
                                                    inbound
                                                        ? 'border border-border bg-card text-foreground shadow-[var(--shadow-card)]'
                                                        : 'bg-primary text-primary-foreground'
                                                }`}
                                            >
                                                <div
                                                    className={`flex flex-wrap gap-x-2 text-xs ${
                                                        inbound
                                                            ? 'text-muted-foreground'
                                                            : 'text-primary-foreground/80'
                                                    }`}
                                                >
                                                    <span>
                                                        {inbound
                                                            ? displayName
                                                            : 'You'}
                                                    </span>
                                                    <span>·</span>
                                                    <span>
                                                        {message.channel ===
                                                        'whatsapp'
                                                            ? 'WhatsApp'
                                                            : 'Desk'}
                                                    </span>
                                                    <span>·</span>
                                                    <span>
                                                        {message.sent_at_label}
                                                    </span>
                                                </div>
                                                <p className="mt-1 whitespace-pre-wrap">
                                                    {message.body}
                                                </p>
                                                {message.refusal_reason ? (
                                                    <p className="mt-1 text-xs text-destructive">
                                                        {
                                                            message.refusal_reason
                                                        }
                                                    </p>
                                                ) : null}
                                                {message.attachments.length >
                                                0 ? (
                                                    <ul
                                                        className={`mt-1 text-xs ${
                                                            inbound
                                                                ? 'text-muted-foreground'
                                                                : 'text-primary-foreground/80'
                                                        }`}
                                                    >
                                                        {message.attachments.map(
                                                            (attachment) => (
                                                                <li
                                                                    key={
                                                                        attachment.id
                                                                    }
                                                                >
                                                                    Attachment
                                                                    {attachment.mime_type
                                                                        ? ` · ${attachment.mime_type}`
                                                                        : ''}
                                                                </li>
                                                            ),
                                                        )}
                                                    </ul>
                                                ) : null}
                                            </div>
                                        </li>
                                    );
                                })}
                            </ol>
                        )}
                    </SurfaceBody>
                </Surface>

                <LeadBriefing
                    lead={lead}
                    productOptions={product_options}
                />

                <div className="grid gap-6 xl:grid-cols-3">
                    <section className="space-y-6 xl:col-span-2">
                        <Surface>
                            <SurfaceHeader title="Contact" />
                            <SurfaceBody>
                                <dl className="grid gap-3 text-sm sm:grid-cols-2">
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
                            </SurfaceBody>
                        </Surface>

                        <Surface>
                            <SurfaceHeader title="Opportunity" />
                            <Form
                                action={`/leads/${lead.id}`}
                                method="put"
                            >
                                {({ errors, processing }) => (
                                    <SurfaceBody className="grid gap-4">
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
                                            error={
                                                errors.purchase_timeline_note
                                            }
                                        />
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Save details
                                        </Button>
                                    </SurfaceBody>
                                )}
                            </Form>
                        </Surface>
                    </section>

                    <aside className="space-y-6">
                        <Surface>
                            <SurfaceHeader title="Next step" />
                            <SurfaceBody className="text-sm">
                                <p>
                                    {lead.next_action ??
                                        'No next action written.'}
                                </p>
                                <p className="mt-2 text-muted-foreground">
                                    Follow-up:{' '}
                                    {lead.next_follow_up_label ?? 'no date set'}
                                </p>
                                <p className="mt-2 text-muted-foreground">
                                    Marked date:{' '}
                                    {lead.marked_on_label
                                        ? `${lead.marked_on_label}${lead.marked_label ? ` · ${lead.marked_label}` : ''}`
                                        : 'none'}
                                </p>
                                {lead.conversation_topic ? (
                                    <div className="mt-3 rounded-md bg-muted/40 p-3">
                                        <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                            Live topic
                                        </p>
                                        <p className="mt-1 font-medium">
                                            {lead.conversation_topic}
                                        </p>
                                        {lead.conversation_topic_summary ? (
                                            <p className="mt-1 text-muted-foreground">
                                                {lead.conversation_topic_summary}
                                            </p>
                                        ) : null}
                                    </div>
                                ) : null}
                                {lead.stage.default_follow_up_delay_hours !==
                                null ? (
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        This stage stores a delay of{' '}
                                        {
                                            lead.stage
                                                .default_follow_up_delay_hours
                                        }{' '}
                                        hours. A stage-delay rule on Follow-ups
                                        uses that number, and a reply cancels
                                        it.
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
                            </SurfaceBody>
                        </Surface>
                        <History history={lead.history} />
                    </aside>
                </div>

                <LeadAnalysisPanel
                    leadId={lead.id}
                    intelligence={lead.intelligence}
                />

                <FollowUpTasks leadId={lead.id} tasks={lead.follow_ups} />

                <Surface>
                    <SurfaceHeader
                        title="Touches & calls"
                        description="Every phone call, meeting, or desk note with date and time — backdating allowed. The sales agent keeps the stage while you speak."
                        badge={
                            <>
                                {lead.next_conversation_label ? (
                                    <Badge variant="laundry">
                                        Next talk ·{' '}
                                        {lead.next_conversation_label}
                                    </Badge>
                                ) : null}
                                {lead.conversations_stopped ? (
                                    <Badge variant="destructive">
                                        Conversations stopped
                                    </Badge>
                                ) : null}
                            </>
                        }
                    />
                    <SurfaceBody>
                        {lead.interactions.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No calls or meetings logged yet. Record each
                                touch.
                            </p>
                        ) : (
                            <ul className="divide-y divide-border rounded-lg border border-border">
                                {lead.interactions.map((row) => (
                                    <li
                                        key={row.id}
                                        className="px-3 py-2 text-sm"
                                    >
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge variant="outline">
                                                {row.type_label}
                                            </Badge>
                                            {row.direction !== 'none' ? (
                                                <span className="text-xs text-muted-foreground">
                                                    {row.direction}
                                                </span>
                                            ) : null}
                                            {row.duration_minutes !== null ? (
                                                <span className="text-xs text-muted-foreground">
                                                    {row.duration_minutes} min
                                                </span>
                                            ) : null}
                                            {row.outcome ? (
                                                <span className="text-xs text-muted-foreground">
                                                    {row.outcome}
                                                </span>
                                            ) : null}
                                        </div>
                                        <p className="mt-1 whitespace-pre-wrap">
                                            {row.body}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {row.author} ·{' '}
                                            {row.occurred_at_label}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </SurfaceBody>
                </Surface>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Surface>
                        <SurfaceHeader title="Notes" />
                        <SurfaceBody>
                            {lead.notes.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No notes yet.
                                </p>
                            ) : (
                                <ul className="space-y-3 text-sm">
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
                        </SurfaceBody>
                    </Surface>
                    <Surface>
                        <SurfaceHeader title="Activity" />
                        <SurfaceBody>
                            {lead.activities.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No activity yet.
                                </p>
                            ) : (
                                <ul className="space-y-2 text-sm">
                                    {lead.activities.map((row) => (
                                        <li key={row.id}>
                                            <span>{row.label}</span>
                                            <span className="block text-xs text-muted-foreground">
                                                {row.actor ?? 'System'} ·{' '}
                                                {row.at}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </SurfaceBody>
                    </Surface>
                </div>
            </Page>
        </>
    );
}

function LeadBriefing({
    lead,
    productOptions,
}: {
    lead: LeadDetail;
    productOptions: Option[];
}) {
    const currentIndex = lead.pipeline_stages.findIndex(
        (stage) => stage.id === lead.stage.id,
    );

    return (
        <Surface>
            <SurfaceHeader
                title="What is this lead?"
                description="We fetch the WhatsApp username (push name) — formal chats often have no business name. Status stays whatever stage you marked. Pick the product when ads mix POS, laundry, hotel, etc."
            />
            <SurfaceBody className="grid gap-5 lg:grid-cols-[1.4fr_1fr]">
                <div className="space-y-4">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            They chased you up for
                        </p>
                        <p className="mt-1 text-base font-medium text-foreground">
                            {lead.intent ??
                                'Not clear yet — check the chat or write the business problem below.'}
                        </p>
                        {lead.conversation_topic &&
                        lead.conversation_topic !== lead.intent ? (
                            <p className="mt-1 text-sm text-muted-foreground">
                                Live topic: {lead.conversation_topic}
                            </p>
                        ) : null}
                    </div>

                    <div>
                        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            Status · {lead.pipeline.name}
                        </p>
                        <ol className="flex flex-wrap gap-2">
                            {lead.pipeline_stages.map((stage, index) => {
                                const active = stage.id === lead.stage.id;
                                const passed =
                                    currentIndex >= 0 && index < currentIndex;
                                const closedTone =
                                    stage.kind === 'won'
                                        ? 'tone-green'
                                        : stage.kind === 'lost'
                                          ? 'tone-red'
                                          : null;

                                return (
                                    <li
                                        key={stage.id}
                                        className={`rounded-lg px-2.5 py-1.5 text-xs font-semibold ${
                                            active
                                                ? closedTone ??
                                                  'bg-primary text-primary-foreground'
                                                : passed
                                                  ? 'bg-secondary text-secondary-foreground'
                                                  : 'bg-muted text-muted-foreground'
                                        }`}
                                    >
                                        {stage.name}
                                    </li>
                                );
                            })}
                        </ol>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Now:{' '}
                            <span className="font-medium text-foreground">
                                {lead.stage.name}
                            </span>
                            {lead.next_follow_up_label
                                ? ` · follow-up ${lead.next_follow_up_label}`
                                : ''}
                            {lead.next_action
                                ? ` · next: ${lead.next_action}`
                                : ''}
                        </p>
                    </div>
                </div>

                <Form
                    action={`/leads/${lead.id}`}
                    method="put"
                    className="rounded-xl border border-border bg-muted p-4"
                >
                    {({ processing, errors }) => (
                        <div className="space-y-3">
                            <SelectField
                                name="product_id"
                                label="Product / system"
                                options={productOptions}
                                defaultValue={String(lead.product.id)}
                                hint="POS, laundry, hotel, etc. Change if the inbound default was wrong."
                                error={errors.product_id}
                            />
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Saving…' : 'Save product'}
                            </Button>
                        </div>
                    )}
                </Form>
            </SurfaceBody>
        </Surface>
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
        <Surface>
            <SurfaceHeader title="Stage history" />
            <SurfaceBody>
                {history.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No stage history.
                    </p>
                ) : (
                    <ol className="space-y-3 text-sm">
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
            </SurfaceBody>
        </Surface>
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

function MessagingControls({ lead }: { lead: LeadDetail }) {
    const flags = [
        lead.whatsapp.conversations_stopped
            ? ['allow_conversations', 'Allow conversations']
            : ['stop_conversations', 'Stop future conversations'],
        lead.whatsapp.automation_paused
            ? ['resume', 'Resume automation']
            : ['pause', 'Pause automation'],
        lead.whatsapp.taken_over
            ? ['release', 'End takeover']
            : ['take_over', 'Take over'],
        lead.whatsapp.needs_personal_attention
            ? ['clear_attention', 'Clear personal attention']
            : ['attention', 'Needs personal attention'],
    ] as const;

    return (
        <>
            {flags.map(([action, label]) => (
                <Form
                    key={action}
                    action={`/leads/${lead.id}/messaging`}
                    method="post"
                >
                    <input type="hidden" name="action" value={action} />
                    <Button
                        type="submit"
                        variant={
                            action === 'stop_conversations'
                                ? 'destructive'
                                : 'outline'
                        }
                    >
                        {label}
                    </Button>
                </Form>
            ))}
        </>
    );
}

function InteractionDialog({ leadId }: { leadId: number }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline">Record touch</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Record a call or meeting</DialogTitle>
                    <DialogDescription>
                        Log every time you spoke. Set the date and time —
                        including backdating — so the automated agent keeps
                        context on this stage.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/leads/${leadId}/interactions`}
                    method="post"
                    resetOnSuccess
                    className="grid gap-3"
                >
                    {({ errors, processing }) => (
                        <>
                            <SelectField
                                name="type"
                                label="Type"
                                required
                                options={[
                                    {
                                        value: 'phone_call',
                                        label: 'Phone call',
                                    },
                                    { value: 'meeting', label: 'Meeting' },
                                    { value: 'note', label: 'Note' },
                                    { value: 'visit', label: 'Visit' },
                                    { value: 'other', label: 'Other' },
                                ]}
                                defaultValue="phone_call"
                                error={errors.type}
                            />
                            <SelectField
                                name="direction"
                                label="Direction"
                                options={[
                                    { value: 'outbound', label: 'Outbound' },
                                    { value: 'inbound', label: 'Inbound' },
                                    { value: 'none', label: 'Not applicable' },
                                ]}
                                defaultValue="outbound"
                                error={errors.direction}
                            />
                            <AreaField
                                name="body"
                                label="What happened"
                                required
                                error={errors.body}
                                hint="Short note the AI can read later."
                            />
                            <TextField
                                name="occurred_at"
                                label="When"
                                type="datetime-local"
                                hint="Africa/Nairobi. Leave blank for now. Backdating is allowed."
                                error={errors.occurred_at}
                            />
                            <TextField
                                name="duration_minutes"
                                label="Duration (minutes)"
                                type="number"
                                error={errors.duration_minutes}
                            />
                            <TextField
                                name="outcome"
                                label="Outcome"
                                hint="e.g. Left voicemail, booked demo, almost buying."
                                error={errors.outcome}
                            />
                            <Button type="submit" disabled={processing}>
                                Save interaction
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function ScheduleConversationDialog({ lead }: { lead: LeadDetail }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline">Schedule conversation</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Schedule next conversation</DialogTitle>
                    <DialogDescription>
                        Keeps this lead on its stage and tells the automated
                        agent when to talk again. Clearing the time removes the
                        schedule.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/leads/${lead.id}/conversation-schedule`}
                    method="put"
                    className="grid gap-3"
                >
                    {({ errors, processing }) => (
                        <>
                            <TextField
                                name="next_conversation_at"
                                label="Next conversation"
                                type="datetime-local"
                                defaultValue={lead.next_conversation_at}
                                hint="Africa/Nairobi. Leave blank to clear."
                                error={errors.next_conversation_at}
                            />
                            <TextField
                                name="next_action"
                                label="Next action"
                                defaultValue={lead.next_action}
                                error={errors.next_action}
                            />
                            <Button type="submit" disabled={processing}>
                                Save schedule
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function WhatsAppSend({ lead }: { lead: LeadDetail }) {
    const [idempotencyKey, setIdempotencyKey] = useState(() =>
        crypto.randomUUID(),
    );
    const desk = lead.whatsapp;

    return (
        <Form
            action={`/leads/${lead.id}/whatsapp`}
            method="post"
            className="grid gap-3 rounded-md border border-dashed border-border p-3"
            onSuccess={() => setIdempotencyKey(crypto.randomUUID())}
        >
            {({ errors, processing }) => (
                <>
                    <div className="text-sm">
                        <p className="font-medium">Send WhatsApp text</p>
                        {desk.opted_out ? (
                            <p className="mt-1 text-destructive">
                                This contact opted out. No WhatsApp message can
                                be sent.
                            </p>
                        ) : null}
                        {!desk.configured ? (
                            <p className="mt-1 text-destructive">
                                WhatsApp ({desk.driver}) is not configured.
                                Fix keys on Integrations / WhatsApp settings.
                            </p>
                        ) : null}
                        {!desk.enforces_service_window ? (
                            <p className="mt-1 text-muted-foreground">
                                FlareSend allows free-form chat anytime (no
                                Meta 24-hour window). Last inbound{' '}
                                {desk.last_inbound_label ?? 'none yet'}.
                            </p>
                        ) : desk.can_send_free_form ? (
                            <p className="mt-1 text-muted-foreground">
                                Customer-service window is open
                                {desk.window_closes_label
                                    ? ` until ${desk.window_closes_label}`
                                    : ''}
                                . Last inbound{' '}
                                {desk.last_inbound_label ?? 'unknown'}.
                            </p>
                        ) : desk.template_ready ? (
                            <p className="mt-1 text-muted-foreground">
                                24-hour window is closed
                                {desk.last_inbound_label
                                    ? ` (last inbound ${desk.last_inbound_label})`
                                    : ''}
                                . Your message will send as Meta template{' '}
                                <span className="font-mono text-xs">
                                    {desk.template_name}
                                </span>{' '}
                                (body as {'{{1}}'}).
                            </p>
                        ) : (
                            <p className="mt-1 text-destructive">
                                The 24-hour window is closed
                                {desk.last_inbound_label
                                    ? ` (last inbound ${desk.last_inbound_label})`
                                    : ' — no inbound WhatsApp yet'}
                                . Set{' '}
                                <span className="font-mono text-xs">
                                    WHATSAPP_TEMPLATE_NAME
                                </span>{' '}
                                to an approved Meta template, or wait for them
                                to message you again.
                            </p>
                        )}
                        {desk.automation_paused ||
                        desk.taken_over ||
                        desk.needs_personal_attention ||
                        desk.automatic_sends_paused ? (
                            <p className="mt-1 text-muted-foreground">
                                Automatic sends are blocked
                                {desk.automatic_sends_paused
                                    ? ' for the whole desk'
                                    : ' on this lead'}
                                . A message you send from here is still a
                                manual send.
                            </p>
                        ) : null}
                    </div>
                    <input
                        type="hidden"
                        name="idempotency_key"
                        value={idempotencyKey}
                    />
                    <AreaField
                        name="body"
                        label="Message"
                        required
                        error={errors.body}
                    />
                    <Button
                        type="submit"
                        disabled={
                            processing ||
                            desk.opted_out ||
                            !desk.configured ||
                            !desk.can_send
                        }
                    >
                        {desk.can_send_free_form
                            ? 'Send on WhatsApp'
                            : desk.template_ready
                              ? 'Send as template'
                              : 'Cannot send yet'}
                    </Button>
                </>
            )}
        </Form>
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

function MarkDateDialog({ lead }: { lead: LeadDetail }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline">Mark date</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Mark a date</DialogTitle>
                    <DialogDescription>
                        Tag this lead with a day (visit, pickup, install).
                        Optionally drop it on your laundry calendar.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/leads/${lead.id}/mark-date`}
                    method="post"
                    className="grid gap-3"
                >
                    {({ errors, processing }) => (
                        <>
                            <TextField
                                name="marked_on"
                                label="Date"
                                type="date"
                                defaultValue={lead.marked_on ?? ''}
                                error={errors.marked_on}
                            />
                            <TextField
                                name="marked_label"
                                label="Label"
                                defaultValue={lead.marked_label ?? ''}
                                hint="e.g. Site visit, Laundry pickup"
                                error={errors.marked_label}
                            />
                            <SelectField
                                name="add_to_calendar"
                                label="Also add to calendar"
                                defaultValue="1"
                                options={[
                                    {
                                        value: '1',
                                        label: 'Yes — blue calendar item',
                                    },
                                    { value: '0', label: 'No — lead only' },
                                ]}
                                error={errors.add_to_calendar}
                            />
                            <Button type="submit" disabled={processing}>
                                Save marked date
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
                <Button variant="destructive" disabled={stages.length === 0}>
                    Mark lost
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Mark lost</DialogTitle>
                    <DialogDescription>
                        A reason is required. Use Lost for wrong fit, competitor
                        wins, or opt-outs.
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

function EscalationPanel({
    leadId,
    escalation,
}: {
    leadId: number;
    escalation: NonNullable<LeadDetail['escalation']>;
}) {
    return (
        <Surface className="border-destructive/40">
            <SurfaceHeader
                title="Correct it, teach the desk, then resume"
                description={
                    <>
                        Automation stopped on this lead. You were emailed
                        {escalation.notified_at_label
                            ? ` (${escalation.notified_at_label})`
                            : ''}{' '}
                        and a Google Calendar ping was queued when Calendar is
                        connected.
                    </>
                }
                badge={<Badge variant="destructive">{escalation.source}</Badge>}
                className="border-destructive/20"
            />
            <SurfaceBody>
                <p className="mb-3 text-xs font-semibold uppercase tracking-wide text-destructive">
                    AI paused — needs your input
                </p>
                <dl className="grid gap-2 text-sm sm:grid-cols-2">
                    <div>
                        <dt className="text-muted-foreground">Name</dt>
                        <dd className="font-medium">
                            {escalation.contact_name ?? '—'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Status</dt>
                        <dd className="font-medium">
                            {escalation.lead_status ?? '—'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">
                            System interested in
                        </dt>
                        <dd className="font-medium">
                            {escalation.product_name ?? '—'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">
                            Current position
                        </dt>
                        <dd className="font-medium">
                            {escalation.stage_name ?? '—'}
                        </dd>
                    </div>
                </dl>
                <p className="mt-3 text-sm">
                    <span className="font-medium">Why: </span>
                    {escalation.reason}
                </p>
                {escalation.inbound_snippet ? (
                    <p className="mt-2 rounded-md bg-muted/50 p-3 text-sm">
                        <span className="font-medium">Latest chat: </span>
                        {escalation.inbound_snippet}
                    </p>
                ) : null}
                <Form
                    action={`/leads/${leadId}/escalations/${escalation.id}/resolve`}
                    method="post"
                    className="mt-4 grid gap-3 md:grid-cols-2"
                >
                    {({ errors, processing }) => (
                        <>
                            <AreaField
                                name="correction"
                                label="Teach the desk (required)"
                                required
                                hint="How should it handle this person next time? Saved to memory + knowledge."
                                error={errors.correction}
                            />
                            <AreaField
                                name="send_reply"
                                label="Optional WhatsApp reply now"
                                defaultValue={escalation.suggested_reply ?? ''}
                                hint="Edit the suggested reply and send it yourself. Leave blank to only teach."
                                error={errors.send_reply}
                            />
                            <SelectField
                                name="resume_automation"
                                label="After saving"
                                options={[
                                    {
                                        value: '1',
                                        label: 'Resume automation on this lead',
                                    },
                                    {
                                        value: '0',
                                        label: 'Keep automation paused',
                                    },
                                ]}
                                defaultValue="1"
                                error={errors.resume_automation}
                            />
                            <div className="flex items-end">
                                <Button type="submit" disabled={processing}>
                                    Save correction
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </SurfaceBody>
        </Surface>
    );
}

LeadShow.layout = {
    breadcrumbs: [{ title: 'Leads', href: '/leads' }],
};
