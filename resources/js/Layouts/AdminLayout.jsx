import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Check, Database, LogOut, Bell, Building2, CreditCard, LayoutDashboard, Package, Settings, X, Wifi, Loader2 } from 'lucide-react';
import useRealtimeNotifications from '../hooks/useRealtimeNotifications';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';

const navigation = [
    { label: 'Dashboard', route: 'dashboard', icon: LayoutDashboard },
    { label: 'All Institutes', route: 'institute.index', icon: Building2 },
    { label: 'Plans & Packages', route: 'plans.index', icon: Package },
    { label: 'Subscription Invoices', route: 'subscription-invoices.index', icon: CreditCard },
    { label: 'Notifications', route: 'notifications.index', icon: Bell },
    { label: 'System Settings', route: 'settings', icon: Settings },
];

function isActive(item) {
    const href = route(item.route);
    return window.location.pathname === new URL(href, window.location.origin).pathname;
}

export default function AdminLayout({ children, user, title, onLogout }) {
    const { props } = usePage();
    const flash = props.flash || {};
    const unreadCount = (props.notifications && props.notifications.unread_count) || 0;
    const { unread: liveUnread, toasts, transport } = useRealtimeNotifications(user);
    const badgeCount = unreadCount + liveUnread;
    const [mobileOpen, setMobileOpen] = useState(false);
    const initials = user.name.trim().split(/\s+/).map((part) => part[0]).slice(0, 2).join('').toUpperCase();

    const nav = (
        <nav className="flex flex-1 flex-col gap-1 px-3 py-4">
            <p className="px-3 pb-1 pt-2 text-[0.7rem] font-semibold uppercase tracking-wider text-sidebar-foreground/60">Manage</p>
            {navigation.map((item) => {
                const Icon = item.icon;
                const active = isActive(item);

                return (
                    <Link
                        key={item.route}
                        href={route(item.route)}
                        onClick={() => setMobileOpen(false)}
                        className={`group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
                            active
                                ? 'bg-sidebar-primary text-sidebar-primary-foreground'
                                : 'text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-foreground'
                        }`}
                    >
                        <Icon className="size-4.5 shrink-0" strokeWidth={1.8} />
                        {item.label}
                        {item.route === 'notifications.index' && badgeCount > 0 && (
                            <span className="ml-auto inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-destructive px-1.5 text-[0.65rem] font-semibold text-white">
                                {badgeCount > 99 ? '99+' : badgeCount}
                            </span>
                        )}
                    </Link>
                );
            })}
        </nav>
    );

    const brand = (
        <div className="flex items-center gap-3 border-b border-sidebar-border/50 px-6 py-5">
            <div className="flex size-9 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                <Database className="size-4.5" strokeWidth={2} />
            </div>
            <div className="leading-tight">
                <p className="text-sm font-semibold tracking-tight">SMS Admin</p>
                <p className="text-xs text-sidebar-foreground/60">Super Admin Panel</p>
            </div>
        </div>
    );

    const footer = (
        <div className="border-t border-sidebar-border/50 p-4">
            <div className="flex items-center gap-3">
                <Avatar className="size-9">
                    <AvatarFallback className="bg-sidebar-primary/20 text-[0.7rem] font-semibold text-sidebar-primary-foreground">
                        {initials}
                    </AvatarFallback>
                </Avatar>
                <div className="min-w-0 flex-1 leading-tight">
                    <p className="truncate text-sm font-medium">{user.name}</p>
                    <p className="truncate text-xs text-sidebar-foreground/60">{user.email}</p>
                </div>
            </div>
            <Button
                variant="ghost"
                size="sm"
                onClick={onLogout}
                className="mt-3 w-full justify-start text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-foreground"
            >
                <LogOut className="size-4" />
                Log out
            </Button>
        </div>
    );

    return (
        <div className="min-h-screen bg-background text-foreground">
            {/* Toast notifications */}
            <div className="pointer-events-none fixed right-4 top-4 z-50 flex w-80 flex-col gap-2">
                {toasts.map((toast) => (
                    <div
                        key={`${toast.id}-${toast.created_at}`}
                        role="status"
                        className="pointer-events-auto flex items-start gap-3 rounded-xl bg-popover p-4 text-popover-foreground shadow-lg ring-1 ring-foreground/10"
                    >
                        <span className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                            <Check className="size-4" />
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-medium">{toast.title}</p>
                            <p className="mt-0.5 text-xs text-muted-foreground">{toast.body}</p>
                        </div>
                    </div>
                ))}
            </div>

            {/* Mobile overlay */}
            {mobileOpen && (
                <div className="fixed inset-0 z-40 bg-black/50 md:hidden" onClick={() => setMobileOpen(false)} />
            )}

            {/* Sidebar (desktop) */}
            <aside className="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col border-r border-sidebar-border/50 bg-sidebar text-sidebar-foreground md:flex">
                {brand}
                {nav}
                {footer}
            </aside>

            {/* Sidebar (mobile) */}
            <aside
                className={`fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-sidebar-border/50 bg-sidebar text-sidebar-foreground transition-transform duration-200 md:hidden ${
                    mobileOpen ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                <div className="flex items-center justify-between">
                    {brand}
                    <Button variant="ghost" size="icon-sm" onClick={() => setMobileOpen(false)} className="mr-2 self-center">
                        <X className="size-4" />
                    </Button>
                </div>
                {nav}
                {footer}
            </aside>

            <div className="md:ml-64">
                <header className="sticky top-0 z-30 flex h-16 items-center justify-between gap-4 border-b bg-background/95 px-4 backdrop-blur-sm md:px-6">
                    <div className="flex items-center gap-3">
                        <Button variant="ghost" size="icon" onClick={() => setMobileOpen(true)} className="md:hidden">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.8} stroke="currentColor" className="size-5">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            </svg>
                        </Button>
                        <div className="leading-tight">
                            <h1 className="text-lg font-semibold tracking-tight">{title}</h1>
                        </div>
                    </div>
                    <div className="flex items-center gap-2.5">
                        {transport === 'websocket' ? (
                            <Badge variant="secondary" className="h-6 gap-1.5 px-2 text-[0.7rem] font-normal text-emerald-600 dark:text-emerald-400">
                                <Wifi className="size-3" />
                                Live
                            </Badge>
                        ) : (
                            <Badge variant="secondary" className="h-6 gap-1.5 px-2 text-[0.7rem] font-normal text-muted-foreground">
                                <Loader2 className="size-3 animate-spin" />
                                Polling
                            </Badge>
                        )}
                        <Button variant="ghost" size="icon" asChild>
                            <Link
                                href={route('notifications.index')}
                                className="relative"
                                title={transport === 'websocket' ? 'Notifications (live)' : 'Notifications (refreshing periodically)'}
                            >
                                <Bell className="size-5" strokeWidth={1.8} />
                                {badgeCount > 0 && (
                                    <span className="absolute right-0.5 top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[0.6rem] font-semibold text-white">
                                        {badgeCount > 99 ? '99+' : badgeCount}
                                    </span>
                                )}
                            </Link>
                        </Button>
                        <Separator orientation="vertical" className="mx-1 h-7" />
                        <Avatar className="size-8">
                            <AvatarFallback className="bg-primary/10 text-[0.65rem] font-semibold">{initials}</AvatarFallback>
                        </Avatar>
                    </div>
                </header>
                <main className="mx-auto w-full max-w-7xl p-4 md:p-6">
                    {flash.success && (
                        <div role="status" className="mb-5 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/50 dark:text-emerald-200">
                            <Check className="mt-0.5 size-4 shrink-0" />
                            {flash.success}
                        </div>
                    )}
                    {flash.error && (
                        <div role="alert" className="mb-5 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/60 dark:bg-red-950/50 dark:text-red-200">
                            <X className="mt-0.5 size-4 shrink-0" />
                            {flash.error}
                        </div>
                    )}
                    {children}
                </main>
            </div>
        </div>
    );
}
