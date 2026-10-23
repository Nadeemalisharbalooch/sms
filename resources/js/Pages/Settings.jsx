import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Check, Monitor, Moon, ShieldCheck, Sun } from 'lucide-react';
import AdminLayout from '../Layouts/AdminLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';

const options = [
    { value: 'light', title: 'Light', description: 'Use the light appearance.', icon: Sun },
    { value: 'dark', title: 'Dark', description: 'Use the dark appearance.', icon: Moon },
    { value: 'system', title: 'System', description: 'Match your device preference.', icon: Monitor },
];

function applyTheme(theme) {
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    document.documentElement.classList.toggle('dark', theme === 'dark' || (theme === 'system' && prefersDark));
}

export default function Settings({ user, adminTwoFactorEnabled = false }) {
    const [theme, setTheme] = useState(() => localStorage.getItem('theme') || 'system');
    const [twoFactorEnabled, setTwoFactorEnabled] = useState(adminTwoFactorEnabled);

    useEffect(() => {
        applyTheme(theme);
        localStorage.setItem('theme', theme);

        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const handleChange = () => theme === 'system' && applyTheme(theme);
        media.addEventListener('change', handleChange);

        return () => media.removeEventListener('change', handleChange);
    }, [theme]);

    return (
        <AdminLayout user={user} title="System Settings" onLogout={() => router.post(route('logout.web'))}>
            <Head title="System Settings" />
            <div className="max-w-2xl">
                <Card size="sm" className="mb-5">
                    <CardHeader className="px-5">
                        <CardTitle className="flex items-center gap-2"><ShieldCheck className="size-5 text-primary" /> Admin two-factor authentication</CardTitle>
                        <CardDescription>Require an emailed verification code after an admin enters their password.</CardDescription>
                    </CardHeader>
                    <CardContent className="flex items-center justify-between gap-4 px-5">
                        <div>
                            <p className="text-sm font-medium">{twoFactorEnabled ? 'Enabled for admin sign-ins' : 'Disabled'}</p>
                            <p className="mt-1 text-xs text-muted-foreground">This only changes the web admin login. Institute and API sign-ins are unaffected.</p>
                        </div>
                        <Switch
                            checked={twoFactorEnabled}
                            aria-label="Enable admin two-factor authentication"
                            onCheckedChange={(enabled) => {
                                setTwoFactorEnabled(enabled);
                                router.patch(route('settings.two-factor.update'), { enabled }, {
                                    preserveScroll: true,
                                    onError: () => setTwoFactorEnabled(!enabled),
                                });
                            }}
                        />
                    </CardContent>
                </Card>
                <Card size="sm">
                    <CardHeader className="px-5">
                        <CardTitle>Appearance</CardTitle>
                        <CardDescription>Choose how the admin panel looks on this device.</CardDescription>
                    </CardHeader>
                    <CardContent className="px-5">
                        <div className="grid gap-3 sm:grid-cols-3">
                            {options.map((option) => {
                                const Icon = option.icon;
                                const selected = theme === option.value;

                                return (
                                    <button
                                        key={option.value}
                                        type="button"
                                        onClick={() => setTheme(option.value)}
                                        className={`relative rounded-xl border p-4 text-left transition-all ${
                                            selected
                                                ? 'border-primary bg-primary/5 ring-2 ring-primary/30'
                                                : 'hover:border-muted-foreground/30 hover:bg-muted/50'
                                        }`}
                                    >
                                        {selected && (
                                            <span className="absolute right-3 top-3 flex size-4.5 items-center justify-center rounded-full bg-primary text-primary-foreground">
                                                <Check className="size-3" strokeWidth={3} />
                                            </span>
                                        )}
                                        <Icon className={`size-5 ${selected ? 'text-primary' : 'text-muted-foreground'}`} strokeWidth={1.8} />
                                        <span className="mt-3 block text-sm font-medium">{option.title}</span>
                                        <span className="mt-1 block text-xs text-muted-foreground">{option.description}</span>
                                    </button>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
