import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <Head title="Forgot password" />

            {status ? (
                <div className="rounded-md bg-success-soft px-3 py-2 text-sm font-medium text-success">
                    {status}
                </div>
            ) : null}

            <Form {...email.form()} className="space-y-5">
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autoComplete="email"
                                autoFocus
                                placeholder="you@company.com"
                            />
                            <InputError message={errors.email} />
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                            data-test="email-password-reset-link-button"
                        >
                            {processing && <Spinner />}
                            Email reset link
                        </Button>
                    </>
                )}
            </Form>

            <p className="text-center text-sm text-muted-foreground">
                Remembered it?{' '}
                <TextLink href={login()} className="text-primary">
                    Back to sign in
                </TextLink>
            </p>
        </>
    );
}

ForgotPassword.layout = {
    title: 'Forgot password',
    description: 'We’ll email a reset link for your desk account.',
};
