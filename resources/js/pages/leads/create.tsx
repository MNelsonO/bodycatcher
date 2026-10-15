import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { AreaField, SelectField, TextField } from '@/components/crm/fields';
import { Button } from '@/components/ui/button';
import type { Option, StageOption } from '@/types/crm';

type PipelineChoice = {
    id: number;
    name: string;
    is_default: boolean;
    stages: StageOption[];
};

const surface =
    'overflow-hidden rounded-xl border border-border bg-card shadow-[var(--shadow-card)]';

function FormSection({
    title,
    description,
    children,
}: {
    title: string;
    description?: string;
    children: ReactNode;
}) {
    return (
        <section className={surface}>
            <div className="border-b border-border bg-card px-5 py-4">
                <h2 className="text-lg font-semibold tracking-tight text-foreground">
                    {title}
                </h2>
                {description ? (
                    <p className="mt-1 max-w-[60ch] text-sm leading-relaxed text-muted-foreground">
                        {description}
                    </p>
                ) : null}
            </div>
            <div className="grid gap-4 bg-card p-5 md:grid-cols-2">
                {children}
            </div>
        </section>
    );
}

export default function CreateLead({
    contacts,
    products,
    pipelines,
    decision_maker_options,
    selected_contact_id,
}: {
    contacts: Array<{
        id: number;
        full_name: string;
        business_name: string | null;
        primary_phone: string;
    }>;
    products: Array<{ id: number; name: string }>;
    pipelines: PipelineChoice[];
    decision_maker_options: Option[];
    selected_contact_id: number | null;
}) {
    const defaultPipeline =
        pipelines.find((pipeline) => pipeline.is_default) ?? pipelines[0];
    const [pipelineId, setPipelineId] = useState(
        defaultPipeline ? String(defaultPipeline.id) : '',
    );
    const stages =
        pipelines.find((pipeline) => String(pipeline.id) === pipelineId)
            ?.stages ?? [];
    const firstActive =
        stages.find((stage) => stage.kind === 'active') ?? stages[0];

    return (
        <>
            <Head title="Open a lead" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:gap-8 md:p-8">
                {/* Page header */}
                <div className="border-b border-border pb-5">
                    <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                        Open a lead
                    </h1>
                    <p className="mt-2 max-w-[60ch] text-sm leading-relaxed text-muted-foreground">
                        One contact can have several leads. Two open leads for
                        the same product are refused.
                    </p>
                </div>

                {contacts.length === 0 ||
                products.length === 0 ||
                pipelines.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-input bg-card px-6 py-10 text-sm text-muted-foreground">
                        <p className="max-w-[55ch] leading-relaxed">
                            A lead needs a contact, an active product, and a
                            pipeline with a stage.
                        </p>
                        <div className="mt-4 flex gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/contacts/create">
                                    Add a contact
                                </Link>
                            </Button>
                        </div>
                    </div>
                ) : (
                    <Form
                        action="/leads"
                        method="post"
                        className="flex flex-col gap-6"
                    >
                        {({ errors, processing }) => (
                            <>
                                <FormSection
                                    title="Who and what"
                                    description="The contact, the product they want, and where the lead starts."
                                >
                                    <div className="md:col-span-2">
                                        <SelectField
                                            name="contact_id"
                                            label="Contact"
                                            required
                                            options={contacts.map(
                                                (contact) => ({
                                                    value: String(contact.id),
                                                    label: `${contact.full_name} · ${contact.primary_phone}${contact.business_name ? ` · ${contact.business_name}` : ''}`,
                                                }),
                                            )}
                                            defaultValue={
                                                selected_contact_id
                                                    ? String(
                                                          selected_contact_id,
                                                      )
                                                    : String(
                                                          contacts[0]?.id ?? '',
                                                      )
                                            }
                                            error={errors.contact_id}
                                        />
                                    </div>
                                    <SelectField
                                        name="product_id"
                                        label="Product"
                                        required
                                        options={products.map((product) => ({
                                            value: String(product.id),
                                            label: product.name,
                                        }))}
                                        defaultValue={String(
                                            products[0]?.id ?? '',
                                        )}
                                        error={errors.product_id}
                                    />
                                    <TextField
                                        name="source"
                                        label="Source"
                                        error={errors.source}
                                    />
                                    <SelectField
                                        name="pipeline_id"
                                        label="Pipeline"
                                        required
                                        options={pipelines.map((pipeline) => ({
                                            value: String(pipeline.id),
                                            label: pipeline.name,
                                        }))}
                                        defaultValue={pipelineId}
                                        onValueChange={setPipelineId}
                                        error={errors.pipeline_id}
                                    />
                                    <SelectField
                                        key={pipelineId}
                                        name="stage_id"
                                        label="Initial stage"
                                        required
                                        options={stages.map((stage) => ({
                                            value: String(stage.id),
                                            label: `${stage.name} (${stage.kind})`,
                                        }))}
                                        defaultValue={
                                            firstActive
                                                ? String(firstActive.id)
                                                : ''
                                        }
                                        error={errors.stage_id}
                                    />
                                </FormSection>

                                <FormSection
                                    title="Qualification"
                                    description="What the business needs and how ready they are to buy."
                                >
                                    <div className="md:col-span-2">
                                        <AreaField
                                            name="business_problem"
                                            label="Business problem"
                                            error={errors.business_problem}
                                        />
                                    </div>
                                    <div className="md:col-span-2">
                                        <AreaField
                                            name="current_solution"
                                            label="Current solution"
                                            error={errors.current_solution}
                                        />
                                    </div>
                                    <TextField
                                        name="branch_count"
                                        label="Branch count or size"
                                        type="number"
                                        error={errors.branch_count}
                                    />
                                    <SelectField
                                        name="decision_maker_status"
                                        label="Decision maker"
                                        options={decision_maker_options}
                                        defaultValue="unknown"
                                        error={errors.decision_maker_status}
                                    />
                                    <AreaField
                                        name="budget_note"
                                        label="Budget note"
                                        hint="Free text. Leave it blank if they have not given a figure."
                                        error={errors.budget_note}
                                    />
                                    <AreaField
                                        name="purchase_timeline_note"
                                        label="Purchase timeline"
                                        error={errors.purchase_timeline_note}
                                    />
                                </FormSection>

                                <FormSection
                                    title="Next steps"
                                    description="What happens next and when."
                                >
                                    <TextField
                                        name="next_action"
                                        label="Next action"
                                        error={errors.next_action}
                                    />
                                    <TextField
                                        name="next_follow_up_on"
                                        label="Next follow-up"
                                        type="date"
                                        error={errors.next_follow_up_on}
                                    />
                                    <div className="md:col-span-2">
                                        <AreaField
                                            name="lost_reason"
                                            label="Lost reason"
                                            hint="Required only if the initial stage is a lost stage."
                                            error={errors.lost_reason}
                                        />
                                    </div>
                                </FormSection>

                                <div className="flex items-center gap-3">
                                    <Button
                                        type="submit"
                                        size="lg"
                                        disabled={processing}
                                    >
                                        Open lead
                                    </Button>
                                    <Button variant="outline" size="lg" asChild>
                                        <Link href="/leads">Cancel</Link>
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </>
    );
}

CreateLead.layout = {
    breadcrumbs: [
        { title: 'Leads', href: '/leads' },
        { title: 'Open', href: '/leads/create' },
    ],
};