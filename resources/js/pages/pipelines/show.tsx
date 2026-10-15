import { Form, Head, router } from '@inertiajs/react';
import { AreaField, SelectField, TextField } from '@/components/crm/fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { Option } from '@/types/crm';

type StageRow = {
    id: number;
    name: string;
    purpose: string | null;
    kind: string;
    position: number;
    default_follow_up_delay_hours: number | null;
    archived: boolean;
    targets: number[];
};

const surface =
    'overflow-hidden rounded-xl border border-border bg-card shadow-[var(--shadow-card)]';

function PanelHead({
    title,
    description,
}: {
    title: string;
    description?: string;
}) {
    return (
        <div className="border-b border-border bg-card px-5 py-4">
            <h2 className="text-lg font-semibold tracking-tight text-foreground">
                {title}
            </h2>
            {description ? (
                <p className="mt-1 max-w-[65ch] text-sm leading-relaxed text-muted-foreground">
                    {description}
                </p>
            ) : null}
        </div>
    );
}

export default function PipelineShow({
    pipeline,
    stages,
    kinds,
}: {
    pipeline: {
        id: number;
        name: string;
        description: string | null;
        is_default: boolean;
        archived: boolean;
    };
    stages: StageRow[];
    kinds: Option[];
}) {
    function move(index: number, direction: -1 | 1) {
        const ids = stages.map((stage) => stage.id);
        const target = index + direction;

        if (target < 0 || target >= ids.length) {
            return;
        }

        const next = [...ids];
        const current = next[index];
        const swap = next[target];

        if (current === undefined || swap === undefined) {
            return;
        }

        next[index] = swap;
        next[target] = current;

        router.post(
            `/pipelines/${pipeline.id}/stages/reorder`,
            { stage_ids: next },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title={pipeline.name} />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:gap-8 md:p-8">
                {/* Page header */}
                <div className="border-b border-border pb-5">
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                            {pipeline.name}
                        </h1>
                        {pipeline.is_default ? <Badge>Default</Badge> : null}
                        {pipeline.archived ? (
                            <Badge variant="outline">Archived</Badge>
                        ) : null}
                    </div>
                    <p className="mt-2 max-w-[65ch] text-sm leading-relaxed text-muted-foreground">
                        {pipeline.archived ? 'Archived. ' : ''}
                        {pipeline.is_default ? 'Default pipeline. ' : ''}
                        Stages should read as a clear sales path: contact →
                        qualify → demo → close. Purpose text shows agents what
                        to do in each stage.
                    </p>
                </div>

                {/* Pipeline details */}
                <section className={surface}>
                    <PanelHead title="Pipeline details" />
                    <Form
                        action={`/pipelines/${pipeline.id}`}
                        method="put"
                        className="grid max-w-xl gap-4 bg-card p-5"
                    >
                        {({ errors, processing }) => (
                            <>
                                <TextField
                                    name="name"
                                    label="Name"
                                    required
                                    defaultValue={pipeline.name}
                                    error={errors.name}
                                />
                                <AreaField
                                    name="description"
                                    label="Description"
                                    defaultValue={pipeline.description}
                                    error={errors.description}
                                />
                                <div className="flex gap-2">
                                    <Button type="submit" disabled={processing}>
                                        Save pipeline
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                    <div className="flex flex-wrap gap-2 border-t border-border bg-muted px-5 py-4">
                        {pipeline.archived ? null : (
                            <Form
                                action={`/pipelines/${pipeline.id}/archive`}
                                method="post"
                            >
                                <Button type="submit" variant="outline">
                                    Archive pipeline
                                </Button>
                            </Form>
                        )}
                        <Form
                            action={`/pipelines/${pipeline.id}`}
                            method="delete"
                        >
                            <Button type="submit" variant="outline">
                                Delete if unused
                            </Button>
                        </Form>
                    </div>
                </section>

                {/* Stages */}
                <section className={surface}>
                    <PanelHead
                        title="Stages"
                        description="Reorder with Up and Down. Changes to a stage apply when you press Save on that stage."
                    />
                    <div className="space-y-4 bg-card p-5">
                        {stages.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No stages yet. Add the first one below.
                            </p>
                        ) : (
                            <ol className="space-y-4">
                                {stages.map((stage, index) => (
                                    <li
                                        key={stage.id}
                                        className="rounded-lg border border-border bg-muted p-4"
                                    >
                                        <div className="mb-3 flex items-center gap-2">
                                            <span className="flex size-6 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground">
                                                {index + 1}
                                            </span>
                                            <span className="text-sm font-semibold text-foreground">
                                                {stage.name}
                                            </span>
                                            {stage.archived ? (
                                                <Badge variant="outline">
                                                    Archived
                                                </Badge>
                                            ) : null}
                                        </div>
                                        <Form
                                            action={`/pipelines/${pipeline.id}/stages/${stage.id}`}
                                            method="put"
                                            className="grid gap-4 md:grid-cols-4"
                                        >
                                            {({ errors, processing }) => (
                                                <>
                                                    <TextField
                                                        name="name"
                                                        label="Name"
                                                        required
                                                        defaultValue={
                                                            stage.name
                                                        }
                                                        error={errors.name}
                                                    />
                                                    <TextField
                                                        name="purpose"
                                                        label="Purpose"
                                                        defaultValue={
                                                            stage.purpose ?? ''
                                                        }
                                                        error={errors.purpose}
                                                    />
                                                    <SelectField
                                                        name="kind"
                                                        label="Kind"
                                                        required
                                                        options={kinds}
                                                        defaultValue={
                                                            stage.kind
                                                        }
                                                        error={errors.kind}
                                                    />
                                                    <TextField
                                                        name="default_follow_up_delay_hours"
                                                        label="Follow-up delay (hours)"
                                                        type="number"
                                                        defaultValue={
                                                            stage.default_follow_up_delay_hours
                                                        }
                                                        error={
                                                            errors.default_follow_up_delay_hours
                                                        }
                                                    />
                                                    <div className="flex flex-wrap items-end gap-2 md:col-span-4">
                                                        <Button
                                                            type="submit"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            Save
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            disabled={
                                                                index === 0
                                                            }
                                                            onClick={() =>
                                                                move(index, -1)
                                                            }
                                                        >
                                                            Up
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            disabled={
                                                                index ===
                                                                stages.length -
                                                                    1
                                                            }
                                                            onClick={() =>
                                                                move(index, 1)
                                                            }
                                                        >
                                                            Down
                                                        </Button>
                                                    </div>
                                                </>
                                            )}
                                        </Form>
                                        {stage.purpose ? (
                                            <p className="mt-3 text-xs text-muted-foreground">
                                                {stage.purpose}
                                            </p>
                                        ) : null}
                                        {stage.archived ? null : (
                                            <Form
                                                action={`/pipelines/${pipeline.id}/stages/${stage.id}/archive`}
                                                method="post"
                                                className="mt-2"
                                            >
                                                <Button
                                                    type="submit"
                                                    variant="ghost"
                                                    size="sm"
                                                >
                                                    Archive stage
                                                </Button>
                                            </Form>
                                        )}
                                    </li>
                                ))}
                            </ol>
                        )}

                        <Form
                            action={`/pipelines/${pipeline.id}/stages`}
                            method="post"
                            className="grid gap-4 rounded-lg border border-dashed border-input bg-card p-4 md:grid-cols-2 lg:grid-cols-5"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <TextField
                                        name="name"
                                        label="New stage"
                                        required
                                        error={errors.name}
                                    />
                                    <TextField
                                        name="purpose"
                                        label="Purpose"
                                        error={errors.purpose}
                                    />
                                    <SelectField
                                        name="kind"
                                        label="Kind"
                                        required
                                        options={kinds}
                                        defaultValue="active"
                                        error={errors.kind}
                                    />
                                    <TextField
                                        name="default_follow_up_delay_hours"
                                        label="Follow-up delay (hours)"
                                        type="number"
                                        error={
                                            errors.default_follow_up_delay_hours
                                        }
                                    />
                                    <div className="flex items-end">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Add stage
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </div>
                </section>

                {/* Transitions */}
                <section className={surface}>
                    <PanelHead
                        title="Permitted transitions"
                        description="A lead can only move along a checked path. Illegal moves are rejected and do not write history."
                    />
                    <div className="bg-card p-5">
                        {stages.length < 2 ? (
                            <p className="text-sm text-muted-foreground">
                                Add at least two stages before setting
                                transitions.
                            </p>
                        ) : (
                            <Form
                                action={`/pipelines/${pipeline.id}/transitions`}
                                method="put"
                                className="space-y-4"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        {stages.map((stage) => (
                                            <fieldset
                                                key={stage.id}
                                                className="rounded-lg border border-border bg-muted p-4"
                                            >
                                                <legend className="rounded-full bg-secondary px-3 py-0.5 text-sm font-semibold text-secondary-foreground">
                                                    From {stage.name}
                                                </legend>
                                                <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                                    {stages
                                                        .filter(
                                                            (target) =>
                                                                target.id !==
                                                                stage.id,
                                                        )
                                                        .map((target) => (
                                                            <div
                                                                key={target.id}
                                                                className="flex items-center gap-2.5"
                                                            >
                                                                <Checkbox
                                                                    id={`t-${stage.id}-${target.id}`}
                                                                    name={`transitions[${stage.id}][]`}
                                                                    value={String(
                                                                        target.id,
                                                                    )}
                                                                    defaultChecked={stage.targets.includes(
                                                                        target.id,
                                                                    )}
                                                                />
                                                                <Label
                                                                    htmlFor={`t-${stage.id}-${target.id}`}
                                                                    className="text-sm font-normal"
                                                                >
                                                                    {
                                                                        target.name
                                                                    }
                                                                </Label>
                                                            </div>
                                                        ))}
                                                </div>
                                            </fieldset>
                                        ))}
                                        {errors.transitions ? (
                                            <p className="text-sm font-medium text-destructive">
                                                {errors.transitions}
                                            </p>
                                        ) : null}
                                        <Button
                                            type="submit"
                                            size="lg"
                                            disabled={processing}
                                        >
                                            Save transitions
                                        </Button>
                                    </>
                                )}
                            </Form>
                        )}
                    </div>
                </section>
            </div>
        </>
    );
}

PipelineShow.layout = {
    breadcrumbs: [{ title: 'Pipelines', href: '/pipelines' }],
};