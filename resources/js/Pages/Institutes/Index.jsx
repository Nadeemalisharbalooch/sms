import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../Layouts/AdminLayout';

const emptyInstitute = {
    name: '',
    email: '',
    phone: '',
    address: '',
    logo: null,
    favicon: null,
    attendance_mode: 'class',
    is_active: true,
    // User fields
    user_name: '',
    user_email: '',
    user_phone: '',
    user_password: '',
    plan_id: '',
    subscription_status: 'trialing',
};

export default function InstitutesIndex({ user, institutes, plans }) {
    const [editing, setEditing] = useState(null);
    const { data, setData, post, put, processing, errors, reset } = useForm(emptyInstitute);

    function submit(event) {
        event.preventDefault();

        if (editing) {
            put(route('institute.update', editing.public_id), { onSuccess: closeForm });
            return;
        }

        post(route('institute.store'), { onSuccess: closeForm });
    }

    function startEdit(institute) {
        setEditing(institute);
        setData({
            name: institute.name || '',
            email: institute.email || '',
            phone: institute.phone || '',
            address: institute.address || '',
            // File inputs cannot be pre-populated. Keep existing uploads unless a
            // replacement file is selected.
            logo: null,
            favicon: null,
            attendance_mode: institute.attendance_mode || 'class',
            is_active: institute.is_active ?? true,
            user_name: institute.owner?.name || '',
            user_email: institute.owner?.email || '',
            user_phone: institute.owner?.phone || '',
            user_password: '',
            plan_id: institute.subscription?.plan_id ? String(institute.subscription.plan_id) : '',
            subscription_status: institute.subscription?.status || 'trialing',
        });
    }

    function closeForm() {
        setEditing(null);
        reset();
    }

    function show(institute) {
        // Add your show logic here - e.g., show modal or navigate to detail page
        alert(`Viewing ${institute.name}\nEmail: ${institute.email}\nPhone: ${institute.phone}\nAddress: ${institute.address}`);
    }

    function remove(institute) {
        if (window.confirm(`Delete ${institute.name}?`)) {
            router.delete(route('institute.destroy', institute.public_id));
        }
    }

    return (
        <AdminLayout user={user} title="Institutes" onLogout={() => router.post(route('logout.web'))}>
            <Head title="Institutes" />
            <div className="grid gap-6 lg:grid-cols-[1fr_360px]">
                <section className="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-800">
                    <div className="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                        <h2 className="font-semibold text-gray-900 dark:text-white">All Institutes</h2>
                    </div>
                    {institutes.length ? (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-gray-50 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                    <tr><th className="px-6 py-3">Logo</th><th className="px-6 py-3">Name</th><th className="px-6 py-3">Email</th><th className="px-6 py-3">Plan</th><th className="px-6 py-3">Invoice</th><th className="px-6 py-3">Mode</th><th className="px-6 py-3">Status</th><th className="px-6 py-3">Actions</th></tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                    {institutes.map((institute) => (
                                        <tr key={institute.public_id} className="text-gray-700 dark:text-gray-200">
                                            <td className="px-6 py-4">
                                                {institute.logo ? <img src={institute.logo} alt="logo" className="h-8 w-8 rounded object-cover" /> : <span className="text-gray-400 text-xs">No logo</span>}
                                            </td>
                                            <td className="px-6 py-4 font-medium">{institute.name}</td>
                                            <td className="px-6 py-4">{institute.email || '-'}</td>
                                            <td className="px-6 py-4"><p>{institute.subscription?.plan?.name || '-'}</p>{institute.subscription && <p className="mt-1 text-xs capitalize text-gray-500">{institute.subscription.status}</p>}</td>
                                            <td className="px-6 py-4 text-xs">
                                                {institute.subscription?.invoice ? <>
                                                    <p className="font-medium text-gray-700 dark:text-gray-200">{institute.subscription.invoice.invoice_number}</p>
                                                    <p className="mt-1">PKR {institute.subscription.invoice.amount}</p>
                                                    <p className="mt-1 capitalize text-gray-500">{institute.subscription.invoice.status.replace('_', ' ')}</p>
                                                    {institute.subscription.invoice.due_date && <p className="mt-1 text-gray-500">Due: {institute.subscription.invoice.due_date}</p>}
                                                    {institute.subscription.invoice.payment_submitted_at && <p className="mt-1 text-gray-500">Submitted: {new Date(institute.subscription.invoice.payment_submitted_at).toLocaleString()}</p>}
                                                    {institute.subscription.invoice.payment_method && <p className="mt-1 capitalize text-gray-500">Method: {institute.subscription.invoice.payment_method}</p>}
                                                    {institute.subscription.invoice.payment_reference && <p className="mt-1 break-all text-gray-500">Reference: {institute.subscription.invoice.payment_reference}</p>}
                                                    {institute.subscription.invoice.notes && <p className="mt-1 break-words text-gray-500">Note: {institute.subscription.invoice.notes}</p>}
                                                    {institute.subscription.invoice.payment_screenshot_url && <a href={institute.subscription.invoice.payment_screenshot_url} target="_blank" rel="noreferrer" className="mt-1 inline-block text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">View screenshot</a>}
                                                </> : <span className="text-gray-400">No invoice</span>}
                                            </td>
                                            <td className="px-6 py-4 capitalize">{institute.attendance_mode}</td>
                                            <td className="px-6 py-4">
                                                <span className={"px-2 py-1 text-xs rounded " + (institute.is_active ? "bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300" : "bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300")}>
                                                    {institute.is_active ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                            <td className="space-x-3 px-6 py-4 whitespace-nowrap">
                                                <button type="button" onClick={() => show(institute)} className="text-blue-600 hover:text-blue-800 dark:text-blue-400">Show</button>
                                                <button type="button" onClick={() => startEdit(institute)} className="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">Edit</button>
                                                {institute.subscription?.invoice?.status === 'verification_pending' && <button type="button" onClick={() => router.put(route('subscription-invoices.verify', institute.subscription.invoice.id))} className="text-green-600 hover:text-green-800 dark:text-green-400">Verify Payment</button>}
                                                <button type="button" onClick={() => remove(institute)} className="text-red-600 hover:text-red-800 dark:text-red-400">Delete</button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : <p className="p-6 text-sm text-gray-600 dark:text-gray-300">No institutes have been added yet.</p>}
                </section>

                <form onSubmit={submit} className="h-fit rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
                    <div className="flex items-center justify-between">
                        <h2 className="font-semibold text-gray-900 dark:text-white">{editing ? 'Edit Institute' : 'New Institute'}</h2>
                        {editing && <button type="button" onClick={closeForm} className="text-sm text-gray-600 dark:text-gray-300">Cancel</button>}
                    </div>
                    <Field label="Name" error={errors.name}><input value={data.name} onChange={(event) => setData('name', event.target.value)} required className="input" /></Field>
                    <Field label="Email" error={errors.email}><input type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} className="input" /></Field>
                    <Field label="Phone" error={errors.phone}><input value={data.phone} onChange={(event) => setData('phone', event.target.value)} className="input" /></Field>
                    <Field label="Address" error={errors.address}><textarea value={data.address} onChange={(event) => setData('address', event.target.value)} className="input" rows="3" /></Field>
                    <Field label="Logo" error={errors.logo}><input type="file" accept="image/*" onChange={(event) => setData('logo', event.target.files[0] || null)} className="input" /></Field>
                    <Field label="Favicon" error={errors.favicon}><input type="file" accept="image/*" onChange={(event) => setData('favicon', event.target.files[0] || null)} className="input" /></Field>
                    <Field label="Attendance mode" error={errors.attendance_mode}><select value={data.attendance_mode} onChange={(event) => setData('attendance_mode', event.target.value)} className="input"><option value="class">Class</option><option value="subject">Subject</option></select></Field>
                    <div className="hidden">
                        <h3 className="mb-4 text-sm font-medium text-gray-700 dark:text-gray-200">Package & Subscription</h3>
                        <Field label="Plan" error={errors.plan_id}>
                            <select value={data.plan_id} onChange={(event) => setData('plan_id', event.target.value)} className="input">
                                <option value="">No plan assigned</option>
                                {plans.map((plan) => <option key={plan.id} value={plan.id}>{plan.name} — {plan.price}/{plan.billing_interval}</option>)}
                            </select>
                        </Field>
                        <Field label="Subscription status" error={errors.subscription_status}>
                            <select value={data.subscription_status} onChange={(event) => setData('subscription_status', event.target.value)} className="input" disabled={!data.plan_id}>
                                <option value="trialing">Trial</option>
                                <option value="active">Active / Approved</option>
                                <option value="expired">Expired</option>
                                <option value="canceled">Canceled</option>
                            </select>
                        </Field>
                    </div>
                    <div className="mt-6 border-t border-gray-200 dark:border-gray-700 pt-6">
                        <h3 className="mb-4 text-sm font-medium text-gray-700 dark:text-gray-200">Institute User</h3>
                        <Field label="User Name" error={errors.user_name}><input value={data.user_name} onChange={(event) => setData('user_name', event.target.value)} className="input" placeholder="Same as institute name" /></Field>
                        <Field label="User Email" error={errors.user_email}><input type="email" value={data.user_email} onChange={(event) => setData('user_email', event.target.value)} className="input" placeholder="Same as institute email" /></Field>
                        <Field label="User Phone" error={errors.user_phone}><input value={data.user_phone} onChange={(event) => setData('user_phone', event.target.value)} className="input" placeholder="Same as institute phone" /></Field>
                        <Field label={editing ? 'New Password (optional)' : 'User Password'} error={errors.user_password}><input type="password" value={data.user_password} onChange={(event) => setData('user_password', event.target.value)} className="input" required={!editing} /></Field>
                    </div>
                    <button type="submit" disabled={processing} className="mt-5 w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50 dark:bg-indigo-600 dark:hover:bg-indigo-500">{processing ? 'Saving...' : editing ? 'Update Institute' : 'Create Institute'}</button>
                </form>
            </div>
        </AdminLayout>
    );
}

function Field({ label, error, children }) {
    return <label className="mt-4 block text-sm font-medium text-gray-700 dark:text-gray-200">{label}{children}{error && <span className="mt-1 block text-xs text-red-600">{error}</span>}</label>;
}
