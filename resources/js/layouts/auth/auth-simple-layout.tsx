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
        <div className="relative flex min-h-svh flex-col items-center justify-center bg-background px-4 py-10 sm:px-6 md:py-14">
            {/* Solid blue accent bar */}
            <div
                className="absolute inset-x-0 top-0 h-1.5 bg-primary"
                aria-hidden
            />

            <div className="w-full max-w-[26rem]">
                {/* Solid white card */}
                <div className="overflow-hidden rounded-xl border border-border bg-card shadow-[var(--shadow-card)]">
                    {/* Header */}
                    <div className="border-b border-border bg-card px-8 pt-8 pb-7">
                        <Link
                            href={home()}
                            className="inline-flex rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <BrandLogo
                                markClassName="h-10 w-10"
                                className="gap-3"
                            />
                        </Link>

                        {(title || description) && (
                            <div className="mt-7 space-y-2">
                                {title ? (
                                    <h1 className="text-2xl leading-tight font-semibold tracking-tight text-foreground">
                                        {title}
                                    </h1>
                                ) : null}
                                {description ? (
                                    <p className="text-sm leading-relaxed text-muted-foreground">
                                        {description}
                                    </p>
                                ) : null}
                            </div>
                        )}
                    </div>

                    {/* Form area */}
                    <div className="bg-card px-8 py-8">{children}</div>
                </div>

                {/* Footer note */}
                <p className="mt-6 text-center text-xs text-muted-foreground">
                    Protected by end-to-end encryption
                </p>
            </div>
        </div>
    );
}