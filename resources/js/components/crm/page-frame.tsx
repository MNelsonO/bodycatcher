import { usePage } from '@inertiajs/react';
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
                'mx-auto flex w-full flex-col gap-5 p-4 md:gap-6 md:p-6 xl:p-8',
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
    /** Small label above the title. Defaults to the sidebar section. */
    eyebrow?: string;
    actions?: ReactNode;
    className?: string;
};

const SECTIONS: Array<[string, string]> = [
    ['/leads', 'Work · Leads'],
    ['/contacts', 'Work · Contacts'],
    ['/calendar', 'Work · Calendar'],
    ['/follow-ups', 'Work · Follow-ups'],
    ['/inbox', 'Work · Inbox'],
    ['/invoices', 'Money · Invoices'],
    ['/billing', 'Money · Billing'],
    ['/expenses', 'Money · Expenses'],
    ['/money', 'Money · Accounts'],
    ['/reports', 'Money · Reports'],
    ['/whatsapp', 'Setup · WhatsApp'],
    ['/ai', 'Setup · Intelligence'],
    ['/integrations', 'Setup · Integrations'],
    ['/products', 'Setup · Products'],
    ['/pipelines', 'Setup · Pipelines'],
    ['/users', 'Setup · Users'],
];

function sectionFor(url: string): string {
    const path = url.split('?')[0];
    const match = SECTIONS.find(([prefix]) => path.startsWith(prefix));

    return match ? match[1] : '';
}

export function PageHeader({
    title,
    description,
    eyebrow,
    actions,
    className,
}: PageHeaderProps) {
    const { url } = usePage();
    const label = eyebrow ?? sectionFor(url);

    return (
        <header
            className={cn(
                'flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between',
                className,
            )}
        >
            <div className="min-w-0">
                {label ? (
                    <p className="text-xs font-semibold tracking-wider text-primary uppercase">
                        {label}
                    </p>
                ) : null}
                <h1
                    className={cn(
                        'truncate text-2xl font-semibold tracking-tight text-foreground md:text-[1.75rem]',
                        label ? 'mt-1' : '',
                    )}
                >
                    {title}
                </h1>
                {description ? (
                    <p className="mt-1 max-w-[70ch] text-sm leading-relaxed text-muted-foreground">
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
                'flex items-center justify-between gap-3 border-b border-border bg-card px-5 py-3.5',
                className,
            )}
        >
            <div className="min-w-0">
                <div className="flex items-center gap-2">
                    <h2 className="text-base font-semibold tracking-tight text-foreground">
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
    return <div className={cn('bg-card px-5 py-5', className)}>{children}</div>;
}
