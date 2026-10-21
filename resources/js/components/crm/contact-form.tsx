import { Form } from '@inertiajs/react';
import { AreaField, SelectField, TextField } from '@/components/crm/fields';
import { Surface, SurfaceBody } from '@/components/crm/page-frame';
import { Button } from '@/components/ui/button';
import type { ContactFormOptions } from '@/types/crm';

export type ContactFormValues = {
    id?: number;
    full_name: string;
    primary_phone: string;
    additional_phones: string;
    business_name: string | null;
    business_type: string | null;
    location: string | null;
    country_code: string | null;
    conversation_area: string | null;
    email: string | null;
    preferred_channel: string;
    consent_status: string;
    lead_source: string | null;
};

export function ContactForm({
    contact,
    options,
}: {
    contact: ContactFormValues | null;
    options: ContactFormOptions;
}) {
    const editing = contact !== null;

    return (
        <Form
            action={editing ? `/contacts/${contact.id}` : '/contacts'}
            method={editing ? 'put' : 'post'}
        >
            {({ errors, processing }) => (
                <Surface>
                    <SurfaceBody className="grid gap-4">
                        <TextField
                            name="full_name"
                            label="Full name"
                            required
                            defaultValue={contact?.full_name}
                            error={errors.full_name}
                        />
                        <TextField
                            name="primary_phone"
                            label="Primary phone"
                            required
                            defaultValue={contact?.primary_phone}
                            error={errors.primary_phone}
                            hint="Use +country code for international numbers. Kenyan locals starting with 07 or 01 become +254."
                        />
                        <AreaField
                            name="additional_phones"
                            label="Additional phones"
                            defaultValue={contact?.additional_phones}
                            error={errors.additional_phones}
                            hint="One number per line. A matching number updates the existing contact instead of creating a duplicate."
                        />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <TextField
                                name="business_name"
                                label="Business name"
                                defaultValue={contact?.business_name}
                                error={errors.business_name}
                            />
                            <TextField
                                name="business_type"
                                label="Business type"
                                defaultValue={contact?.business_type}
                                error={errors.business_type}
                            />
                            <SelectField
                                name="country_code"
                                label="Country"
                                options={options.countries}
                                defaultValue={
                                    contact?.country_code ?? '__none__'
                                }
                                error={errors.country_code}
                                hint="Where this client sits — you run ads across multiple countries."
                            />
                            <TextField
                                name="location"
                                label="City / location"
                                defaultValue={contact?.location}
                                error={errors.location}
                            />
                            <TextField
                                name="conversation_area"
                                label="Conversation area"
                                defaultValue={contact?.conversation_area}
                                error={errors.conversation_area}
                                hint="Market or ad angle the AI should stay in (e.g. Kampala Facebook laundry demos)."
                            />
                            <TextField
                                name="email"
                                label="Email"
                                type="email"
                                defaultValue={contact?.email}
                                error={errors.email}
                            />
                        </div>
                        <SelectField
                            name="preferred_channel"
                            label="Preferred channel"
                            required
                            options={options.channels}
                            defaultValue={
                                contact?.preferred_channel ?? 'whatsapp'
                            }
                            error={errors.preferred_channel}
                        />
                        <SelectField
                            name="consent_status"
                            label="Consent"
                            required
                            options={options.consent}
                            defaultValue={contact?.consent_status ?? 'unknown'}
                            error={errors.consent_status}
                        />
                        <TextField
                            name="lead_source"
                            label="Lead source"
                            defaultValue={contact?.lead_source}
                            error={errors.lead_source}
                        />
                        <Button type="submit" disabled={processing}>
                            {editing ? 'Save contact' : 'Save contact'}
                        </Button>
                    </SurfaceBody>
                </Surface>
            )}
        </Form>
    );
}
