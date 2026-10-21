import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { AreaField, SelectField, TextField } from '@/components/crm/fields';
import {
    Page,
    PageHeader,
    Surface,
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

type ProductRow = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    pricing_notes: string | null;
    is_active: boolean;
    archived: boolean;
    leads_total: number;
    leads_open: number;
    leads_won: number;
    leads_lost: number;
};

const chip =
    'rounded-full border border-border bg-muted px-3 py-1 text-xs font-medium text-foreground transition-colors hover:bg-secondary hover:text-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';

export default function ProductsIndex({
    products,
}: {
    products: ProductRow[];
}) {
    return (
        <>
            <Head title="Products" />
            <Page width="content">
                <PageHeader
                    title="Products"
                    description="The desk knows each product and tracks leads under it. Pricing notes are whatever you type — no checkout prices."
                    actions={
                        <ProductDialog
                            title="Add product"
                            description="A product a lead can be opened against."
                            action="/products"
                            method="post"
                            product={null}
                            trigger="Add product"
                        />
                    }
                />

                {products.length === 0 ? (
                    <Surface>
                        <div className="px-6 py-12 text-center text-sm text-muted-foreground">
                            No products yet. Add Kemet Laundry OS or whatever
                            you actually sell.
                        </div>
                    </Surface>
                ) : (
                    <Surface>
                        <SurfaceHeader
                            title="Catalogue"
                            badge={
                                <span className="text-sm tabular-nums text-muted-foreground">
                                    {products.length}{' '}
                                    {products.length === 1
                                        ? 'product'
                                        : 'products'}
                                </span>
                            }
                        />
                        <ul className="divide-y divide-border">
                            {products.map((product) => (
                                <li
                                    key={product.id}
                                    className="flex flex-col gap-4 bg-card px-5 py-5 sm:flex-row sm:items-start sm:justify-between"
                                >
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="text-base font-semibold text-foreground">
                                                {product.name}
                                            </span>
                                            <Badge
                                                variant={
                                                    product.archived ||
                                                    !product.is_active
                                                        ? 'outline'
                                                        : 'default'
                                                }
                                            >
                                                {product.archived
                                                    ? 'Archived'
                                                    : product.is_active
                                                      ? 'Active'
                                                      : 'Inactive'}
                                            </Badge>
                                        </div>
                                        <p className="mt-0.5 font-mono text-xs text-muted-foreground">
                                            {product.slug}
                                        </p>
                                        {product.description ? (
                                            <p className="mt-3 max-w-[65ch] text-sm leading-relaxed text-foreground">
                                                {product.description}
                                            </p>
                                        ) : null}
                                        {product.pricing_notes ? (
                                            <p className="mt-1.5 max-w-[65ch] text-sm leading-relaxed text-muted-foreground">
                                                Pricing notes:{' '}
                                                {product.pricing_notes}
                                            </p>
                                        ) : null}
                                        <div className="mt-4 flex flex-wrap gap-2">
                                            <Link
                                                href={`/leads?product_id=${product.id}`}
                                                className={chip}
                                            >
                                                {product.leads_total} leads
                                            </Link>
                                            <Link
                                                href={`/leads?product_id=${product.id}&kind=active`}
                                                className={chip}
                                            >
                                                {product.leads_open} open
                                            </Link>
                                            <Link
                                                href={`/leads?product_id=${product.id}&kind=won`}
                                                className={chip}
                                            >
                                                {product.leads_won} won
                                            </Link>
                                            <Link
                                                href={`/leads?product_id=${product.id}&kind=lost`}
                                                className={chip}
                                            >
                                                {product.leads_lost} lost
                                            </Link>
                                        </div>
                                    </div>
                                    <div className="flex shrink-0 gap-2">
                                        <ProductDialog
                                            title="Edit product"
                                            description="Update the catalogue entry."
                                            action={`/products/${product.id}`}
                                            method="put"
                                            product={product}
                                            trigger="Edit"
                                        />
                                        {product.archived ? null : (
                                            <Form
                                                action={`/products/${product.id}`}
                                                method="delete"
                                            >
                                                <Button
                                                    type="submit"
                                                    variant="outline"
                                                >
                                                    Archive
                                                </Button>
                                            </Form>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </Surface>
                )}
            </Page>
        </>
    );
}

function ProductDialog({
    title,
    description,
    action,
    method,
    product,
    trigger,
}: {
    title: string;
    description: string;
    action: string;
    method: 'post' | 'put';
    product: ProductRow | null;
    trigger: string;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant={product ? 'outline' : 'default'}
                    size={product ? 'default' : 'lg'}
                >
                    {trigger}
                </Button>
            </DialogTrigger>
            <DialogContent className="border border-border bg-card shadow-[var(--shadow-pop)]">
                <DialogHeader>
                    <DialogTitle className="text-xl font-semibold tracking-tight">
                        {title}
                    </DialogTitle>
                    <DialogDescription className="leading-relaxed">
                        {description}
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={action}
                    method={method}
                    className="grid gap-4"
                    onSuccess={() => setOpen(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <TextField
                                name="name"
                                label="Name"
                                required
                                defaultValue={product?.name}
                                error={errors.name}
                            />
                            <TextField
                                name="slug"
                                label="Slug"
                                defaultValue={product?.slug}
                                hint="Leave blank to build it from the name."
                                error={errors.slug}
                            />
                            <AreaField
                                name="description"
                                label="Description"
                                defaultValue={product?.description}
                                error={errors.description}
                            />
                            <AreaField
                                name="pricing_notes"
                                label="Pricing notes"
                                defaultValue={product?.pricing_notes}
                                hint="Plain text. Not a checkout price."
                                error={errors.pricing_notes}
                            />
                            <SelectField
                                name="is_active"
                                label="Status"
                                options={[
                                    { value: '1', label: 'Active' },
                                    { value: '0', label: 'Inactive' },
                                ]}
                                defaultValue={
                                    product
                                        ? product.is_active
                                            ? '1'
                                            : '0'
                                        : '1'
                                }
                                error={errors.is_active}
                            />
                            <Button
                                type="submit"
                                size="lg"
                                disabled={processing}
                            >
                                Save product
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

ProductsIndex.layout = {
    breadcrumbs: [{ title: 'Products', href: '/products' }],
};