import { Head, router } from '@inertiajs/react';
import AdminLayout from '../Layouts/AdminLayout';

export default function Dashboard({ user, institutesCount }) {
    return (
        <AdminLayout user={user} title="Dashboard" onLogout={() => router.post(route('logout'))}>
            <Head title="Dashboard" />
            <div className="grid gap-6 sm:grid-cols-2">
                <section className="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
                    <h2 className="text-base font-semibold text-gray-900 dark:text-white">Welcome, {user.name}</h2>
                    <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">This is your Inertia Super Admin dashboard.</p>
                </section>
                <section className="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
                    <p className="text-sm font-medium text-gray-600 dark:text-gray-300">Total Institutes</p>
                    <p className="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">{institutesCount}</p>
                </section>
            </div>
        </AdminLayout>
    );
}
