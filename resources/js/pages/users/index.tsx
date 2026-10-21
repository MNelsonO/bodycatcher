import { Form, Head } from '@inertiajs/react';
import { SelectField, TextField } from '@/components/crm/fields';
import {
    Page,
    PageHeader,
    Surface,
    SurfaceHeader,
} from '@/components/crm/page-frame';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { Option } from '@/types/crm';

type DeskUser = {
    id: number;
    name: string;
    email: string;
    role: string;
};

function initials(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);
    const letters = parts.slice(0, 2).map((part) => part[0]);

    return (letters.join('') || '?').toUpperCase();
}

export default function UsersIndex({
    users,
    roles,
}: {
    users: DeskUser[];
    roles: Option[];
}) {
    return (
        <>
            <Head title="Users" />
            <Page width="narrow">
                <PageHeader
                    title="Users"
                    description="Owners manage the catalogue and pipelines. Sales works the lead book."
                    actions={
                        <Dialog>
                            <DialogTrigger asChild>
                                <Button size="lg">Add user</Button>
                            </DialogTrigger>
                            <DialogContent className="border border-border bg-card shadow-[var(--shadow-pop)]">
                                <DialogHeader>
                                    <DialogTitle className="text-xl font-semibold tracking-tight">
                                        Add user
                                    </DialogTitle>
                                    <DialogDescription className="leading-relaxed">
                                        They can sign in immediately. Public
                                        signup is off.
                                    </DialogDescription>
                                </DialogHeader>
                                <Form
                                    action="/users"
                                    method="post"
                                    className="grid gap-4"
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <TextField
                                                name="name"
                                                label="Name"
                                                required
                                                error={errors.name}
                                            />
                                            <TextField
                                                name="email"
                                                label="Email"
                                                type="email"
                                                required
                                                error={errors.email}
                                            />
                                            <TextField
                                                name="password"
                                                label="Password"
                                                type="password"
                                                required
                                                error={errors.password}
                                            />
                                            <TextField
                                                name="password_confirmation"
                                                label="Confirm password"
                                                type="password"
                                                required
                                                error={
                                                    errors.password_confirmation
                                                }
                                            />
                                            <SelectField
                                                name="role"
                                                label="Role"
                                                required
                                                options={roles}
                                                defaultValue="sales"
                                                error={errors.role}
                                            />
                                            <Button
                                                type="submit"
                                                size="lg"
                                                disabled={processing}
                                            >
                                                Add user
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>
                    }
                />

                <Surface>
                    <SurfaceHeader
                        title="Desk team"
                        badge={
                            <span className="text-sm tabular-nums text-muted-foreground">
                                {users.length}{' '}
                                {users.length === 1 ? 'user' : 'users'}
                            </span>
                        }
                    />
                    <ul className="divide-y divide-border">
                        {users.map((user) => (
                            <li
                                key={user.id}
                                className="flex flex-col gap-4 bg-card px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div className="flex min-w-0 items-center gap-3">
                                    <div
                                        className="flex size-11 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground"
                                        aria-hidden
                                    >
                                        {initials(user.name)}
                                    </div>
                                    <div className="min-w-0">
                                        <p className="truncate font-semibold text-foreground">
                                            {user.name}
                                        </p>
                                        <p className="truncate text-sm text-muted-foreground">
                                            {user.email}
                                        </p>
                                        <span className="mt-1.5 inline-flex rounded-full bg-secondary px-2.5 py-0.5 text-xs font-semibold text-secondary-foreground">
                                            {user.role}
                                        </span>
                                    </div>
                                </div>
                                <Form
                                    action={`/users/${user.id}`}
                                    method="put"
                                    className="flex flex-wrap items-end gap-3"
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <SelectField
                                                name="role"
                                                label="Role"
                                                options={roles}
                                                defaultValue={user.role}
                                                error={errors.role}
                                            />
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                disabled={processing}
                                            >
                                                Save role
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </li>
                        ))}
                    </ul>
                </Surface>
            </Page>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [{ title: 'Users', href: '/users' }],
};
