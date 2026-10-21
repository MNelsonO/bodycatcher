import { Form, Head } from '@inertiajs/react';
import { CheckCircle2, ShieldCheck } from 'lucide-react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    firstNameFor,
    initialsFor,
    readRememberedUser,
    writeRememberedUser,
    type RememberedUser,
} from '@/lib/remembered-user';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    const [hydrated, setHydrated] = useState(false);
    const [remembered, setRemembered] = useState<RememberedUser | null>(null);
    const [switchAccount, setSwitchAccount] = useState(false);
    const [email, setEmail] = useState('');

    useEffect(() => {
        const user = readRememberedUser();
        setRemembered(user);
        if (user) {
            setEmail(user.email);
        }
        setHydrated(true);
    }, []);

    const known = remembered && !switchAccount ? remembered : null;
    const activeEmail = known ? known.email : email.trim();

    return (
        <>
            <Head title="Log in" />

            <div className="flex flex-col gap-6">
                {status ? (
                    <div
                        role="status"
                        className="flex items-start gap-2.5 rounded-lg border border-success bg-success-soft px-4 py-3 text-sm font-medium text-success"
                    >
                        <CheckCircle2 className="mt-0.5 size-4 shrink-0" />
                        <span>{status}</span>
                    </div>
                ) : null}

                {!hydrated ? <LoginSkeleton /> : null}

                {hydrated && known ? (
                    <div className="flex items-center gap-3 rounded-xl border border-border bg-secondary p-4">
                        <div
                            aria-hidden
                            className="flex size-12 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground"
                        >
                            {initialsFor(known.name, known.email)}
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-base font-semibold text-foreground">
                                Welcome back, {firstNameFor(known)}
                            </p>
                            <p className="truncate text-sm text-muted-foreground">
                                {known.email}
                            </p>
                        </div>
                        <button
                            type="button"
                            className="shrink-0 rounded-sm text-sm font-medium text-primary hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            onClick={() => {
                                setSwitchAccount(true);
                                setEmail('');
                            }}
                        >
                            Switch
                        </button>
                    </div>
                ) : null}

                {hydrated ? (
                    <Form
                        {...store.form()}
                        resetOnSuccess={['password']}
                        className="flex flex-col gap-5"
                        onSuccess={() => {
                            // Only remember the account once it has actually
                            // authenticated, never on a failed attempt.
                            if (activeEmail) {
                                writeRememberedUser({
                                    email: activeEmail,
                                    name:
                                        known && known.email === activeEmail
                                            ? known.name || ''
                                            : '',
                                });
                            }
                        }}
                    >
                        {({ processing, errors }) => (
                            <>
                                {known ? (
                                    <input
                                        type="hidden"
                                        name="email"
                                        value={known.email}
                                    />
                                ) : (
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor="email"
                                            className="text-sm font-medium"
                                        >
                                            Email address
                                        </Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            name="email"
                                            required
                                            autoFocus
                                            autoComplete="username"
                                            inputMode="email"
                                            autoCapitalize="none"
                                            spellCheck={false}
                                            placeholder="you@company.com"
                                            className="h-11 bg-card"
                                            aria-invalid={
                                                errors.email ? true : undefined
                                            }
                                            value={email}
                                            onChange={(event) =>
                                                setEmail(event.target.value)
                                            }
                                        />
                                        <InputError message={errors.email} />
                                    </div>
                                )}

                                <div className="grid gap-2">
                                    <Label
                                        htmlFor="password"
                                        className="text-sm font-medium"
                                    >
                                        Password
                                    </Label>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        required
                                        autoFocus={Boolean(known)}
                                        autoComplete="current-password"
                                        placeholder="Enter your password"
                                        className="h-11 bg-card"
                                        aria-invalid={
                                            errors.password ? true : undefined
                                        }
                                    />
                                    <InputError message={errors.password} />
                                    {/* Known-user flow has no email field, so
                                        surface email errors here too. */}
                                    {known ? (
                                        <InputError message={errors.email} />
                                    ) : null}
                                </div>

                                <div className="flex items-center justify-between gap-3">
                                    <div className="flex items-center gap-2.5">
                                        <Checkbox
                                            id="remember"
                                            name="remember"
                                            defaultChecked
                                        />
                                        <Label
                                            htmlFor="remember"
                                            className="text-sm font-normal text-muted-foreground"
                                        >
                                            Keep me signed in
                                        </Label>
                                    </div>
                                    {canResetPassword ? (
                                        <TextLink
                                            href={request()}
                                            className="text-sm font-medium text-primary"
                                        >
                                            Forgot password?
                                        </TextLink>
                                    ) : null}
                                </div>

                                <Button
                                    type="submit"
                                    className="h-11 w-full text-base font-semibold"
                                    disabled={processing}
                                    data-test="login-button"
                                >
                                    {processing ? <Spinner /> : null}
                                    {processing
                                        ? 'Signing in…'
                                        : known
                                          ? `Continue as ${firstNameFor(known)}`
                                          : 'Sign in'}
                                </Button>
                            </>
                        )}
                    </Form>
                ) : null}

                <div className="flex items-start gap-2.5 border-t border-border pt-5 text-xs leading-relaxed text-muted-foreground">
                    <ShieldCheck className="mt-0.5 size-4 shrink-0" />
                    <p>
                        Your email is remembered on this device only to speed up
                        sign-in. Your password is never stored. Trouble getting
                        in? Ask your administrator to reset your access.
                    </p>
                </div>
            </div>
        </>
    );
}

function LoginSkeleton() {
    return (
        <div className="flex flex-col gap-5" aria-hidden>
            <div className="grid gap-2">
                <div className="h-4 w-24 animate-pulse rounded bg-muted" />
                <div className="h-11 animate-pulse rounded-md bg-muted" />
            </div>
            <div className="grid gap-2">
                <div className="h-4 w-20 animate-pulse rounded bg-muted" />
                <div className="h-11 animate-pulse rounded-md bg-muted" />
            </div>
            <div className="h-11 animate-pulse rounded-md bg-muted" />
        </div>
    );
}

Login.layout = {
    title: 'Sign in to Kemet Tech',
    description: 'Use your desk account to open the sales desk.',
};