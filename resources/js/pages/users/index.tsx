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
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Users
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Owners manage the catalogue and pipelines. Sales
                            works the lead book.
                        </p>
                    </div>
                    <Dialog>
                        <DialogTrigger asChild>
                            <Button>Add user</Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Add user</DialogTitle>
                                <DialogDescription>
                                    They can sign in immediately. Public signup
                                    is off.
                                </DialogDescription>
                            </DialogHeader>
                            <Form
                                action="/users"
                                method="post"
                                className="grid gap-3"
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
                <ul className="divide-y rounded-lg border">
                    {users.map((user) => (
                        <li
                            key={user.id}
                            className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <div className="font-medium">{user.name}</div>
                                <div className="text-sm text-muted-foreground">
                                    {user.email} · {user.role}
                                </div>
                            </div>
                            <Form
                                action={`/users/${user.id}`}
                                method="put"
                                className="flex flex-wrap items-end gap-2"
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
            </div>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [{ title: 'Users', href: '/users' }],
};
