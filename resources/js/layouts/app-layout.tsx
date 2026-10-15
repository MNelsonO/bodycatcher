import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import { writeRememberedUser } from '@/lib/remembered-user';
import type { BreadcrumbItem } from '@/types';

export default function AppLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    const { auth } = usePage().props;

    useEffect(() => {
        if (auth?.user?.email) {
            writeRememberedUser({
                email: auth.user.email,
                name: auth.user.name || '',
            });
        }
    }, [auth?.user?.email, auth?.user?.name]);

    return (
        <AppLayoutTemplate breadcrumbs={breadcrumbs}>
            {children}
        </AppLayoutTemplate>
    );
}
