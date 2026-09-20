import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../Layouts/AdminLayout';

export default function NotificationsIndex({ user, notifications, filters, unread_count }) {
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');
    const applyFilters = (event) => {
        event.preventDefault();
        router.get(
            route('notifications.index'),
            { search: search || undefined, status: status || undefined },
            { preserveState: true, replace: true }
        );
    };

    const unreadCount = notifications.data.filter((item) => !item.read_at).length;

    return (
        <AdminLayout user={user} title="Notifications" onLogout={() => router.post(route('logout.web'))}>
            <Head title="Notifications" />
            <section className="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-800">
                <div className="flex flex-col gap-4 border-b border-gray-200 px-6 py-4 dark:border-gray-700 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 className="font-semibold text-gray-900 dark:text-white">Notification Tray</h2>
                        <p className="mt-1 text-sm text-gray-500">
                            {unread_count > 0 ? `${unread_count} unread notification${unread_count === 1 ? '' : 's'}.` : 'You are all caught up.'}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <form onSubmit={applyFilters} className="flex flex-wrap gap-2">
                            <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search..." className="input !mt-0 w-48" />
                            <select value={status} onChange={(e) => setStatus(e.target.value)} className="input !mt-0 w-36">
                                <option value="">All</option>
                                <option value="unread">Unread</option>
                                <option value="read">Read</option>
                            </select>
                            <button type="submit" className="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white dark:bg-indigo-600">Filter</button>
                        </form>
                        {unreadCount > 0 && (
                            <button
                                type="button"
                                onClick={() => router.patch(route('notifications.read-all'))}
                                className="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200"
                            >
                                Mark all read
                            </button>
                        )}
                    </div>
                </div>

                {notifications.data.length ? (
                    <ul className="divide-y divide-gray-100 dark:divide-gray-700">
                        {notifications.data.map((notification) => {
                            const data = notification.data || {};
                            const unread = !notification.read_at;
                            return (
                                <li key={notification.id} className="flex items-start gap-4 px-6 py-4">
                                    <span className={`mt-2 h-2 w-2 shrink-0 rounded-full ${unread ? 'bg-indigo-600' : 'bg-gray-300 dark:bg-gray-600'}`} />
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-medium text-gray-900 dark:text-white">{data.title || 'Notification'}</p>
                                            {(data.priority || data.category) && (
                                                <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs capitalize text-gray-500 dark:bg-gray-700 dark:text-gray-300">
                                                    {[data.priority, data.category].filter(Boolean).join(' · ')}
                                                </span>
                                            )}
                                        </div>
                                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">{data.body || ''}</p>
                                        <p className="mt-1 text-xs text-gray-400">
                                            {new Date(notification.created_at).toLocaleString()}
                                        </p>
                                        {data.action_url && (
                                            <a href={data.action_url} target="_blank" rel="noreferrer" className="mt-2 inline-block text-sm text-indigo-600">
                                                {data.action_text || 'Open'}
                                            </a>
                                        )}
                                    </div>
                                    {unread && (
                                        <button
                                            type="button"
                                            onClick={() => router.patch(route('notifications.read', notification.id))}
                                            className="shrink-0 text-sm text-indigo-600 hover:text-indigo-800"
                                        >
                                            Mark read
                                        </button>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                ) : (
                    <div className="px-6 py-12 text-center text-sm text-gray-500">No notifications found.</div>
                )}

                {notifications.links.length > 3 && (
                    <div className="flex flex-wrap gap-2 border-t border-gray-200 px-6 py-4 dark:border-gray-700">
                        {notifications.links.map((link, index) =>
                            link.url ? (
                                <Link
                                    key={index}
                                    href={link.url}
                                    className={`rounded px-3 py-1 text-sm ${link.active ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200'}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ) : (
                                <span key={index} className="px-3 py-1 text-sm text-gray-400" dangerouslySetInnerHTML={{ __html: link.label }} />
                            )
                        )}
                    </div>
                )}
            </section>
        </AdminLayout>
    );
}