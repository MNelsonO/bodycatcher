import { Form, Head, Link } from '@inertiajs/react';
import { TextField, AreaField } from '@/components/crm/fields';
import {
    Page,
    PageHeader,
    Surface,
    SurfaceBody,
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
            <Page>
                <PageHeader
                    title="Pipelines"
                    description="Stages and the moves between them are records, not code."
                    actions={
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
                    }
                />
                {pipelines.length === 0 ? (
                    <Surface>
                        <SurfaceBody className="text-sm text-muted-foreground">
                            No pipelines yet. Seed the Kemet Tech sales pipeline
                            or create one here.
                        </SurfaceBody>
                    </Surface>
                ) : (
                    <Surface>
                        <SurfaceBody className="p-0">
                            <ul className="divide-y divide-border">
                                {pipelines.map((pipeline) => (
                                    <li
                                        key={pipeline.id}
                                        className="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
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
                                                <Button
                                                    type="submit"
                                                    variant="outline"
                                                >
                                                    Delete
                                                </Button>
                                            </Form>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </SurfaceBody>
                    </Surface>
                )}
            </Page>
        </>
    );
}

PipelinesIndex.layout = {
    breadcrumbs: [{ title: 'Pipelines', href: '/pipelines' }],
};
