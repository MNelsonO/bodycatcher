import { Form, Head } from '@inertiajs/react';
import { AreaField, SelectField, TextField } from '@/components/crm/fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { Option } from '@/types/crm';

type Variable = {
    key: string;
    configured: boolean;
};

type ProductOption = {
    id: number;
    name: string;
};

type Article = {
    id: number;
    title: string;
    body: string;
    product_id: number | null;
    product_name: string | null;
    is_active: boolean;
};

export default function IntelligenceSettings({
    variables,
    configured,
    can_manage,
    products,
    articles,
}: {
    variables: Variable[];
    configured: boolean;
    can_manage: boolean;
    products: ProductOption[];
    articles: Article[];
}) {
    const productOptions: Option[] = [
        { value: 'none', label: 'Any product' },
        ...products.map((product) => ({
            value: String(product.id),
            label: product.name,
        })),
    ];
    const activeOptions: Option[] = [
        { value: '1', label: 'Active' },
        { value: '0', label: 'Inactive' },
    ];

    return (
        <>
            <Head title="Intelligence" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Intelligence
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        The model reads a lead and proposes a summary, a score
                        with reasons, extracted fields, and a reply draft.
                        This page shows whether each variable is set. It never
                        shows the secret.
                    </p>
                </div>

                <section className="rounded-lg border p-4">
                    <h2 className="text-sm font-medium">Configuration</h2>
                    <ul className="mt-3 divide-y text-sm">
                        {variables.map((variable) => (
                            <li
                                key={variable.key}
                                className="flex items-center justify-between gap-3 py-2"
                            >
                                <span className="font-mono text-xs">
                                    {variable.key}
                                </span>
                                <Badge
                                    variant={
                                        variable.configured
                                            ? 'default'
                                            : 'outline'
                                    }
                                >
                                    {variable.configured
                                        ? 'Configured'
                                        : 'Missing'}
                                </Badge>
                            </li>
                        ))}
                    </ul>
                    <p className="mt-3 text-sm text-muted-foreground">
                        {configured
                            ? 'All three variables are set. A read still waits for you to apply fields or send a reply.'
                            : 'Missing variables mean a read is stored as unavailable. The desk does not invent a summary or a score.'}
                    </p>
                    <p className="mt-2 text-xs text-muted-foreground">
                        LLM_BASE_URL is the OpenAI-compatible root, for example
                        the provider&apos;s /v1 path. The desk posts to
                        /chat/completions on that root.
                    </p>
                </section>

                <section className="grid gap-4">
                    <div>
                        <h2 className="text-sm font-medium">Knowledge</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Active notes are retrieved by shared words, with a
                            boost when the note is tied to the lead&apos;s
                            product. They are quoted into the prompt. They are
                            not sent to WhatsApp.
                        </p>
                    </div>

                    {can_manage ? (
                        <Form
                            action="/ai/articles"
                            method="post"
                            resetOnSuccess
                            className="grid gap-3 rounded-lg border p-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <TextField
                                        name="title"
                                        label="Title"
                                        required
                                        error={errors.title}
                                    />
                                    <AreaField
                                        name="body"
                                        label="Note"
                                        required
                                        error={errors.body}
                                    />
                                    <SelectField
                                        name="product_id"
                                        label="Product"
                                        options={productOptions}
                                        defaultValue="none"
                                        error={errors.product_id}
                                    />
                                    <SelectField
                                        name="is_active"
                                        label="Status"
                                        options={activeOptions}
                                        defaultValue="1"
                                        error={errors.is_active}
                                    />
                                    <div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Save note
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            An owner adds and edits knowledge notes.
                        </p>
                    )}

                    {articles.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No knowledge notes yet.
                        </p>
                    ) : (
                        articles.map((article) => (
                            <article
                                key={article.id}
                                className="rounded-lg border p-4"
                            >
                                {can_manage ? (
                                    <Form
                                        action={`/ai/articles/${article.id}`}
                                        method="put"
                                        className="grid gap-3"
                                    >
                                        {({ errors, processing }) => (
                                            <>
                                                <TextField
                                                    name="title"
                                                    label="Title"
                                                    required
                                                    defaultValue={article.title}
                                                    error={errors.title}
                                                />
                                                <AreaField
                                                    name="body"
                                                    label="Note"
                                                    required
                                                    defaultValue={article.body}
                                                    error={errors.body}
                                                />
                                                <SelectField
                                                    name="product_id"
                                                    label="Product"
                                                    options={productOptions}
                                                    defaultValue={
                                                        article.product_id
                                                            ? String(
                                                                  article.product_id,
                                                              )
                                                            : 'none'
                                                    }
                                                    error={errors.product_id}
                                                />
                                                <SelectField
                                                    name="is_active"
                                                    label="Status"
                                                    options={activeOptions}
                                                    defaultValue={
                                                        article.is_active
                                                            ? '1'
                                                            : '0'
                                                    }
                                                    error={errors.is_active}
                                                />
                                                <div>
                                                    <Button
                                                        type="submit"
                                                        variant="outline"
                                                        disabled={processing}
                                                    >
                                                        Update note
                                                    </Button>
                                                </div>
                                            </>
                                        )}
                                    </Form>
                                ) : (
                                    <div className="grid gap-2 text-sm">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h3 className="font-medium">
                                                {article.title}
                                            </h3>
                                            <Badge variant="outline">
                                                {article.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </Badge>
                                        </div>
                                        <p className="whitespace-pre-wrap text-muted-foreground">
                                            {article.body}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {article.product_name ??
                                                'Any product'}
                                        </p>
                                    </div>
                                )}
                            </article>
                        ))
                    )}
                </section>
            </div>
        </>
    );
}
