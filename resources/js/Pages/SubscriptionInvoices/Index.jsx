import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Search } from 'lucide-react';
import AdminLayout from '../../Layouts/AdminLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

const statuses = ['open', 'verification_pending', 'paid', 'void'];

const statusVariant = {
    paid: 'default',
    verification_pending: 'secondary',
    open: 'outline',
    void: 'ghost',
};

export default function SubscriptionInvoicesIndex({ user, invoices, filters }) {
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || 'all');
    const applyFilters = (event) => {
        event.preventDefault();
        router.get(route('subscription-invoices.index'), { search: search || undefined, status: status === 'all' ? undefined : status }, { preserveState: true, replace: true });
    };

    const rejectInvoice = (invoice) => {
        const reason = window.prompt(`Reject payment for ${invoice.invoice_number}?\nEnter an optional reason for the institute:`);
        if (reason === null) {
            return;
        }
        router.post(route('subscription-invoices.reject', invoice.id), { reason }, { forceFormData: true });
    };

    return (
        <AdminLayout user={user} title="Subscription Invoices" onLogout={() => router.post(route('logout.web'))}>
            <Head title="Subscription Invoices" />
            <Card size="sm" className="gap-0 py-0">
                <CardHeader className="gap-2 border-b px-5 py-4">
                    <CardTitle>All Invoice History</CardTitle>
                    <CardDescription>Manage invoices for every institute, including renewals and upgrades.</CardDescription>
                    <form onSubmit={applyFilters} className="mt-2 flex flex-wrap items-center gap-2">
                        <div className="relative">
                            <Search className="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Institute, invoice, reference..." className="w-64 pl-8" />
                        </div>
                        <Select value={status} onValueChange={setStatus}>
                            <SelectTrigger className="w-44"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All statuses</SelectItem>
                                {statuses.map((item) => (
                                    <SelectItem key={item} value={item}>{item.replace('_', ' ')}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button type="submit">Filter</Button>
                    </form>
                </CardHeader>
                {invoices.data.length ? (
                    <Table>
                        <TableHeader>
                            <TableRow className="bg-muted/50 hover:bg-muted/50">
                                <TableHead className="px-5">Invoice</TableHead>
                                <TableHead className="px-4">Institute</TableHead>
                                <TableHead className="px-4">Plan</TableHead>
                                <TableHead className="px-4">Amount</TableHead>
                                <TableHead className="px-4">Payment Proof</TableHead>
                                <TableHead className="px-4">Status</TableHead>
                                <TableHead className="px-4 text-right">Action</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {invoices.data.map((invoice) => (
                                <TableRow key={invoice.id}>
                                    <TableCell className="px-5 py-4">
                                        <p className="font-mono text-xs font-medium">{invoice.invoice_number}</p>
                                        <p className="mt-0.5 text-xs text-muted-foreground">Created {new Date(invoice.created_at).toLocaleDateString()}</p>
                                    </TableCell>
                                    <TableCell className="px-4 py-4">
                                        <p className="text-sm font-medium">{invoice.institute?.name}</p>
                                        <p className="mt-0.5 text-xs text-muted-foreground">{invoice.institute?.email}</p>
                                    </TableCell>
                                    <TableCell className="px-4 py-4">
                                        <p className="text-sm font-medium">{invoice.plan?.name}</p>
                                        <p className="mt-0.5 text-xs capitalize text-muted-foreground">{invoice.plan?.billing_interval}</p>
                                    </TableCell>
                                    <TableCell className="px-4 py-4">
                                        <p className="tabular-nums">{invoice.currency} {invoice.amount}</p>
                                        <p className="mt-0.5 text-xs text-muted-foreground">Due: {invoice.due_date || '—'}</p>
                                    </TableCell>
                                    <TableCell className="px-4 py-4">
                                        <div className="text-xs">
                                            <p className="capitalize">{invoice.payment_method || '—'}</p>
                                            <p className="mt-0.5 max-w-40 truncate text-muted-foreground">{invoice.payment_reference || '—'}</p>
                                            {invoice.payment_screenshot_url && (
                                                <a href={invoice.payment_screenshot_url} target="_blank" rel="noreferrer" className="mt-1 inline-block font-medium text-primary underline-offset-4 hover:underline">
                                                    View screenshot
                                                </a>
                                            )}
                                        </div>
                                    </TableCell>
                                    <TableCell className="px-4 py-4">
                                        <Badge variant={statusVariant[invoice.status] || 'outline'} className="capitalize">
                                            {invoice.status.replace('_', ' ')}
                                        </Badge>
                                        {invoice.rejection_reason && (
                                            <p className="mt-1 max-w-40 text-xs text-destructive">Rejected: {invoice.rejection_reason}</p>
                                        )}
                                    </TableCell>
                                    <TableCell className="px-4 py-4 text-right">
                                        {invoice.status === 'verification_pending' ? (
                                            <div className="flex justify-end gap-2">
                                                <Button type="button" size="sm" variant="outline" className="text-emerald-600 dark:text-emerald-400" onClick={() => router.put(route('subscription-invoices.verify', invoice.id))}>
                                                    Verify
                                                </Button>
                                                <Button type="button" size="sm" variant="destructive" onClick={() => rejectInvoice(invoice)}>
                                                    Reject
                                                </Button>
                                            </div>
                                        ) : (
                                            <span className="text-xs text-muted-foreground">—</span>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                ) : (
                    <div className="px-6 py-16 text-center">
                        <p className="text-sm font-medium">No invoices found</p>
                        <p className="mt-1 text-sm text-muted-foreground">Try adjusting your filters.</p>
                    </div>
                )}
                {invoices.links.length > 3 && (
                    <CardContent className="flex flex-wrap gap-1.5 border-t px-5 py-4">
                        {invoices.links.map((link, index) =>
                            link.url ? (
                                <Link
                                    key={index}
                                    href={link.url}
                                    className={index === 0 || index === invoices.links.length - 1
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
