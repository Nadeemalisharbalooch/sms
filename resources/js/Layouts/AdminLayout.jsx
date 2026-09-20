import { Link, usePage } from '@inertiajs/react';

const navigation = [
    { label: 'Dashboard', route: 'dashboard' },
    { label: 'All Institutes', route: 'institute.index' },
    { label: 'Plans & Packages', route: 'plans.index' },
    { label: 'Subscription Invoices', route: 'subscription-invoices.index' },
    { label: 'Notifications', route: 'notifications.index' },
    { label: 'System Settings', route: 'settings' },
];

export default function AdminLayout({ children, user, title, onLogout }) {
    const { props } = usePage();
    const flash = props.flash || {};
    const unreadCount = (props.notifications && props.notifications.unread_count) || 0;

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
                        const active = window.location.pathname === new URL(href, window.location.origin).pathname;

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
                <header className="flex items-center justify-between bg-white px-6 py-4 shadow-sm dark:bg-gray-800">
                    <h1 className="text-xl font-semibold text-gray-900 dark:text-white">{title}</h1>
                    <Link
                        href={route('notifications.index')}
                        className="relative rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white"
                        title="Notifications"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.8} stroke="currentColor" className="h-6 w-6">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                        </svg>
                        {unreadCount > 0 && (
                            <span className="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-xs font-semibold text-white">
                                {unreadCount > 99 ? '99+' : unreadCount}
                            </span>
                        )}
                    </Link>
                </header>
                <main className="p-6">
                    {flash.success && (
                        <div role="status" className="mb-6 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">
                            {flash.success}
                        </div>
                    )}
                    {flash.error && (
                        <div role="alert" className="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
                            {flash.error}
                        </div>
                    )}
                    {children}
                </main>
            </div>
        </div>
    );
}
