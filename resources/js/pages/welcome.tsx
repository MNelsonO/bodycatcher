import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Kemet Tech sales desk" />
            <main className="mx-auto flex min-h-svh max-w-lg flex-col justify-center gap-8 px-6 py-16">
                <div className="space-y-3">
                    <p className="text-sm font-medium tracking-wide text-muted-foreground">
                        Kemet Tech
                    </p>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Sales desk
                    </h1>
                    <p className="text-sm leading-6 text-muted-foreground">
                        The lead book for Kemet Tech. WhatsApp and a language
                        model propose a summary, a score, and a reply. Nothing
                        is sent and no stage moves until you approve it.
                        Calendar is not connected.
                    </p>
                </div>
                <div>
                    {auth.user ? (
                        <Button asChild>
                            <Link href={dashboard()}>Open the desk</Link>
                        </Button>
                    ) : (
                        <Button asChild>
                            <Link href={login()}>Sign in</Link>
                        </Button>
                    )}
                </div>
            </main>
        </>
    );
}
