import { Form, Head, Link } from '@inertiajs/react';
import { AreaField, TextField } from '@/components/crm/fields';
import {
    Page,
    PageHeader,
    Surface,
    SurfaceBody,
    SurfaceHeader,
} from '@/components/crm/page-frame';
import { Button } from '@/components/ui/button';

type Profile = {
    legal_name: string;
    trading_name: string | null;
    address_line1: string | null;
    address_line2: string | null;
    city: string | null;
    country: string | null;
    phone: string | null;
    email: string | null;
    tax_id: string | null;
    bank_details: string | null;
    default_terms: string | null;
    default_tax_rate: number;
    currency: string;
    logo_url: string | null;
};

export default function BusinessProfilePage({ profile }: { profile: Profile }) {
    return (
        <>
            <Head title="Business profile" />
            <Page width="form">
                <PageHeader
                    title="Logo & address"
                    description="These details print on every invoice and share page."
                    actions={
                        <Button variant="outline" asChild>
                            <Link href="/invoices">Invoices</Link>
                        </Button>
                    }
                />

                <Surface>
                    <SurfaceHeader title="Business profile" />
                    <SurfaceBody>
                        <Form
                            action="/billing/profile"
                            method="post"
                            encType="multipart/form-data"
                            className="grid gap-3"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <TextField
                                        name="legal_name"
                                        label="Legal name"
                                        defaultValue={profile.legal_name}
                                        error={errors.legal_name}
                                    />
                                    <TextField
                                        name="trading_name"
                                        label="Trading name"
                                        defaultValue={profile.trading_name}
                                        error={errors.trading_name}
                                    />
                                    <TextField
                                        name="address_line1"
                                        label="Address line 1"
                                        defaultValue={profile.address_line1}
                                        error={errors.address_line1}
                                    />
                                    <TextField
                                        name="address_line2"
                                        label="Address line 2"
                                        defaultValue={profile.address_line2}
                                        error={errors.address_line2}
                                    />
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <TextField
                                            name="city"
                                            label="City"
                                            defaultValue={profile.city}
                                            error={errors.city}
                                        />
                                        <TextField
                                            name="country"
                                            label="Country"
                                            defaultValue={profile.country}
                                            error={errors.country}
                                        />
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <TextField
                                            name="phone"
                                            label="Phone"
                                            defaultValue={profile.phone}
                                            error={errors.phone}
                                        />
                                        <TextField
                                            name="email"
                                            label="Email"
                                            defaultValue={profile.email}
                                            error={errors.email}
                                        />
                                    </div>
                                    <TextField
                                        name="tax_id"
                                        label="Tax ID / PIN"
                                        defaultValue={profile.tax_id}
                                        error={errors.tax_id}
                                    />
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <TextField
                                            name="default_tax_rate"
                                            label="Default tax %"
                                            type="number"
                                            defaultValue={
                                                profile.default_tax_rate
                                            }
                                            error={errors.default_tax_rate}
                                        />
                                        <TextField
                                            name="currency"
                                            label="Currency"
                                            defaultValue={profile.currency}
                                            error={errors.currency}
                                        />
                                    </div>
                                    <AreaField
                                        name="bank_details"
                                        label="Bank / payment details"
                                        defaultValue={profile.bank_details}
                                        error={errors.bank_details}
                                    />
                                    <AreaField
                                        name="default_terms"
                                        label="Default terms and conditions"
                                        defaultValue={profile.default_terms}
                                        error={errors.default_terms}
                                        hint="Copied onto new invoices. You can edit per invoice."
                                    />
                                    <div className="grid gap-2">
                                        <label className="text-sm font-medium">
                                            Logo upload
                                        </label>
                                        {profile.logo_url ? (
                                            <img
                                                src={profile.logo_url}
                                                alt="Current logo"
                                                className="max-h-20 w-fit rounded border border-border bg-card p-2"
                                            />
                                        ) : null}
                                        <input
                                            type="file"
                                            name="logo"
                                            accept="image/*"
                                            className="text-sm"
                                        />
                                        {errors.logo ? (
                                            <p className="text-sm text-destructive">
                                                {errors.logo}
                                            </p>
                                        ) : null}
                                    </div>
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                    >
                                        Save business profile
                                    </Button>
                                </>
                            )}
                        </Form>
                    </SurfaceBody>
                </Surface>
            </Page>
        </>
    );
}

BusinessProfilePage.layout = {
    breadcrumbs: [
        { title: 'Invoices', href: '/invoices' },
        { title: 'Business profile', href: '/billing/profile' },
    ],
};
