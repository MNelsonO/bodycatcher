import { Head } from '@inertiajs/react';
import {
    ContactForm,
    type ContactFormValues,
} from '@/components/crm/contact-form';
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
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <h1 className="text-xl font-semibold tracking-tight">
                    Edit {contact.full_name}
                </h1>
                <ContactForm contact={contact} options={options} />
            </div>
        </>
    );
}

EditContact.layout = {
    breadcrumbs: [
        { title: 'Contacts', href: '/contacts' },
        { title: 'Edit', href: '/contacts' },
    ],
};
