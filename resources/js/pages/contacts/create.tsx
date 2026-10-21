import { Head } from '@inertiajs/react';
import { ContactForm } from '@/components/crm/contact-form';
import { Page, PageHeader } from '@/components/crm/page-frame';
import type { ContactFormOptions } from '@/types/crm';

export default function CreateContact({
    options,
}: {
    options: ContactFormOptions;
}) {
    return (
        <>
            <Head title="Add contact" />
            <Page width="form">
                <PageHeader
                    title="Add contact"
                    description="If the phone already exists, that contact is updated. Same name alone is not a match."
                />
                <ContactForm contact={null} options={options} />
            </Page>
        </>
    );
}

CreateContact.layout = {
    breadcrumbs: [
        { title: 'Contacts', href: '/contacts' },
        { title: 'Add', href: '/contacts/create' },
    ],
};
