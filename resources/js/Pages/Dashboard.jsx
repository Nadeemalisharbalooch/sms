import { Head, router } from '@inertiajs/react';
import { Building2, CreditCard, Package, Users } from 'lucide-react';
import AdminLayout from '../Layouts/AdminLayout';
import { Card, CardContent } from '@/components/ui/card';

const stats = [
    { label: 'Total Institutes', valueKey: 'institutesCount', icon: Building2 },
    { label: 'Active Plans', valueKey: 'plansCount', icon: Package },
    { label: 'Pending Invoices', valueKey: 'pendingInvoicesCount', icon: CreditCard },
    { label: 'Total Users', valueKey: 'usersCount', icon: Users },
];

export default function Dashboard({ user, institutesCount, plansCount, pendingInvoicesCount, usersCount }) {
    const values = {
        institutesCount,
        plansCount,
        pendingInvoicesCount,
        usersCount,
    };

    return (
        <AdminLayout user={user} title="Dashboard" onLogout={() => router.post(route('logout.web'))}>
            <Head title="Dashboard" />

            <div className="mb-6">
                <h2 className="text-2xl font-semibold tracking-tight">Welcome back, {user.name}</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Here's an overview of your platform today.
                </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {stats.map((stat) => {
                    const Icon = stat.icon;
                    const value = values[stat.valueKey];

                    return (
                        <Card key={stat.valueKey} className="gap-3 py-5">
                            <CardContent className="flex items-start justify-between">
                                <div className="space-y-1">
                                    <p className="text-sm text-muted-foreground">{stat.label}</p>
                                    <p className="text-3xl font-semibold tracking-tight tabular-nums">
                                        {value ?? '—'}
                                    </p>
                                </div>
                                <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <Icon className="size-5" strokeWidth={1.8} />
                                </div>
                            </CardContent>
                        </Card>
                    );
                })}
            </div>
        </AdminLayout>
    );
}
