import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/** Shared Laundry OS desk surface: white card, hairline border, solid shadow. */
export const surfaceClass =
    'overflow-hidden rounded-xl border border-border bg-card shadow-[var(--shadow-card)]';

type PageProps = {
    children: ReactNode;
    className?: string;
    /** Narrow forms / settings. Default is the desk canvas. */
    width?: 'desk' | 'wide' | 'content' | 'narrow' | 'form';
};

const widthClass: Record<NonNullable<PageProps['width']>, string> = {
    desk: 'max-w-[88rem]',
    wide: 'max-w-[88rem]',
    content: 'max-w-5xl',
    narrow: 'max-w-4xl',
    form: 'max-w-3xl',
};

/**
 * Standard authenticated page frame.
 * Matches Command centre: mist canvas, consistent padding and gaps.
 */
export function Page({ children, className, width = 'desk' }: PageProps) {
    return (
        <div
            className={cn(
                'mx-auto flex w-full flex-col gap-6 p-4 md:gap-8 md:p-8',
                widthClass[width],
                className,
            )}
        >
            {children}
        </div>
    );
}

type PageHeaderProps = {
    title: string;
    description?: ReactNode;
    /** Brand / section eyebrow, e.g. "Kemet Tech · Sales desk" */
    eyebrow?: string;
    actions?: ReactNode;
    className?: string;
};

export function PageHeader({
    title,
    description,
    eyebrow = 'Kemet Tech · Sales desk',
    actions,
    className,
}: PageHeaderProps) {
    return (
        <header
            className={cn(
                'flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between',
                className,
            )}
        >
            <div className="min-w-0">
                {eyebrow ? (
                    <p className="text-sm font-medium text-primary">{eyebrow}</p>
                ) : null}
                <h1
                    className={cn(
                        'font-semibold tracking-tight text-foreground',
                        eyebrow
                            ? 'mt-1.5 text-3xl md:text-4xl'
                            : 'text-2xl md:text-3xl',
                    )}
                >
                    {title}
                </h1>
                {description ? (
                    <p className="mt-1.5 max-w-[70ch] text-sm leading-relaxed text-muted-foreground">
                        {description}
                    </p>
                ) : null}
            </div>
            {actions ? (
                <div className="flex shrink-0 flex-wrap items-center gap-2">
                    {actions}
                </div>
            ) : null}
        </header>
    );
}

type SurfaceProps = {
    children: ReactNode;
    className?: string;
    id?: string;
    as?: 'section' | 'div' | 'article';
};

export function Surface({
    children,
    className,
    id,
    as: Tag = 'section',
}: SurfaceProps) {
    return (
        <Tag id={id} className={cn(surfaceClass, className)}>
            {children}
        </Tag>
    );
}

type SurfaceHeaderProps = {
    title: string;
    description?: ReactNode;
    action?: ReactNode;
    badge?: ReactNode;
    className?: string;
};

export function SurfaceHeader({
    title,
    description,
    action,
    badge,
    className,
}: SurfaceHeaderProps) {
    return (
        <div
            className={cn(
                'flex items-start justify-between gap-3 border-b border-border bg-card px-5 py-4',
                className,
            )}
        >
            <div className="min-w-0">
                <div className="flex items-center gap-2">
                    <h2 className="text-lg font-semibold tracking-tight text-foreground">
                        {title}
                    </h2>
                    {badge}
                </div>
                {description ? (
                    <p className="mt-0.5 max-w-[70ch] text-sm text-muted-foreground">
                        {description}
                    </p>
                ) : null}
            </div>
            {action}
        </div>
    );
}

type SurfaceBodyProps = {
    children: ReactNode;
    className?: string;
};

export function SurfaceBody({ children, className }: SurfaceBodyProps) {
    return (
        <div className={cn('bg-card px-5 py-5', className)}>{children}</div>
    );
}
