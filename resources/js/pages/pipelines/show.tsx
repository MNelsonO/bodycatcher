import { Form, Head, router } from '@inertiajs/react';
import { AreaField, SelectField, TextField } from '@/components/crm/fields';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { Option } from '@/types/crm';

type StageRow = {
    id: number;
    name: string;
    kind: string;
    position: number;
    default_follow_up_delay_hours: number | null;
    archived: boolean;
    targets: number[];
};

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
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        {pipeline.name}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {pipeline.archived ? 'Archived. ' : ''}
                        {pipeline.is_default ? 'Default pipeline. ' : ''}
                        Suggested follow-up hours are stored and not executed.
                    </p>
                </div>

                <Form
                    action={`/pipelines/${pipeline.id}`}
                    method="put"
                    className="grid max-w-xl gap-3"
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

                <div className="flex flex-wrap gap-2">
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
                    <Form action={`/pipelines/${pipeline.id}`} method="delete">
                        <Button type="submit" variant="outline">
                            Delete if unused
                        </Button>
                    </Form>
                </div>

                <section className="space-y-3">
                    <h2 className="text-sm font-medium">Stages</h2>
                    {stages.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No stages yet. Add the first one below.
                        </p>
                    ) : (
                        <ol className="space-y-3">
                            {stages.map((stage, index) => (
                                <li
                                    key={stage.id}
                                    className="rounded-lg border p-3"
                                >
                                    <Form
                                        action={`/pipelines/${pipeline.id}/stages/${stage.id}`}
                                        method="put"
                                        className="grid gap-3 md:grid-cols-4"
                                    >
                                        {({ errors, processing }) => (
                                            <>
                                                <TextField
                                                    name="name"
                                                    label="Name"
                                                    required
                                                    defaultValue={stage.name}
                                                    error={errors.name}
                                                />
                                                <SelectField
                                                    name="kind"
                                                    label="Kind"
                                                    required
                                                    options={kinds}
                                                    defaultValue={stage.kind}
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
                                                <div className="flex items-end gap-2">
                                                    <Button
                                                        type="submit"
                                                        disabled={processing}
                                                    >
                                                        Save
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        disabled={index === 0}
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
                                                            stages.length - 1
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
                                    {stage.archived ? (
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            Archived
                                        </p>
                                    ) : (
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
                        className="grid max-w-3xl gap-3 rounded-lg border border-dashed p-3 md:grid-cols-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <TextField
                                    name="name"
                                    label="New stage"
                                    required
                                    error={errors.name}
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
                                    error={errors.default_follow_up_delay_hours}
                                />
                                <div className="flex items-end">
                                    <Button type="submit" disabled={processing}>
                                        Add stage
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </section>

                <section className="space-y-3">
                    <h2 className="text-sm font-medium">
                        Permitted transitions
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        A lead can only move along a checked path. Illegal moves
                        are rejected and do not write history.
                    </p>
                    {stages.length < 2 ? (
                        <p className="text-sm text-muted-foreground">
                            Add at least two stages before setting transitions.
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
                                            className="rounded-lg border p-3"
                                        >
                                            <legend className="px-1 text-sm font-medium">
                                                From {stage.name}
                                            </legend>
                                            <div className="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                                {stages
                                                    .filter(
                                                        (target) =>
                                                            target.id !==
                                                            stage.id,
                                                    )
                                                    .map((target) => (
                                                        <div
                                                            key={target.id}
                                                            className="flex items-center gap-2"
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
                                                            >
                                                                {target.name}
                                                            </Label>
                                                        </div>
                                                    ))}
                                            </div>
                                        </fieldset>
                                    ))}
                                    {errors.transitions ? (
                                        <p className="text-sm text-red-600">
                                            {errors.transitions}
                                        </p>
                                    ) : null}
                                    <Button type="submit" disabled={processing}>
                                        Save transitions
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

PipelineShow.layout = {
    breadcrumbs: [{ title: 'Pipelines', href: '/pipelines' }],
};
