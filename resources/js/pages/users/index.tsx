import { Form, Head } from '@inertiajs/react';
import { SelectField, TextField } from '@/components/crm/fields';
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

const surface =
    'overflow-hidden rounded-xl border border-border bg-card shadow-[var(--shadow-card)]';

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
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:gap-8 md:p-8">
                {/* Page header */}
                <div className="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                            Users
                        </h1>
                        <p className="mt-2 max-w-[60ch] text-sm leading-relaxed text-muted-foreground">
                            Owners manage the catalogue and pipelines. Sales
                            works the lead book.
                        </p>
                    </div>
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
                                    They can sign in immediately. Public signup
                                    is off.
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
                                            error={errors.password_confirmation}
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
                </div>

                {/* User list */}
                <section className={surface}>
                    <div className="flex items-center justify-between border-b border-border bg-secondary px-5 py-3">
                        <h2 className="text-sm font-semibold text-secondary-foreground">
                            Desk team
                        </h2>
                        <span className="text-sm tabular-nums text-secondary-foreground">
                            {users.length}{' '}
                            {users.length === 1 ? 'user' : 'users'}
                        </span>
                    </div>
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
                </section>
            </div>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [{ title: 'Users', href: '/users' }],
};