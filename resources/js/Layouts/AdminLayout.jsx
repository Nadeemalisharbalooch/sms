import { Link, usePage } from '@inertiajs/react';

const navigation = [
    { label: 'Dashboard', route: 'dashboard' },
    { label: 'Institutes', route: 'institutes.index' },
    { label: 'System Settings', route: 'settings' },
];

export default function AdminLayout({ children, user, title, onLogout }) {
    const { url } = usePage();

    return (
        <div className="min-h-screen bg-gray-100 dark:bg-gray-900">
            <aside className="fixed inset-y-0 left-0 hidden w-64 flex-col bg-gray-900 text-white md:flex">
                <div className="border-b border-gray-700 px-6 py-5">
                    <p className="text-lg font-semibold">SMS Admin</p>
                    <p className="mt-1 text-sm text-gray-400">Super Admin Panel</p>
                </div>

                <nav className="flex-1 px-3 py-4">
                    {navigation.map((item) => {
                        const href = route(item.route);
                        const active = url === new URL(href).pathname;

                        return (
                            <Link
                                key={item.route}
                                href={href}
                                className={`mb-1 block rounded-md px-3 py-2 text-sm font-medium ${active ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white'}`}
                            >
                                {item.label}
                            </Link>
                        );
                    })}
                </nav>

                <div className="border-t border-gray-700 p-4">
                    <p className="truncate text-sm font-medium">{user.name}</p>
                    <p className="truncate text-xs text-gray-400">{user.email}</p>
                    <button type="button" onClick={onLogout} className="mt-3 text-sm text-gray-300 hover:text-white">Log out</button>
                </div>
            </aside>

            <div className="md:ml-64">
                <header className="bg-white px-6 py-4 shadow-sm dark:bg-gray-800">
                    <h1 className="text-xl font-semibold text-gray-900 dark:text-white">{title}</h1>
                </header>
                <main className="p-6">{children}</main>
            </div>
        </div>
    );
}
