import { Head, Link, router } from '@inertiajs/react';
import { Loader2, Send, Sparkles, Tag } from 'lucide-react';
import { useState } from 'react';
import {
    Page,
    PageHeader,
    Surface,
    SurfaceBody,
    SurfaceHeader,
} from '@/components/crm/page-frame';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

type LabelRow = {
    label_id: string;
    name: string;
    color: string;
    attached: number;
    waiting: number;
};

type Member = {
    lead_id: number;
    conversation_id: number | null;
    contact_name: string;
    stage: string;
    last_message: string | null;
    last_message_label: string | null;
    skip_reason: string | null;
};

type Draft = {
    lead_id: number;
    conversation_id: number | null;
    contact_name: string;
    body: string | null;
    skip_reason: string | null;
    ai: boolean;
};

function xsrf(): string {
    const match = document.cookie
        .split('; ')
        .find((part) => part.startsWith('XSRF-TOKEN='));

    return match ? decodeURIComponent(match.split('=')[1]) : '';
}

export default function LabelsPage({
    labels,
    selected,
    ai_ready,
    limits,
}: {
    labels: LabelRow[];
    selected: null | {
        label_id: string;
        name: string;
        color: string;
        members: Member[];
    };
    ai_ready: boolean;
    limits: { per_day: number; gap_seconds: number; quiet_hours: number };
}) {
    return (
        <>
            <Head title="Labels" />
            <Page>
                <PageHeader
                    title="WhatsApp labels"
                    description="Your WhatsApp Business labels, with the chats in each. Pick a label to see its chats and send each person a personal message, spaced out safely."
                />

                <div className="grid gap-6 lg:grid-cols-[18rem_1fr]">
                    <Surface className="h-fit">
                        <SurfaceHeader title="Labels" />
                        {labels.length === 0 ? (
                            <SurfaceBody className="text-sm text-muted-foreground">
                                No labels yet. Link WhatsApp on the WhatsApp
                                page and they sync automatically.
                            </SurfaceBody>
                        ) : (
                            <ul className="divide-y divide-border">
                                {labels.map((label) => (
                                    <li key={label.label_id}>
                                        <Link
                                            href={`/labels?label=${label.label_id}`}
                                            preserveScroll
                                            className={cn(
                                                'flex items-center gap-3 px-4 py-2.5 text-sm text-foreground transition-colors hover:bg-muted/50 hover:text-foreground',
                                                selected?.label_id ===
                                                    label.label_id &&
                                                    'bg-muted',
                                            )}
                                        >
                                            <span
                                                className="size-2.5 shrink-0 rounded-full"
                                                style={{
                                                    backgroundColor:
                                                        label.color,
                                                }}
                                            />
                                            <span className="min-w-0 flex-1 truncate font-medium">
                                                {label.name}
                                            </span>
                                            <span className="text-xs text-muted-foreground tabular-nums">
                                                {label.attached}
                                                {label.waiting > 0
                                                    ? ` · +${label.waiting}`
                                                    : ''}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                        <p className="border-t border-border px-4 py-2.5 text-xs text-muted-foreground">
                            Number = chats in the desk. &ldquo;+&rdquo; = chats
                            waiting to be recognised; they join when that person
                            next writes.
                        </p>
                    </Surface>

                    {selected ? (
                        <SelectedLabel
                            key={selected.label_id}
                            selected={selected}
                            aiReady={ai_ready}
                            limits={limits}
                        />
                    ) : (
                        <Surface>
                            <SurfaceBody className="flex flex-col items-center gap-2 py-16 text-center text-sm text-muted-foreground">
                                <Tag className="size-6 opacity-50" />
                                Pick a label to see its chats.
                            </SurfaceBody>
                        </Surface>
                    )}
                </div>
            </Page>
        </>
    );
}

function SelectedLabel({
    selected,
    aiReady,
    limits,
}: {
    selected: {
        label_id: string;
        name: string;
        color: string;
        members: Member[];
    };
    aiReady: boolean;
    limits: { per_day: number; gap_seconds: number; quiet_hours: number };
}) {
    const [goal, setGoal] = useState('');
    const [template, setTemplate] = useState(
        'Hi {name}, quick follow-up from Kemet. Would you like a free demo this week? {link}',
    );
    const [drafts, setDrafts] = useState<Draft[] | null>(null);
    const [chosen, setChosen] = useState<Record<number, boolean>>({});
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const eligible = selected.members.filter((m) => m.skip_reason === null);

    const makeDrafts = async () => {
        setBusy(true);
        setError(null);

        try {
            const response = await fetch(
                `/labels/${selected.label_id}/drafts`,
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-XSRF-TOKEN': xsrf(),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ goal, template }),
                },
            );
            const json = await response.json();

            if (!response.ok) {
                setError(json?.message ?? 'Could not draft messages.');

                return;
            }

            const rows = json.drafts as Draft[];
            setDrafts(rows);
            setChosen(
                Object.fromEntries(
                    rows
                        .filter((row) => row.body !== null)
                        .map((row) => [row.lead_id, true]),
                ),
            );
        } catch {
            setError('Could not draft messages.');
        } finally {
            setBusy(false);
        }
    };

    const send = () => {
        if (!drafts) {
            return;
        }

        const messages = drafts
            .filter((row) => row.body && chosen[row.lead_id])
            .map((row) => ({ lead_id: row.lead_id, body: row.body as string }));

        if (messages.length === 0) {
            return;
        }

        setBusy(true);
        router.post(
            `/labels/${selected.label_id}/send`,
            { messages },
            {
                preserveScroll: true,
                onFinish: () => setBusy(false),
                onSuccess: () => setDrafts(null),
            },
        );
    };

    const count = drafts
        ? drafts.filter((row) => row.body && chosen[row.lead_id]).length
        : 0;

    return (
        <div className="flex flex-col gap-6">
            <Surface>
                <SurfaceHeader
                    title={selected.name}
                    description={`${selected.members.length} chats · ${eligible.length} safe to message now`}
                    action={
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={`/inbox?label=${selected.label_id}`}>
                                Open in Inbox
                            </Link>
                        </Button>
                    }
                    badge={
                        <span
                            className="size-2.5 rounded-full"
                            style={{ backgroundColor: selected.color }}
                        />
                    }
                />
                {selected.members.length === 0 ? (
                    <SurfaceBody className="text-sm text-muted-foreground">
                        No chats in the desk carry this label yet.
                    </SurfaceBody>
                ) : (
                    <ul className="divide-y divide-border">
                        {selected.members.map((member) => (
                            <li
                                key={member.lead_id}
                                className="flex items-center gap-3 px-5 py-2.5 text-sm"
                            >
                                <span className="min-w-0 flex-1">
                                    <Link
                                        href={
                                            member.conversation_id
                                                ? `/inbox/${member.conversation_id}`
                                                : `/leads/${member.lead_id}`
                                        }
                                        className="block truncate font-medium"
                                    >
                                        {member.contact_name}
                                    </Link>
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {member.stage}
                                        {member.last_message
                                            ? ` · ${member.last_message}`
                                            : ''}
                                    </span>
                                </span>
                                {member.skip_reason ? (
                                    <Badge
                                        variant="outline"
                                        className="shrink-0"
                                    >
                                        {member.skip_reason}
                                    </Badge>
                                ) : (
                                    <Badge className="shrink-0">
                                        Can message
                                    </Badge>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </Surface>

            <Surface>
                <SurfaceHeader
                    title="Message this label"
                    description={`Each person gets their own message. Sends go out ${Math.round(limits.gap_seconds / 60)}–${Math.round((limits.gap_seconds + 60) / 60)} minutes apart, at most ${limits.per_day} a day, never to people who have not written to you or got a message in the last ${limits.quiet_hours} hours. If someone replies first, their message is cancelled.`}
                />
                <SurfaceBody className="grid gap-4 text-sm">
                    <div className="grid gap-2">
                        <Label
                            htmlFor="goal"
                            className="flex items-center gap-1.5"
                        >
                            <Sparkles className="size-3.5 text-primary" />
                            Goal for the AI
                            {!aiReady ? (
                                <span className="text-xs font-normal text-muted-foreground">
                                    (add your Claude key to turn on)
                                </span>
                            ) : null}
                        </Label>
                        <Textarea
                            id="goal"
                            rows={2}
                            value={goal}
                            onChange={(event) => setGoal(event.target.value)}
                            disabled={!aiReady}
                            placeholder="e.g. Remind them about the free demo and ask which day suits them."
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="template">
                            Template (used when the AI is off or cannot write a
                            short message)
                        </Label>
                        <Input
                            id="template"
                            value={template}
                            onChange={(event) =>
                                setTemplate(event.target.value)
                            }
                        />
                        <p className="text-xs text-muted-foreground">
                            {'{name}'} = first name, {'{business}'} = business,{' '}
                            {'{link}'} = their product page on kemettech.com.
                        </p>
                    </div>
                    <div>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => void makeDrafts()}
                            disabled={busy || eligible.length === 0}
                        >
                            {busy && !drafts ? (
                                <Loader2 className="animate-spin" />
                            ) : (
                                <Sparkles />
                            )}
                            Draft messages for {eligible.length} people
                        </Button>
                    </div>
                    {error ? <p className="text-destructive">{error}</p> : null}

                    {drafts ? (
                        <div className="grid gap-3">
                            {drafts.map((row) => (
                                <div
                                    key={row.lead_id}
                                    className={cn(
                                        'rounded-lg border p-3',
                                        !row.body && 'opacity-60',
                                    )}
                                >
                                    <div className="mb-2 flex items-center justify-between gap-2">
                                        <label className="flex items-center gap-2 font-medium">
                                            <input
                                                type="checkbox"
                                                disabled={!row.body}
                                                checked={
                                                    !!row.body &&
                                                    !!chosen[row.lead_id]
                                                }
                                                onChange={(event) =>
                                                    setChosen({
                                                        ...chosen,
                                                        [row.lead_id]:
                                                            event.target
                                                                .checked,
                                                    })
                                                }
                                                className="accent-primary"
                                            />
                                            {row.contact_name}
                                        </label>
                                        <span className="text-xs text-muted-foreground">
                                            {row.body
                                                ? row.ai
                                                    ? 'Written by AI'
                                                    : 'From template'
                                                : row.skip_reason}
                                        </span>
                                    </div>
                                    {row.body !== null ? (
                                        <Textarea
                                            rows={2}
                                            value={row.body}
                                            onChange={(event) =>
                                                setDrafts(
                                                    drafts.map((d) =>
                                                        d.lead_id ===
                                                        row.lead_id
                                                            ? {
                                                                  ...d,
                                                                  body: event
                                                                      .target
                                                                      .value,
                                                              }
                                                            : d,
                                                    ),
                                                )
                                            }
                                        />
                                    ) : null}
                                </div>
                            ))}
                            <div>
                                <Button
                                    type="button"
                                    onClick={send}
                                    disabled={busy || count === 0}
                                >
                                    {busy ? (
                                        <Loader2 className="animate-spin" />
                                    ) : (
                                        <Send />
                                    )}
                                    Schedule {count} messages
                                </Button>
                            </div>
                        </div>
                    ) : null}
                </SurfaceBody>
            </Surface>
        </div>
    );
}
