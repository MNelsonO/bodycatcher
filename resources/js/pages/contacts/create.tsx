import { Head } from '@inertiajs/react';
import { ContactForm } from '@/components/crm/contact-form';
import type { ContactFormOptions } from '@/types/crm';

export default function CreateContact({
    options,
}: {
    options: ContactFormOptions;
}) {
    return (
        <>
            <Head title="Add contact" />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Add contact
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        If the phone already exists, that contact is updated.
                        Same name alone is not a match.
                    </p>
                </div>
                <ContactForm contact={null} options={options} />
            </div>
        </>
    );
}

CreateContact.layout = {
    breadcrumbs: [
        { title: 'Contacts', href: '/contacts' },
        { title: 'Add', href: '/contacts/create' },
    ],
};
