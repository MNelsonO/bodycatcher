import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import BrandLogo from '@/components/brand-logo';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';

export default function AuthCardLayout({
    children,
    title,
    description,
}: PropsWithChildren<{
    name?: string;
    title?: string;
    description?: string;
}>) {
    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
            <div
                className="absolute inset-x-0 top-0 h-1.5 bg-primary"
                aria-hidden
            />
            <div className="flex w-full max-w-md flex-col gap-6">
                <Link
                    href={home()}
                    className="flex items-center gap-2 self-center font-medium"
                >
                    <BrandLogo markClassName="h-9 w-9" className="gap-2" />
                </Link>

                <Card>
                    <CardHeader className="border-b border-border px-10 pt-8 pb-6 text-center">
                        <CardTitle className="text-2xl font-semibold tracking-tight">
                            {title}
                        </CardTitle>
                        {description ? (
                            <CardDescription>{description}</CardDescription>
                        ) : null}
                    </CardHeader>
                    <CardContent className="px-10 py-8">{children}</CardContent>
                </Card>
            </div>
        </div>
    );
}
