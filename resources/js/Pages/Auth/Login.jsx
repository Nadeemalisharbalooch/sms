import { Head, useForm } from '@inertiajs/react';
import { Database } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({ email: '', password: '', remember: true });

    function submit(event) {
        event.preventDefault();
        post(route('login.store'));
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
                        <CardTitle className="text-xl">Welcome back</CardTitle>
                        <CardDescription>Sign in to the super admin panel to continue.</CardDescription>
                    </CardHeader>
                    <CardContent>
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
                    </CardContent>
                </Card>
            </main>
        </>
    );
}
