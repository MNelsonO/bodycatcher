import { Form, Head, Link } from '@inertiajs/react';
import { SelectField, TextField } from '@/components/crm/fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { ContactSummary, Option, Paginator } from '@/types/crm';

export default function ContactsIndex({
    contacts,
    filters,
    country_options,
}: {
    contacts: Paginator<ContactSummary>;
    filters: { q: string; country: string };
    country_options: Option[];
}) {
    return (
        <>
            <Head title="Contacts" />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Contacts
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Classify clients by country for multi-market ads.
                            Desk notes travel with the AI conversation area.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/contacts/create">Add contact</Link>
                    </Button>
                </div>
                <Form
                    action="/contacts"
                    method="get"
                    className="grid max-w-3xl gap-3 sm:grid-cols-[1fr_12rem_auto]"
                >
                    {({ processing }) => (
                        <>
                            <TextField
                                name="q"
                                label="Search"
                                defaultValue={filters.q}
                            />
                            <SelectField
                                name="country"
                                label="Country"
                                options={[
                                    { value: '__all__', label: 'All countries' },
                                    ...country_options,
                                ]}
                                defaultValue={
                                    filters.country !== ''
                                        ? filters.country
                                        : '__all__'
                                }
                            />
                            <div className="flex items-end">
                                <Button type="submit" disabled={processing}>
                                    Filter
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
                {contacts.data.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-8 text-sm text-muted-foreground">
                        No contacts yet. Add the first prospect, or load the
                        sample book with the demo seeder.
                    </div>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {contacts.data.map((contact) => (
                            <li key={contact.id}>
                                <Link
                                    href={`/contacts/${contact.id}`}
                                    className="flex flex-col gap-1 px-4 py-3 hover:bg-muted/40 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <span>
                                        <span className="font-medium">
                                            {contact.full_name}
                                        </span>
                                        <span className="block text-sm text-muted-foreground">
                                            {contact.business_name ??
                                                'No business name'}{' '}
                                            · {contact.primary_phone}
                                            {contact.country
                                                ? ` · ${contact.country}`
                                                : ''}
                                            {contact.conversation_area
                                                ? ` · ${contact.conversation_area}`
                                                : ''}
                                        </span>
                                    </span>
                                    <span className="flex flex-wrap gap-2">
                                        {contact.country_code ? (
                                            <Badge variant="secondary">
                                                {contact.country_code}
                                            </Badge>
                                        ) : null}
                                        <Badge variant="outline">
                                            {contact.consent_status.replace(
                                                '_',
                                                ' ',
                                            )}
                                        </Badge>
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

ContactsIndex.layout = {
    breadcrumbs: [{ title: 'Contacts', href: '/contacts' }],
};
