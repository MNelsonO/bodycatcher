import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Kemet Tech sales desk" />
            <div className="relative flex min-h-svh flex-col items-center justify-center bg-background px-4 py-10 sm:px-6 md:py-16">
                {/* Solid blue accent bar */}
                <div
                    className="absolute inset-x-0 top-0 h-1.5 bg-primary"
                    aria-hidden
                />

                <main className="w-full max-w-xl overflow-hidden rounded-xl border border-border bg-card shadow-[var(--shadow-card)]">
                    <div className="space-y-4 px-8 pt-10 pb-8 md:px-10">
                        <p className="text-sm font-semibold text-primary">
                            Kemet Tech
                        </p>
                        <h1 className="text-3xl leading-tight font-semibold tracking-tight text-foreground md:text-4xl">
                            Sales desk
                        </h1>
                        <p className="max-w-[60ch] text-base leading-relaxed text-muted-foreground">
                            The lead book for Kemet Tech. WhatsApp and a
                            language model propose a summary, a score, and a
                            reply. Nothing is sent and no stage moves until you
                            approve it. Calendar is not connected.
                        </p>
                    </div>

                    <div className="border-t border-border bg-muted px-8 py-5 md:px-10">
                        {auth.user ? (
                            <Button asChild size="lg">
                                <Link href={dashboard()}>Open the desk</Link>
                            </Button>
                        ) : (
                            <Button asChild size="lg">
                                <Link href={login()}>Sign in</Link>
                            </Button>
                        )}
                    </div>
                </main>
            </div>
        </>
    );
}