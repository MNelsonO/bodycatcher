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
        <div className="relative flex min-h-svh flex-col items-center justify-center overflow-hidden p-6 md:p-10">
            <div
                className="pointer-events-none absolute inset-0"
                style={{
                    background:
                        'radial-gradient(circle at 20% 15%, #bfdbfe 0%, transparent 42%), radial-gradient(circle at 85% 90%, #dbeafe 0%, transparent 38%), linear-gradient(180deg, #eff6ff 0%, #f8fbff 45%, #ffffff 100%)',
                }}
                aria-hidden
            />
            <div
                className="pointer-events-none absolute inset-x-0 top-0 h-40 opacity-30"
                style={{
                    backgroundImage:
                        'repeating-linear-gradient(90deg, transparent, transparent 18px, rgba(29,78,216,0.08) 18px, rgba(29,78,216,0.08) 19px)',
                }}
                aria-hidden
            />

            <div className="relative w-full max-w-[400px]">
                <div className="overflow-hidden rounded-3xl bg-laundry-white shadow-[0_18px_50px_-28px_rgba(29,78,216,0.55)] ring-2 ring-primary/20">
                    <div className="bg-primary px-6 py-8 text-primary-foreground">
                        <Link href={home()} className="inline-flex">
                            <BrandLogo
                                inverted
                                markClassName="h-12 w-12"
                                className="gap-3"
                            />
                        </Link>
                        <p className="mt-4 text-sm text-white/85">
                            Fresh blues, clean desk — same feel as your laundry
                            floor.
                        </p>
                    </div>

                    <div className="space-y-5 px-6 py-7">
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
