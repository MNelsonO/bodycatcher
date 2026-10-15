import { Form, Head } from '@inertiajs/react';
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

    const showingKnown = Boolean(remembered && !switchAccount);
    const activeEmail = showingKnown ? remembered!.email : email;

    return (
        <>
            <Head title="Log in" />

            <div className="flex flex-col gap-6">
                {status ? (
                    <div
                        role="status"
                        className="rounded-lg border border-success bg-success-soft px-4 py-3 text-sm font-medium text-success"
                    >
                        {status}
                    </div>
                ) : null}

                {hydrated && showingKnown ? (
                    <div className="rounded-xl border border-border bg-secondary p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex size-12 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground">
                                {initialsFor(
                                    remembered!.name,
                                    remembered!.email,
                                )}
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-base font-semibold text-foreground">
                                    Welcome back, {firstNameFor(remembered!)}
                                </p>
                                <p className="truncate text-sm text-muted-foreground">
                                    {remembered!.email}
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            className="mt-3 rounded-sm text-sm font-medium text-primary hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            onClick={() => {
                                setSwitchAccount(true);
                                setEmail(remembered!.email);
                            }}
                        >
                            Not you? Use another account
                        </button>
                    </div>
                ) : null}

                {hydrated && !showingKnown ? (
                    <div className="rounded-xl border border-border bg-secondary px-4 py-3 text-sm leading-relaxed text-secondary-foreground">
                        Sign in once and we’ll remember your desk account on
                        this device — next time you only enter the password.
                    </div>
                ) : null}

                {hydrated ? (
                    <Form
                        {...store.form()}
                        resetOnSuccess={['password']}
                        className="flex flex-col gap-5"
                        onSuccess={() => {
                            writeRememberedUser({
                                email: activeEmail,
                                name: remembered?.name || '',
                            });
                        }}
                    >
                        {({ processing, errors }) => (
                            <>
                                {showingKnown ? (
                                    <input
                                        type="hidden"
                                        name="email"
                                        value={remembered!.email}
                                    />
                                ) : (
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor="email"
                                            className="text-sm font-medium"
                                        >
                                            Email
                                        </Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            name="email"
                                            required
                                            autoFocus
                                            tabIndex={1}
                                            autoComplete="username"
                                            placeholder="you@company.com"
                                            className="h-11 bg-card"
                                            value={email}
                                            onChange={(event) =>
                                                setEmail(event.target.value)
                                            }
                                        />
                                        <InputError message={errors.email} />
                                    </div>
                                )}

                                <div className="grid gap-2">
                                    <div className="flex items-center justify-between gap-3">
                                        <Label
                                            htmlFor="password"
                                            className="text-sm font-medium"
                                        >
                                            Password
                                        </Label>
                                        {canResetPassword ? (
                                            <TextLink
                                                href={request()}
                                                className="text-sm font-medium text-primary"
                                                tabIndex={5}
                                            >
                                                Forgot password?
                                            </TextLink>
                                        ) : null}
                                    </div>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        required
                                        autoFocus={showingKnown}
                                        tabIndex={2}
                                        autoComplete="current-password"
                                        placeholder="Enter your password"
                                        className="h-11 bg-card"
                                    />
                                    <InputError message={errors.password} />
                                </div>

                                <div className="flex items-center gap-2.5">
                                    <Checkbox
                                        id="remember"
                                        name="remember"
                                        defaultChecked
                                        tabIndex={3}
                                    />
                                    <Label
                                        htmlFor="remember"
                                        className="text-sm font-normal text-muted-foreground"
                                    >
                                        Keep me signed in on this device
                                    </Label>
                                </div>

                                <Button
                                    type="submit"
                                    className="h-11 w-full text-base font-semibold"
                                    tabIndex={4}
                                    disabled={processing}
                                    data-test="login-button"
                                    onClick={() => {
                                        if (activeEmail) {
                                            writeRememberedUser({
                                                email: activeEmail,
                                                name: remembered?.name || '',
                                            });
                                        }
                                    }}
                                >
                                    {processing && <Spinner />}
                                    {showingKnown
                                        ? `Continue as ${firstNameFor(remembered!)}`
                                        : 'Sign in'}
                                </Button>
                            </>
                        )}
                    </Form>
                ) : (
                    <div className="h-48 animate-pulse rounded-xl bg-muted" />
                )}
            </div>
        </>
    );
}

Login.layout = {
    title: 'Sign in',
    description: 'Use your desk account to open the sales desk.',
};