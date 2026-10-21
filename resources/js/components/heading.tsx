import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export default function Heading({
    title,
    description,
    variant = 'default',
    eyebrow,
    actions,
}: {
    title: string;
    description?: ReactNode;
    variant?: 'default' | 'small';
    eyebrow?: string;
    actions?: ReactNode;
}) {
    if (variant === 'small') {
        return (
            <header className="space-y-0.5">
                <h2 className="text-base font-medium text-foreground">
                    {title}
                </h2>
                {description ? (
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                ) : null}
            </header>
        );
    }

    return (
        <header
            className={cn(
                'mb-8 flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between',
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
                <div className="flex shrink-0 flex-wrap gap-2">{actions}</div>
            ) : null}
        </header>
    );
}
