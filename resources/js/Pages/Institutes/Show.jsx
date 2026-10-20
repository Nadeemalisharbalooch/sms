import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    Camera,
    CalendarDays,
    GraduationCap,
    MapPin,
    Phone,
    Receipt,
    Users,
} from 'lucide-react';
import AdminLayout from '../../Layouts/AdminLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

const infoBadgeVariant = {
    paid: 'default',
    verification_pending: 'secondary',
    open: 'outline',
    void: 'ghost',
};

export default function InstitutesShow({ user, institute }) {
    const subscription = institute.subscription;

    return (
        <AdminLayout user={user} title={institute.name} onLogout={() => router.post(route('logout.web'))}>
            <Head title={institute.name} />

            <div className="space-y-4">
                <div>
                    <Button type="button" variant="ghost" size="sm" className="-ml-2" onClick={() => router.visit(route('institute.index'))}>
                        <ArrowLeft className="size-4" />
                        Back to institutes
                    </Button>
                </div>

                {/* Header */}
                <Card size="sm" className="gap-0 py-0">
                    <CardHeader className="border-b px-5 py-4">
                        <div className="flex flex-wrap items-center gap-4">
                            <InstituteLogo institute={institute} />
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h1 className="font-heading text-lg font-medium">{institute.name}</h1>
                                    <Badge
                                        variant={institute.is_active ? 'secondary' : 'outline'}
                                        className={institute.is_active
                                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                                            : 'text-muted-foreground'}
                                    >
                                        {institute.is_active ? 'Active' : 'Inactive'}
                                    </Badge>
                                </div>
                                <CardDescription className="mt-0.5 truncate">
                                    ID: {institute.public_id} · Registered {formatDate(institute.created_at)}
                                </CardDescription>
                            </div>
                            <div className="ml-auto flex flex-wrap items-center gap-4">
                                <Stat icon={<Users className="size-4 text-muted-foreground" />} value={institute.stats?.users ?? 0} label="Members" />
                                <Stat icon={<GraduationCap className="size-4 text-muted-foreground" />} value={institute.stats?.students ?? 0} label="Students" />
                                <Stat icon={<Building2 className="size-4 text-muted-foreground" />} value={institute.stats?.classes ?? 0} label="Classes" />
                            </div>
                        </div>
                    </CardHeader>
                </Card>

                <div className="grid gap-4 lg:grid-cols-2">
                    {/* Institute details */}
                    <Card size="sm" className="h-fit gap-0 py-0">
                        <CardHeader className="border-b px-5 py-4">
                            <CardTitle className="flex items-center gap-2">
                                <Building2 className="size-4.5 text-muted-foreground" />
                                Institute Information
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="px-5 py-4">
                            <DetailRow label="Name" value={institute.name} />
                            <DetailRow label="Short name" value={institute.short_name} />
                            <DetailRow label="Email" value={institute.email} />
                            <DetailRow label="Phone" value={institute.phone} />
                            <DetailRow
                                label="Address"
                                value={institute.address ? (
                                    <span className="inline-flex items-start gap-1.5">
                                        <MapPin className="mt-0.5 size-3.5 shrink-0 text-muted-foreground" />
                                        <span>{institute.address}</span>
                                    </span>
                                ) : null}
                            />
                            <DetailRow label="Attendance mode" value={capital(institute.attendance_mode)} />
                            <DetailRow label="Currency" value={institute.currency_symbol} />
                            <DetailRow label="Timezone" value={institute.timezone} />
                            <DetailRow label="Default fee due date" value={institute.default_fee_due_date ? `Day ${institute.default_fee_due_date} of the month` : null} />
                            <DetailRow label="Created" value={institute.created_at ? `${formatDate(institute.created_at)} (${formatTime(institute.created_at)})` : null} />
                            <DetailRow label="Last updated" value={institute.updated_at ? `${formatDate(institute.updated_at)} (${formatTime(institute.updated_at)})` : null} />
                            <DetailRow
                                label="Favicon"
                                value={institute.favicon_url ? (
                                    <img src={institute.favicon_url} alt="favicon" className="size-10 rounded-md object-contain ring-1 ring-foreground/10" />
                                ) : null}
                            />
                        </CardContent>
                    </Card>

                    <div className="space-y-4">
                        {/* Owner */}
                        <Card size="sm" className="gap-0 py-0">
                            <CardHeader className="border-b px-5 py-4">
                                <CardTitle className="flex items-center gap-2">
                                    <Users className="size-4.5 text-muted-foreground" />
                                    Institute Owner
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="px-5 py-4">
                                <DetailRow label="Name" value={institute.owner?.name} />
                                <DetailRow label="Email" value={institute.owner?.email} />
                                <DetailRow
                                    label="Phone"
                                    value={institute.owner?.phone ? (
                                        <span className="inline-flex items-center gap-1.5">
                                            <Phone className="size-3.5 text-muted-foreground" />
                                            {institute.owner.phone}
                                        </span>
                                    ) : null}
                                />
                            </CardContent>
                        </Card>

                        {/* Subscription */}
                        <Card size="sm" className="gap-0 py-0">
                            <CardHeader className="border-b px-5 py-4">
                                <CardTitle className="flex items-center gap-2">
                                    <CalendarDays className="size-4.5 text-muted-foreground" />
                                    Subscription
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="px-5 py-4">
                                {subscription ? (
                                    <>
                                        <DetailRow label="Plan" value={subscription.plan?.name} />
                                        <DetailRow
                                            label="Status"
                                            value={(
                                                <span className="inline-flex items-center gap-2">
                                                    <Badge variant={subscription.status ? undefined : 'outline'} className="capitalize">
                                                        {subscription.status || '—'}
                                                    </Badge>
                                                    {subscription.blocked && <Badge variant="destructive">Blocked</Badge>}
                                                </span>
                                            )}
                                        />
                                        <DetailRow label="Starts at" value={subscription.starts_at ? formatDate(subscription.starts_at) : null} />
                                        <DetailRow label="Ends at" value={subscription.ends_at ? formatDate(subscription.ends_at) : null} />
                                        <DetailRow label="Approved at" value={subscription.approved_at ? formatDate(subscription.approved_at) : null} />
                                    </>
                                ) : (
                                    <p className="text-sm text-muted-foreground">No subscription yet.</p>
                                )}
                            </CardContent>
                        </Card>

                        {/* Latest invoice */}
                        <Card size="sm" className="gap-0 py-0">
                            <CardHeader className="border-b px-5 py-4">
                                <CardTitle className="flex items-center gap-2">
                                    <Receipt className="size-4.5 text-muted-foreground" />
                                    Latest Invoice
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="px-5 py-4">
                                {subscription?.invoice ? (
                                    <>
                                        <DetailRow label="Invoice number" value={<span className="font-mono text-xs">{subscription.invoice.invoice_number}</span>} />
                                        <DetailRow label="Amount" value={subscription.invoice.currency ? `${subscription.invoice.currency} ${subscription.invoice.amount}` : null} />
                                        <DetailRow label="Billing interval" value={capital(subscription.invoice.billing_interval)} />
                                        <DetailRow
                                            label="Status"
                                            value={(
                                                <Badge variant={infoBadgeVariant[subscription.invoice.status] || 'outline'} className="text-[0.7rem] capitalize">
                                                    {subscription.invoice.status?.replace('_', ' ')}
                                                </Badge>
                                            )}
                                        />
                                        <DetailRow label="Due date" value={subscription.invoice.due_date ? formatDate(subscription.invoice.due_date) : null} />
                                        <DetailRow label="Payment method" value={capital(subscription.invoice.payment_method)} />
                                        <DetailRow label="Payment reference" value={subscription.invoice.payment_reference} />
                                        <DetailRow label="Payment submitted" value={subscription.invoice.payment_submitted_at ? formatDate(subscription.invoice.payment_submitted_at) : null} />
                                        <DetailRow label="Paid at" value={subscription.invoice.paid_at ? formatDate(subscription.invoice.paid_at) : null} />
                                        <DetailRow
                                            label="Payment proof"
                                            value={subscription.invoice.payment_screenshot_url ? (
                                                <a href={subscription.invoice.payment_screenshot_url} target="_blank" rel="noreferrer" className="font-medium text-primary underline-offset-4 hover:underline">
                                                    View screenshot
                                                </a>
                                            ) : null}
                                        />
                                        {subscription.invoice.rejection_reason && (
                                            <DetailRow label="Rejection reason" value={<span className="text-destructive">{subscription.invoice.rejection_reason}</span>} />
                                        )}
                                    </>
                                ) : (
                                    <p className="text-sm text-muted-foreground">No invoice yet.</p>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}

function InstituteLogo({ institute }) {
    const logoSrc = institute.logo_url || null;

    if (logoSrc) {
        return (
            <img
                src={logoSrc}
                alt="logo"
                className="size-14 rounded-xl object-cover ring-1 ring-foreground/10"
                onError={(e) => { e.currentTarget.style.display = 'none'; }}
            />
        );
    }

    return (
        <div className="flex size-14 items-center justify-center rounded-xl bg-muted text-muted-foreground">
            <Camera className="size-6" />
        </div>
    );
}

function Stat({ icon, value, label }) {
    return (
        <div className="text-right">
            <p className="flex items-center justify-end gap-1.5 text-lg font-medium tabular-nums">{icon}{value}</p>
            <p className="text-xs text-muted-foreground">{label}</p>
        </div>
    );
}

function DetailRow({ label, value }) {
    if (value === null || value === undefined || value === '') {
        return <Row label={label} value={<span className="text-xs text-muted-foreground">—</span>} />;
    }

    return <Row label={label} value={<span className="text-sm">{value}</span>} />;
}

function Row({ label, value }) {
    return (
        <div className="border-border/40 border-b py-2 first:border-0 first:pb-0 last:border-0 last:pb-0">
            <p className="text-[0.65rem] font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
            <div className="mt-1">{value}</div>
        </div>
    );
}

function capital(value) {
    if (!value) return null;

    return value.charAt(0).toUpperCase() + value.slice(1).replace(/_/g, ' ');
}

function formatDate(value) {
    if (!value) return '—';

    return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatTime(value) {
    if (!value) return '—';

    return new Date(value).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
}
