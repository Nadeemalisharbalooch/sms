import { Head, useForm } from '@inertiajs/react';
import { Database } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function Login({ twoFactorPending = false, status }) {
    const { data, setData, post, processing, errors } = useForm({ email: '', password: '', remember: true });
    const twoFactorForm = useForm({ otp: '' });

    function submit(event) {
        event.preventDefault();
        post(route('login.store'));
    }

    function verifyTwoFactor(event) {
        event.preventDefault();
        twoFactorForm.post(route('login.verify-2fa'));
    }

    return (
        <>
            <Head title="Log in" />
            <main className="flex min-h-screen items-center justify-center bg-muted/40 p-6">
                <Card className="w-full max-w-sm">
                    <CardHeader className="text-center">
                        <div className="mx-auto mb-2 flex size-12 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                            <Database className="size-6" strokeWidth={2} />
                        </div>
                        <CardTitle className="text-xl">{twoFactorPending ? 'Verify your sign-in' : 'Welcome back'}</CardTitle>
                        <CardDescription>
                            {twoFactorPending
                                ? 'Enter the 6-digit code sent to your admin email address.'
                                : 'Sign in to the super admin panel to continue.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {status && <p className="mb-4 rounded-md bg-primary/10 px-3 py-2 text-sm text-primary">{status}</p>}
                        {twoFactorPending ? (
                            <form onSubmit={verifyTwoFactor} className="space-y-4">
                                <div className="space-y-1.5">
                                    <Label htmlFor="otp">Verification code</Label>
                                    <Input
                                        id="otp"
                                        inputMode="numeric"
                                        autoComplete="one-time-code"
                                        value={twoFactorForm.data.otp}
                                        onChange={(event) => twoFactorForm.setData('otp', event.target.value.replace(/\D/g, '').slice(0, 6))}
                                        autoFocus
                                        required
                                        maxLength={6}
                                        placeholder="000000"
                                    />
                                    {twoFactorForm.errors.otp && <p className="text-xs font-medium text-destructive">{twoFactorForm.errors.otp}</p>}
                                </div>
                                <Button type="submit" disabled={twoFactorForm.processing} size="lg" className="w-full">
                                    {twoFactorForm.processing ? 'Verifying…' : 'Verify and sign in'}
                                </Button>
                                <Button type="button" variant="ghost" className="w-full" disabled={twoFactorForm.processing} onClick={() => twoFactorForm.post(route('login.resend-2fa'))}>
                                    Send a new code
                                </Button>
                            </form>
                        ) : (
                        <form onSubmit={submit} className="space-y-4">
                            <div className="space-y-1.5">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(event) => setData('email', event.target.value)}
                                    autoComplete="username"
                                    autoFocus
                                    required
                                    placeholder="admin@sms.com"
                                />
                                {errors.email && <p className="text-xs font-medium text-destructive">{errors.email}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="password">Password</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    value={data.password}
                                    onChange={(event) => setData('password', event.target.value)}
                                    autoComplete="current-password"
                                    required
                                />
                            </div>
                            <label className="flex items-center gap-2.5">
                                <Checkbox
                                    checked={data.remember}
                                    onCheckedChange={(checked) => setData('remember', Boolean(checked))}
                                />
                                <span className="text-sm text-muted-foreground">Remember me</span>
                            </label>
                            <Button type="submit" disabled={processing} size="lg" className="w-full">
                                {processing ? 'Signing in…' : 'Sign in'}
                            </Button>
                        </form>
                        )}
                    </CardContent>
                </Card>
            </main>
        </>
    );
}
