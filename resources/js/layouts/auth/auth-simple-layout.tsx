import { Link } from '@inertiajs/react';
import BrandLogo from '@/components/brand-logo';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center bg-laundry-mist p-6 md:p-10">
            <div className="w-full max-w-[380px]">
                <div className="overflow-hidden bg-laundry-white shadow-[0_0_0_2px_var(--laundry-blue)]">
                    <div className="bg-primary px-6 py-7 text-primary-foreground">
                        <Link href={home()} className="inline-flex">
                            <BrandLogo
                                inverted
                                markClassName="h-11 w-11"
                                className="gap-3"
                            />
                        </Link>
                    </div>

                    <div className="space-y-6 px-6 py-7">
                        {(title || description) && (
                            <div className="space-y-1.5">
                                {title ? (
                                    <h1 className="text-xl font-semibold tracking-tight text-primary">
                                        {title}
                                    </h1>
                                ) : null}
                                {description ? (
                                    <p className="text-sm text-muted-foreground">
                                        {description}
                                    </p>
                                ) : null}
                            </div>
                        )}
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}
