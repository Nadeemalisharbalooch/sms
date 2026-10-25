import { Head, router } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

const money = (amount) => new Intl.NumberFormat('en-PK', { style: 'currency', currency: 'PKR', maximumFractionDigits: 0 }).format(Number(amount || 0));

export default function PartnersIndex({ user, partners, withdrawals }) {
    const updatePartner = (partner, status) => router.patch(route('partners.admin.update', partner.id), { status });
    const updateRate = (partner) => {
        const commission_rate = window.prompt('Commission rate (0–100%)', partner.commission_rate);
        if (commission_rate === null) return;
        if (Number.isNaN(Number(commission_rate)) || Number(commission_rate) < 0 || Number(commission_rate) > 100) return window.alert('Enter a rate between 0 and 100.');
        router.patch(route('partners.admin.update', partner.id), { commission_rate });
    };
    const updateWithdrawal = (withdrawal, status) => {
        const transaction_reference = status === 'paid' ? window.prompt('Transaction reference (optional):') : null;
        if (status === 'paid' && transaction_reference === null) return;
        router.patch(route('partners.admin.withdrawals.process', withdrawal.id), { status, transaction_reference });
    };

    return (
        <AdminLayout user={user} title="Partner Program" onLogout={() => router.post(route('logout.web'))}>
            <Head title="Partner Program" />
            <div className="space-y-6">
                <Card className="gap-1 py-5">
                    <CardHeader>
                        <CardTitle>Partner accounts</CardTitle>
                        <CardDescription>Manage partner access, commission rates, referral activity, and balances.</CardDescription>
                    </CardHeader>
                    <Table><TableHeader><TableRow><TableHead>Partner</TableHead><TableHead>Referral code</TableHead><TableHead>Activity</TableHead><TableHead>Commission</TableHead><TableHead>Pending</TableHead><TableHead>Available</TableHead><TableHead>Status</TableHead><TableHead /></TableRow></TableHeader>
                        <TableBody>{partners.map((partner) => <TableRow key={partner.id}>
                            <TableCell><div className="font-medium">{partner.company_name}</div><div className="text-xs text-muted-foreground">{partner.user?.name} · {partner.user?.email}</div></TableCell>
                            <TableCell className="font-mono">{partner.referral_code}</TableCell>
                            <TableCell>{partner.clicks} clicks · {partner.signups} signups</TableCell>
                            <TableCell>{partner.commission_rate}%</TableCell>
                            <TableCell>{money(partner.pending_clearance)}</TableCell>
                            <TableCell>{money(partner.available_balance)}</TableCell>
                            <TableCell><Badge variant={partner.status === 'active' ? 'default' : 'secondary'}>{partner.status}</Badge></TableCell>
                            <TableCell><div className="flex gap-2"><Button size="sm" variant="outline" onClick={() => updateRate(partner)}>Rate</Button><Button size="sm" variant="outline" onClick={() => updatePartner(partner, partner.status === 'active' ? 'suspended' : 'active')}>{partner.status === 'active' ? 'Suspend' : 'Activate'}</Button></div></TableCell>
                        </TableRow>)}{!partners.length && <TableRow><TableCell colSpan={8} className="py-8 text-center text-muted-foreground">No partners yet.</TableCell></TableRow>}</TableBody>
                    </Table>
                </Card>
                <Card className="gap-1 py-5">
                    <CardHeader><CardTitle>Withdrawal requests</CardTitle><CardDescription>Review and record partner payouts.</CardDescription></CardHeader>
                    <Table><TableHeader><TableRow><TableHead>Partner</TableHead><TableHead>Amount</TableHead><TableHead>Requested</TableHead><TableHead>Status</TableHead><TableHead>Reference</TableHead><TableHead /></TableRow></TableHeader>
                        <TableBody>{withdrawals.map((withdrawal) => <TableRow key={withdrawal.id}>
                            <TableCell>{withdrawal.partner?.company_name}<div className="text-xs text-muted-foreground">{withdrawal.partner?.user?.email}</div><div className="text-xs text-muted-foreground">{withdrawal.partner?.payout_details?.bank_name} · {withdrawal.partner?.payout_details?.account_title} · {withdrawal.partner?.payout_details?.iban_or_account}</div></TableCell>
                            <TableCell>{money(withdrawal.amount)}</TableCell><TableCell>{new Date(withdrawal.created_at).toLocaleDateString()}</TableCell>
                            <TableCell><Badge variant={withdrawal.status === 'paid' ? 'default' : 'secondary'}>{withdrawal.status}</Badge></TableCell><TableCell>{withdrawal.transaction_reference || '—'}</TableCell>
                            <TableCell>{withdrawal.status === 'pending' && <div className="flex gap-2"><Button size="sm" onClick={() => updateWithdrawal(withdrawal, 'paid')}>Mark paid</Button><Button size="sm" variant="outline" onClick={() => updateWithdrawal(withdrawal, 'rejected')}>Reject</Button></div>}</TableCell>
                        </TableRow>)}{!withdrawals.length && <TableRow><TableCell colSpan={6} className="py-8 text-center text-muted-foreground">No withdrawal requests.</TableCell></TableRow>}</TableBody>
                    </Table>
                </Card>
            </div>
        </AdminLayout>
    );
}
