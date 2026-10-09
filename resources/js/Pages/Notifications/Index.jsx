import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Bell, Check, Search } from 'lucide-react';
import AdminLayout from '../../Layouts/AdminLayout';
import { useRealtimeNotificationItems } from '../../hooks/useRealtimeNotifications';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';

export default function NotificationsIndex({ user, notifications, filters }) {
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || 'all');
    const liveItems = useRealtimeNotificationItems(user.id);
    const applyFilters = (event) => {
        event.preventDefault();
        router.get(
            route('notifications.index'),
            { search: search || undefined, status: status === 'all' ? undefined : status },
            { preserveState: true, replace: true }
        );
    };

    const existingIds = new Set(notifications.data.map((item) => item.id));
    const allItems = [
        ...liveItems
            .filter((item) => ! existingIds.has(item.id))
            .map((item) => ({
                ...item,
                data: {
                    title: item.title,
                    body: item.body,
                    priority: item.priority,
                    category: item.category,
                    action_text: item.action_text,
                    action_url: item.action_url,
                },
            })),
        ...notifications.data,
    ];
    const unreadCount = allItems.filter((item) => !item.read_at).length;

    return (
        <AdminLayout user={user} title="Notifications" onLogout={() => router.post(route('logout.web'))}>
            <Head title="Notifications" />
            <Card size="sm" className="gap-0 py-0">
                <CardHeader className="gap-2 border-b px-5 py-4">
                    <CardTitle className="flex items-center gap-2">
                        <Bell className="size-4.5 text-muted-foreground" />
                        Notification Tray
                    </CardTitle>
                    <CardDescription>
                        {unreadCount > 0 ? `${unreadCount} unread notification${unreadCount === 1 ? '' : 's'}.` : 'You are all caught up.'}
                    </CardDescription>
                    <div className="mt-2 flex flex-wrap items-center gap-2">
                        <form onSubmit={applyFilters} className="flex flex-wrap items-center gap-2">
                            <div className="relative">
                                <Search className="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search..." className="w-52 pl-8" />
                            </div>
                            <Select value={status} onValueChange={setStatus}>
                                <SelectTrigger className="w-32"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All</SelectItem>
                                    <SelectItem value="unread">Unread</SelectItem>
                                    <SelectItem value="read">Read</SelectItem>
                                </SelectContent>
                            </Select>
                            <Button type="submit">Filter</Button>
                        </form>
                        {unreadCount > 0 && (
                            <>
                                <Separator orientation="vertical" className="h-8" />
                                <Button type="button" variant="outline" onClick={() => router.patch(route('notifications.read-all'))}>
                                    <Check className="size-4" />
                                    Mark all read
                                </Button>
                            </>
                        )}
                    </div>
                </CardHeader>

                {allItems.length ? (
                    <ul className="divide-y">
                        {allItems.map((notification) => {
                            const data = notification.data || {};
                            const unread = !notification.read_at;
                            return (
                                <li key={notification.id} className={`flex items-start gap-4 px-5 py-4 transition-colors ${unread ? 'bg-primary/[0.03]' : ''}`}>
                                    <span className={`mt-2 size-2 shrink-0 rounded-full ${unread ? 'bg-primary' : 'bg-muted-foreground/25'}`} />
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className={`text-sm ${unread ? 'font-semibold text-foreground' : 'font-medium text-foreground/80'}`}>{data.title || 'Notification'}</p>
                                            {(data.priority || data.category) && (
                                                <Badge variant="secondary" className="text-[0.65rem] capitalize">
                                                    {[data.priority, data.category].filter(Boolean).join(' · ')}
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="mt-1 text-sm text-muted-foreground">{data.body || ''}</p>
                                        <p className="mt-1.5 text-xs text-muted-foreground/70">
                                            {new Date(notification.created_at).toLocaleString()}
                                        </p>
                                        {data.action_url && (
                                            <a href={data.action_url} target="_blank" rel="noreferrer" className="mt-2 inline-block text-sm font-medium text-primary underline-offset-4 hover:underline">
                                                {data.action_text || 'Open'}
                                            </a>
                                        )}
                                    </div>
                                    {unread && (
                                        <Button type="button" variant="outline" size="sm" onClick={() => router.patch(route('notifications.read', notification.id))} className="shrink-0">
                                            Mark read
                                        </Button>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                ) : (
                    <div className="px-6 py-16 text-center">
                        <p className="text-sm font-medium">No notifications found</p>
                        <p className="mt-1 text-sm text-muted-foreground">Try adjusting your filters.</p>
                    </div>
                )}

                {notifications.links.length > 3 && (
                    <CardContent className="flex flex-wrap gap-1.5 border-t px-5 py-4">
                        {notifications.links.map((link, index) =>
                            link.url ? (
                                <Link
                                    key={index}
                                    href={link.url}
                                    className={index === 0 || index === notifications.links.length - 1
                                        ? 'px-2 py-1 text-xs text-muted-foreground hover:text-foreground'
                                        : `min-w-8 rounded-md border px-2.5 py-1 text-center text-xs font-medium ${link.active ? 'border-primary bg-primary text-primary-foreground' : 'hover:bg-muted'}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ) : (
                                <span key={index} className="px-2 py-1 text-xs text-muted-foreground" dangerouslySetInnerHTML={{ __html: link.label }} />
                            )
                        )}
                    </CardContent>
                )}
            </Card>
        </AdminLayout>
    );
}
