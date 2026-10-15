import { Form, Head, Link } from '@inertiajs/react';
import { TextField, AreaField } from '@/components/crm/fields';
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

type PipelineRow = {
    id: number;
    name: string;
    description: string | null;
    is_default: boolean;
    archived: boolean;
    stages_count: number;
};

export default function PipelinesIndex({
    pipelines,
}: {
    pipelines: PipelineRow[];
}) {
    return (
        <>
            <Head title="Pipelines" />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Pipelines
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Stages and the moves between them are records, not
                            code.
                        </p>
                    </div>
                    <Dialog>
                        <DialogTrigger asChild>
                            <Button>New pipeline</Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>New pipeline</DialogTitle>
                                <DialogDescription>
                                    Add stages after the pipeline exists.
                                </DialogDescription>
                            </DialogHeader>
                            <Form
                                action="/pipelines"
                                method="post"
                                className="grid gap-3"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <TextField
                                            name="name"
                                            label="Name"
                                            required
                                            error={errors.name}
                                        />
                                        <AreaField
                                            name="description"
                                            label="Description"
                                            error={errors.description}
                                        />
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Create pipeline
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>
                {pipelines.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-8 text-sm text-muted-foreground">
                        No pipelines yet. Seed the Kemet Tech sales pipeline or
                        create one here.
                    </div>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {pipelines.map((pipeline) => (
                            <li
                                key={pipeline.id}
                                className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Link
                                            href={`/pipelines/${pipeline.id}`}
                                            className="font-medium hover:underline"
                                        >
                                            {pipeline.name}
                                        </Link>
                                        {pipeline.is_default ? (
                                            <Badge variant="outline">
                                                Default
                                            </Badge>
                                        ) : null}
                                        {pipeline.archived ? (
                                            <Badge variant="outline">
                                                Archived
                                            </Badge>
                                        ) : null}
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {pipeline.stages_count} stages
                                        {pipeline.description
                                            ? ` · ${pipeline.description}`
                                            : ''}
                                    </p>
                                </div>
                                <div className="flex gap-2">
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={`/pipelines/${pipeline.id}`}
                                        >
                                            Edit stages
                                        </Link>
                                    </Button>
                                    <Form
                                        action={`/pipelines/${pipeline.id}`}
                                        method="delete"
                                    >
                                        <Button type="submit" variant="outline">
                                            Delete
                                        </Button>
                                    </Form>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

PipelinesIndex.layout = {
    breadcrumbs: [{ title: 'Pipelines', href: '/pipelines' }],
};
