import { Form, Head, Link } from '@inertiajs/react';
import { SelectField, TextField } from '@/components/crm/fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { ContactSummary, Option, Paginator } from '@/types/crm';

export default function ContactsIndex({
    contacts,
    filters,
    country_options,
    product_options,
    spoke_options,
    scheduled_options,
}: {
    contacts: Paginator<ContactSummary>;
    filters: {
        q: string;
        country: string;
        product_id: string;
        spoke: string;
        scheduled: string;
    };
    country_options: Option[];
    product_options: Option[];
    spoke_options: Option[];
    scheduled_options: Option[];
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
                            Name, system they want, intent, and when you last
                            spoke — filter by month, week, and scheduled talks.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/contacts/create">Add contact</Link>
                    </Button>
                </div>

                <Form
                    action="/contacts"
                    method="get"
                    className="grid max-w-5xl gap-3 md:grid-cols-2 xl:grid-cols-3"
                >
                    {({ processing }) => (
                        <>
                            <TextField
                                name="q"
                                label="Search name, intent, system"
                                defaultValue={filters.q}
                            />
                            <SelectField
                                name="product_id"
                                label="System interested in"
                                options={[
                                    {
                                        value: '__all__',
                                        label: 'All systems',
                                    },
                                    ...product_options,
                                ]}
                                defaultValue={
                                    filters.product_id !== ''
                                        ? filters.product_id
                                        : '__all__'
                                }
                            />
                            <SelectField
                                name="country"
                                label="Country"
                                options={[
                                    {
                                        value: '__all__',
                                        label: 'All countries',
                                    },
                                    ...country_options,
                                ]}
                                defaultValue={
                                    filters.country !== ''
                                        ? filters.country
                                        : '__all__'
                                }
                            />
                            <SelectField
                                name="spoke"
                                label="Last conversation"
                                options={spoke_options}
                                defaultValue={
                                    filters.spoke !== ''
                                        ? filters.spoke
                                        : '__all__'
                                }
                            />
                            <SelectField
                                name="scheduled"
                                label="Scheduled talk"
                                options={scheduled_options}
                                defaultValue={
                                    filters.scheduled !== ''
                                        ? filters.scheduled
                                        : '__all__'
                                }
                            />
                            <div className="flex items-end gap-2">
                                <Button type="submit" disabled={processing}>
                                    Filter
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href="/contacts">Clear</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                {contacts.data.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-8 text-sm text-muted-foreground">
                        No contacts match these filters. Widen the date range or
                        clear filters.
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[720px] text-left text-sm">
                            <thead className="border-b bg-muted/40 text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Name
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        System interested in
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Intent
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Last spoke
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Next talk
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {contacts.data.map((contact) => (
                                    <tr
                                        key={contact.id}
                                        className="hover:bg-muted/30"
                                    >
                                        <td className="px-4 py-3 align-top">
                                            <Link
                                                href={`/contacts/${contact.id}`}
                                                className="font-medium hover:underline"
                                            >
                                                {contact.full_name}
                                            </Link>
                                            <div className="mt-1 text-muted-foreground">
                                                {contact.business_name ??
                                                    'No business'}{' '}
                                                · {contact.primary_phone}
                                            </div>
                                            <div className="mt-1 flex flex-wrap gap-1">
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
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 align-top">
                                            {contact.interested_system ? (
                                                <div className="space-y-1">
                                                    <span>
                                                        {
                                                            contact.interested_system
                                                        }
                                                    </span>
                                                    {(contact.interested_systems
                                                        ?.length ?? 0) > 1 ? (
                                                        <p className="text-xs text-muted-foreground">
                                                            +
                                                            {(contact
                                                                .interested_systems
                                                                ?.length ?? 1) -
                                                                1}{' '}
                                                            more
                                                        </p>
                                                    ) : null}
                                                    {contact.stage_name ? (
                                                        <Badge variant="outline">
                                                            {contact.stage_name}
                                                        </Badge>
                                                    ) : null}
                                                </div>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    No open lead
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 align-top">
                                            <p className="line-clamp-3 max-w-xs">
                                                {contact.intent ?? (
                                                    <span className="text-muted-foreground">
                                                        Not captured
                                                    </span>
                                                )}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3 align-top tabular-nums text-muted-foreground">
                                            {contact.last_spoke_at ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 align-top tabular-nums text-muted-foreground">
                                            {contact.next_talk_at ?? '—'}
                                            {contact.open_lead_id ? (
                                                <div className="mt-1">
                                                    <Link
                                                        href={`/leads/${contact.open_lead_id}`}
                                                        className="text-xs underline"
                                                    >
                                                        Open lead
                                                    </Link>
                                                </div>
                                            ) : null}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

ContactsIndex.layout = {
    breadcrumbs: [{ title: 'Contacts', href: '/contacts' }],
};
