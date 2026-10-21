import { Form, Link } from '@inertiajs/react';
import { AreaField } from '@/components/crm/fields';
import {
    Surface,
    SurfaceBody,
    SurfaceHeader,
} from '@/components/crm/page-frame';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { LeadDetail } from '@/types/crm';

export function LeadAnalysisPanel({
    leadId,
    intelligence,
}: {
    leadId: number;
    intelligence: LeadDetail['intelligence'];
}) {
    const latest = intelligence.latest;

    return (
        <Surface>
            <SurfaceHeader
                title="Intelligence"
                description="The model proposes a summary, a score with reasons, and a reply. Applying a field or sending the reply is a separate step."
                action={
                    <Form
                        action={`/leads/${leadId}/analyses`}
                        method="post"
                    >
                        {({ processing }) => (
                            <Button type="submit" disabled={processing}>
                                Read the conversation
                            </Button>
                        )}
                    </Form>
                }
            />
            <SurfaceBody>
                {!intelligence.configured ? (
                    <p className="text-sm text-muted-foreground">
                        The language model is missing.{' '}
                        <Link href="/ai" className="underline">
                            Check the Intelligence page
                        </Link>{' '}
                        for which environment variables are unset. A read still
                        records that, and it does not invent a score.
                    </p>
                ) : null}

                {latest === null ? (
                    <p className="text-sm text-muted-foreground">
                        No analysis yet.
                    </p>
                ) : null}

                {latest?.status === 'queued' ? (
                    <p className="text-sm">
                        Analysis is queued. If the queue was down, run{' '}
                        <span className="font-mono text-xs">
                            php artisan ai:retry
                        </span>
                        .
                    </p>
                ) : null}

                {latest &&
                (latest.status === 'failed' ||
                    latest.status === 'unavailable') ? (
                    <p className="text-sm text-destructive">
                        {latest.error ?? 'The analysis did not finish.'}
                    </p>
                ) : null}

                {latest?.status === 'completed' ? (
                    <div className="grid gap-4">
                        <div>
                            <p className="text-sm">{latest.summary}</p>
                            {latest.completed_at_label ? (
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {latest.completed_at_label}
                                </p>
                            ) : null}
                        </div>

                        {latest.score !== null ? (
                            <div>
                                <p className="text-sm font-medium">
                                    Score {latest.score}
                                </p>
                                {latest.score_reasons.length > 0 ? (
                                    <ul className="mt-1 list-disc pl-5 text-sm text-muted-foreground">
                                        {latest.score_reasons.map((reason) => (
                                            <li key={reason}>{reason}</li>
                                        ))}
                                    </ul>
                                ) : (
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        No reasons were returned with this
                                        score.
                                    </p>
                                )}
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No score was returned.
                            </p>
                        )}

                        {latest.knowledge.length > 0 ? (
                            <p className="text-xs text-muted-foreground">
                                Knowledge used:{' '}
                                {latest.knowledge
                                    .map((article) => article.title)
                                    .join(', ')}
                            </p>
                        ) : null}

                        {latest.extraction.length > 0 ? (
                            <Form
                                action={`/leads/${leadId}/analyses/${latest.id}/apply`}
                                method="post"
                                className="grid gap-3"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <p className="text-sm font-medium">
                                            Extracted fields
                                        </p>
                                        <div className="grid gap-2">
                                            {latest.extraction.map((field) => (
                                                <label
                                                    key={field.key}
                                                    className="flex items-start gap-2 text-sm"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        name="fields[]"
                                                        value={field.key}
                                                        className="mt-1"
                                                    />
                                                    <span>
                                                        <span className="font-medium">
                                                            {field.label}
                                                        </span>
                                                        <span className="block text-muted-foreground">
                                                            {field.value}
                                                        </span>
                                                    </span>
                                                </label>
                                            ))}
                                        </div>
                                        {errors.fields ? (
                                            <p className="text-sm text-destructive">
                                                {errors.fields}
                                            </p>
                                        ) : null}
                                        <div>
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                disabled={processing}
                                            >
                                                Apply selected fields
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                The model did not extract any lead fields.
                            </p>
                        )}

                        {latest.suggested_stage ? (
                            <Form
                                action={`/leads/${leadId}/analyses/${latest.id}/stage`}
                                method="post"
                                className="grid gap-2"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <p className="text-sm">
                                            Suggested stage:{' '}
                                            <span className="font-medium">
                                                {latest.suggested_stage?.name}
                                            </span>
                                            {latest.stage_reason
                                                ? ` — ${latest.stage_reason}`
                                                : ''}
                                        </p>
                                        {errors.stage_id ? (
                                            <p className="text-sm text-destructive">
                                                {errors.stage_id}
                                            </p>
                                        ) : null}
                                        <div>
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                disabled={processing}
                                            >
                                                Apply suggested stage
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        ) : latest.stage_rejected_reason ? (
                            <p className="text-sm text-muted-foreground">
                                Stage suggestion dropped:{' '}
                                {latest.stage_rejected_reason}
                            </p>
                        ) : null}

                        {latest.suggested_product ? (
                            <Form
                                action={`/leads/${leadId}`}
                                method="put"
                                className="grid gap-2"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="product_id"
                                            value={latest.suggested_product?.id}
                                        />
                                        <p className="text-sm">
                                            Suggested product:{' '}
                                            <span className="font-medium">
                                                {latest.suggested_product?.name}
                                            </span>
                                            {latest.suggested_product?.reason
                                                ? ` — ${latest.suggested_product.reason}`
                                                : ''}
                                        </p>
                                        {errors.product_id ? (
                                            <p className="text-sm text-destructive">
                                                {errors.product_id}
                                            </p>
                                        ) : null}
                                        <div>
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                disabled={processing}
                                            >
                                                Apply suggested product
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        ) : null}

                        {latest.draft ? (
                            <DraftForm leadId={leadId} draft={latest.draft} />
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No reply draft.
                            </p>
                        )}
                    </div>
                ) : null}
            </SurfaceBody>
        </Surface>
    );
}

function DraftForm({
    leadId,
    draft,
}: {
    leadId: number;
    draft: NonNullable<
        NonNullable<LeadDetail['intelligence']['latest']>['draft']
    >;
}) {
    if (draft.status === 'sent') {
        return (
            <div className="grid gap-2 text-sm">
                <div className="flex items-center gap-2">
                    <p className="font-medium">Reply</p>
                    <Badge variant="outline">Sent</Badge>
                </div>
                <p>{draft.body}</p>
            </div>
        );
    }

    return (
        <Form
            key={draft.id}
            action={`/leads/${leadId}/drafts/${draft.id}`}
            method="post"
            className="grid gap-3"
        >
            {({ errors, processing }) => (
                <>
                    <AreaField
                        name="body"
                        label="Reply draft"
                        defaultValue={draft.body}
                        error={errors.body}
                        hint="Approve and send uses the WhatsApp Cloud API as a manual message. Saving keeps the edit and does not send."
                    />
                    {draft.body !== draft.original_body ? (
                        <p className="text-xs text-muted-foreground">
                            Model wording: {draft.original_body}
                        </p>
                    ) : null}
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="submit"
                            name="intent"
                            value="save"
                            variant="outline"
                            disabled={processing}
                        >
                            Save draft
                        </Button>
                        <Button
                            type="submit"
                            name="intent"
                            value="send"
                            disabled={processing}
                        >
                            Approve and send
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
