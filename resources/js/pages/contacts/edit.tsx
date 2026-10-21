import { Head } from '@inertiajs/react';
import {
    ContactForm,
    type ContactFormValues,
} from '@/components/crm/contact-form';
import { Page, PageHeader } from '@/components/crm/page-frame';
import type { ContactFormOptions } from '@/types/crm';

export default function EditContact({
    contact,
    options,
}: {
    contact: ContactFormValues;
    options: ContactFormOptions;
}) {
    return (
        <>
            <Head title={`Edit ${contact.full_name}`} />
            <Page width="form">
                <PageHeader title={`Edit ${contact.full_name}`} />
                <ContactForm contact={contact} options={options} />
            </Page>
        </>
    );
}

EditContact.layout = {
    breadcrumbs: [
        { title: 'Contacts', href: '/contacts' },
        { title: 'Edit', href: '/contacts' },
    ],
};
