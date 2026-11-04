import { Link, usePage } from '@inertiajs/react';
import { CalendarDays, MessageCircle, Sparkles } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

const HIGHLIGHTS = [
    {
        icon: MessageCircle,
        title: 'Every WhatsApp chat, live',
        text: 'Names, full history and unread counts in one inbox.',
    },
    {
        icon: CalendarDays,
        title: 'Calls, demos and visits',
        text: 'Book customers, schedule messages, never miss a follow-up.',
    },
    {
        icon: Sparkles,
        title: 'Claude reads the lead',
        text: 'A summary, a score and a draft reply. You decide what is sent.',
    },
];

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="grid min-h-dvh lg:grid-cols-[1.1fr_1fr]">
            <div className="relative hidden flex-col justify-between overflow-hidden bg-zinc-950 p-10 text-white lg:flex">
                <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(16,185,129,0.35),transparent_55%),radial-gradient(ellipse_at_bottom_right,rgba(14,165,233,0.25),transparent_50%)]" />
                <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_1px_1px,rgba(255,255,255,0.08)_1px,transparent_0)] [background-size:24px_24px]" />
                <Link
                    href={home()}
                    className="relative z-10 flex items-center gap-2 text-lg font-medium"
                >
                    <span className="flex size-9 items-center justify-center rounded-lg bg-white/10 ring-1 ring-white/20">
                        <AppLogoIcon className="size-5 fill-current text-white" />
                    </span>
                    {name}
                </Link>

                <div className="relative z-10 max-w-md">
                    <h2 className="text-3xl leading-tight font-semibold tracking-tight">
                        The sales desk that keeps every conversation moving.
                    </h2>
                    <ul className="mt-8 flex flex-col gap-5">
                        {HIGHLIGHTS.map((item) => (
                            <li key={item.title} className="flex gap-3">
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-400/30">
                                    <item.icon className="size-4" />
                                </span>
                                <span>
                                    <span className="block font-medium">
                                        {item.title}
                                    </span>
                                    <span className="block text-sm text-white/60">
                                        {item.text}
                                    </span>
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>

                <p className="relative z-10 text-xs text-white/40">
                    Kemet Tech · Nairobi
                </p>
            </div>

            <div className="flex items-center justify-center bg-background px-6 py-12 sm:px-10">
                <div className="flex w-full max-w-sm flex-col gap-8">
                    <Link
                        href={home()}
                        className="flex items-center gap-2 font-medium lg:hidden"
                    >
                        <span className="flex size-9 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                            <AppLogoIcon className="size-5 fill-current" />
                        </span>
                        {name}
                    </Link>
                    <div className="flex flex-col gap-2">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {title}
                        </h1>
                        {description ? (
                            <p className="text-sm text-balance text-muted-foreground">
                                {description}
                            </p>
                        ) : null}
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
