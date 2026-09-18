import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../Layouts/AdminLayout';

const statuses = ['', 'open', 'verification_pending', 'paid', 'void'];

export default function SubscriptionInvoicesIndex({ user, invoices, filters }) {
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');
    const applyFilters = (event) => {
        event.preventDefault();
        router.get(route('subscription-invoices.index'), { search: search || undefined, status: status || undefined }, { preserveState: true, replace: true });
    };

    return (
        <AdminLayout user={user} title="Subscription Invoices" onLogout={() => router.post(route('logout.web'))}>
            <Head title="Subscription Invoices" />
            <section className="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-800">
                <div className="flex flex-col gap-4 border-b border-gray-200 px-6 py-4 dark:border-gray-700 md:flex-row md:items-center md:justify-between">
                    <div><h2 className="font-semibold text-gray-900 dark:text-white">All Invoice History</h2><p className="mt-1 text-sm text-gray-500">Manage invoices for every institute, including renewals and upgrades.</p></div>
                    <form onSubmit={applyFilters} className="flex flex-wrap gap-2"><input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Institute, invoice, reference..." className="input !mt-0 w-56" /><select value={status} onChange={(e) => setStatus(e.target.value)} className="input !mt-0 w-44"><option value="">All statuses</option>{statuses.slice(1).map((item) => <option key={item} value={item}>{item.replace('_', ' ')}</option>)}</select><button type="submit" className="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white dark:bg-indigo-600">Filter</button></form>
                </div>
                {invoices.data.length ? <div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead className="bg-gray-50 text-gray-600 dark:bg-gray-700 dark:text-gray-300"><tr><th className="px-6 py-3">Invoice</th><th className="px-6 py-3">Institute</th><th className="px-6 py-3">Plan</th><th className="px-6 py-3">Amount</th><th className="px-6 py-3">Payment Proof</th><th className="px-6 py-3">Status</th><th className="px-6 py-3">Action</th></tr></thead><tbody className="divide-y divide-gray-100 dark:divide-gray-700">{invoices.data.map((invoice) => <tr key={invoice.id} className="text-gray-700 dark:text-gray-200"><td className="px-6 py-4"><p className="font-medium">{invoice.invoice_number}</p><p className="mt-1 text-xs text-gray-500">Created: {new Date(invoice.created_at).toLocaleDateString()}</p></td><td className="px-6 py-4"><p className="font-medium">{invoice.institute?.name}</p><p className="mt-1 text-xs text-gray-500">{invoice.institute?.email}</p></td><td className="px-6 py-4">{invoice.plan?.name}<p className="mt-1 text-xs capitalize text-gray-500">{invoice.plan?.billing_interval}</p></td><td className="px-6 py-4">{invoice.currency} {invoice.amount}<p className="mt-1 text-xs text-gray-500">Due: {invoice.due_date || '-'}</p></td><td className="px-6 py-4 text-xs"><p className="capitalize">{invoice.payment_method || '-'}</p><p className="mt-1 break-all text-gray-500">{invoice.payment_reference || '-'}</p>{invoice.payment_screenshot_url && <a href={invoice.payment_screenshot_url} target="_blank" rel="noreferrer" className="mt-1 inline-block text-indigo-600">View screenshot</a>}</td><td className="px-6 py-4"><span className="capitalize">{invoice.status.replace('_', ' ')}</span></td><td className="px-6 py-4">{invoice.status === 'verification_pending' ? <button type="button" onClick={() => router.put(route('subscription-invoices.verify', invoice.id))} className="text-green-600 hover:text-green-800">Verify Payment</button> : '-'}</td></tr>)}</tbody></table></div> : <p className="p-6 text-sm text-gray-600 dark:text-gray-300">No invoices match your filters.</p>}
                {invoices.links.length > 3 && <div className="flex flex-wrap gap-2 border-t border-gray-200 px-6 py-4 dark:border-gray-700">{invoices.links.map((link, index) => link.url ? <Link key={index} href={link.url} className={`rounded px-3 py-1 text-sm ${link.active ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200'}`} dangerouslySetInnerHTML={{ __html: link.label }} /> : <span key={index} className="px-3 py-1 text-sm text-gray-400" dangerouslySetInnerHTML={{ __html: link.label }} />)}</div>}
            </section>
        </AdminLayout>
    );
}
