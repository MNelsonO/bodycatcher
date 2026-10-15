import { Head } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { login } from '@/routes';

export default function Register() {
    return (
        <>
            <Head title="Sign up closed" />
            <div className="space-y-4 text-sm text-muted-foreground">
                <p>
                    Kemet Tech does not take public signups. Ask the owner for
                    an account.
                </p>
                <TextLink href={login()}>Back to sign in</TextLink>
            </div>
        </>
    );
}

Register.layout = {
    title: 'Sign up is closed',
    description: 'The owner adds people to the sales desk.',
};
