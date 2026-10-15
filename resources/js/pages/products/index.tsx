import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import { AreaField, SelectField, TextField } from '@/components/crm/fields';
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
};

export default function ProductsIndex({
    products,
}: {
    products: ProductRow[];
}) {
    return (
        <>
            <Head title="Products" />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Products
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            No prices live here. Pricing notes are whatever you
                            type.
                        </p>
                    </div>
                    <ProductDialog
                        title="Add product"
                        description="A product a lead can be opened against."
                        action="/products"
                        method="post"
                        product={null}
                        trigger="Add product"
                    />
                </div>
                {products.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-8 text-sm text-muted-foreground">
                        No products yet. Add Kemet Laundry OS or whatever you
                        actually sell.
                    </div>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {products.map((product) => (
                            <li
                                key={product.id}
                                className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div>
                                    <div className="flex items-center gap-2">
                                        <span className="font-medium">
                                            {product.name}
                                        </span>
                                        <Badge variant="outline">
                                            {product.archived
                                                ? 'Archived'
                                                : product.is_active
                                                  ? 'Active'
                                                  : 'Inactive'}
                                        </Badge>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {product.slug}
                                    </p>
                                    {product.description ? (
                                        <p className="mt-2 text-sm">
                                            {product.description}
                                        </p>
                                    ) : null}
                                    {product.pricing_notes ? (
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            Pricing notes:{' '}
                                            {product.pricing_notes}
                                        </p>
                                    ) : null}
                                </div>
                                <div className="flex gap-2">
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
                )}
            </div>
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
                <Button variant={product ? 'outline' : 'default'}>
                    {trigger}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <Form
                    action={action}
                    method={method}
                    className="grid gap-3"
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
                            <Button type="submit" disabled={processing}>
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
