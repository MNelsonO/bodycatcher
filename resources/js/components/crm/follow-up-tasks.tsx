import { Form } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { LeadDetail } from '@/types/crm';

const statusLabel: Record<string, string> = {
    pending: 'Pending',
    sent: 'Sent',
    done: 'Done',
    cancelled: 'Cancelled',
    refused: 'Refused',
    failed: 'Failed',
};

export function FollowUpTasks({
    leadId,
    tasks,
}: {
    leadId: number;
    tasks: LeadDetail['follow_ups'];
}) {
    return (
        <section id={`lead-${leadId}-follow-ups`} className="rounded-lg border p-4">
            <h2 className="text-sm font-medium">Follow-ups</h2>
            <p className="mt-1 text-xs text-muted-foreground">
                Rules on the Follow-ups page create these. A no-response task
                is cancelled when this lead gets a WhatsApp reply. Nothing here
                sends until it is due and automation is allowed.
            </p>
            {tasks.length === 0 ? (
                <p className="mt-3 text-sm text-muted-foreground">
                    No follow-up tasks on this lead.
                </p>
            ) : (
                <ul className="mt-3 grid gap-3">
                    {tasks.map((task) => (
                        <li
                            key={task.id}
                            className="rounded-md border border-dashed p-3 text-sm"
                        >
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="font-medium">
                                    {task.rule ?? 'Follow-up'}
                                </span>
                                <Badge variant="outline">
                                    {statusLabel[task.status] ?? task.status}
                                </Badge>
                                <span className="text-xs text-muted-foreground">
                                    Due {task.due_label ?? 'unscheduled'}
                                </span>
                            </div>
                            <p className="mt-2 whitespace-pre-wrap text-muted-foreground">
                                {task.body}
                            </p>
                            {task.held_reason ? (
                                <p className="mt-2 text-xs">{task.held_reason}</p>
                            ) : null}
                            {task.failure_reason ? (
                                <p className="mt-2 text-xs text-destructive">
                                    {task.failure_reason}
                                </p>
                            ) : null}
                            {task.status === 'pending' ? (
                                <div className="mt-3 flex flex-wrap gap-2">
                                    {task.action === 'operator_task' ? (
                                        <Form
                                            action={`/follow-ups/tasks/${task.id}/done`}
                                            method="post"
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    variant="outline"
                                                    disabled={processing}
                                                >
                                                    Mark done
                                                </Button>
                                            )}
                                        </Form>
                                    ) : null}
                                    <Form
                                        action={`/follow-ups/tasks/${task.id}/cancel`}
                                        method="post"
                                    >
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                disabled={processing}
                                            >
                                                Cancel
                                            </Button>
                                        )}
                                    </Form>
                                </div>
                            ) : null}
                        </li>
                    ))}
                </ul>
            )}
            <p className="mt-3 text-xs text-muted-foreground">
                These tasks belong to this lead. The{' '}
                <a href="/follow-ups" className="underline">
                    Follow-ups
                </a>{' '}
                page lists the latest across the desk.
            </p>
        </section>
    );
}
