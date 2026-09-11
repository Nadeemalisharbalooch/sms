import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../Layouts/AdminLayout';

const emptyPlan = {
    name: '', description: '', price: '0', billing_interval: 'monthly', trial_days: '30',
    student_limit: '', teacher_limit: '', class_limit: '', features: '', is_active: true,
};

export default function PlansIndex({ user, plans }) {
    const [editing, setEditing] = useState(null);
    const { data, setData, post, put, processing, errors, reset } = useForm(emptyPlan);

    const submit = (event) => {
        event.preventDefault();
        if (editing) {
            put(route('plans.update', editing.id), { onSuccess: closeForm });
            return;
        }
        post(route('plans.store'), { onSuccess: closeForm });
    };

    const closeForm = () => { setEditing(null); reset(); };
    const startEdit = (plan) => {
        setEditing(plan);
        setData({
            name: plan.name, description: plan.description || '', price: plan.price,
            billing_interval: plan.billing_interval, trial_days: String(plan.trial_days),
            student_limit: plan.student_limit || '', teacher_limit: plan.teacher_limit || '',
            class_limit: plan.class_limit || '', features: plan.features || '', is_active: plan.is_active,
        });
    };
    const remove = (plan) => window.confirm(`Delete ${plan.name}?`) && router.delete(route('plans.destroy', plan.id));

    return (
        <AdminLayout user={user} title="Plans & Packages" onLogout={() => router.post(route('logout.web'))}>
            <Head title="Plans & Packages" />
            <div className="grid gap-6 lg:grid-cols-[1fr_400px]">
                <section className="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-800">
                    <div className="border-b border-gray-200 px-6 py-4 dark:border-gray-700"><h2 className="font-semibold text-gray-900 dark:text-white">Available Plans</h2></div>
                    {plans.length ? <div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead className="bg-gray-50 text-gray-600 dark:bg-gray-700 dark:text-gray-300"><tr><th className="px-6 py-3">Plan</th><th className="px-6 py-3">Price</th><th className="px-6 py-3">Limits</th><th className="px-6 py-3">Status</th><th className="px-6 py-3">Actions</th></tr></thead><tbody className="divide-y divide-gray-100 dark:divide-gray-700">{plans.map((plan) => <tr key={plan.id} className="text-gray-700 dark:text-gray-200"><td className="px-6 py-4"><p className="font-medium">{plan.name}</p><p className="mt-1 text-xs text-gray-500">{plan.description || 'No description'}</p></td><td className="px-6 py-4">{plan.price} / {plan.billing_interval}</td><td className="px-6 py-4 text-xs">Students: {plan.student_limit || 'Unlimited'}<br />Teachers: {plan.teacher_limit || 'Unlimited'}<br />Classes: {plan.class_limit || 'Unlimited'}</td><td className="px-6 py-4">{plan.is_active ? 'Active' : 'Inactive'}</td><td className="space-x-3 whitespace-nowrap px-6 py-4"><button type="button" onClick={() => startEdit(plan)} className="text-indigo-600">Edit</button><button type="button" onClick={() => remove(plan)} className="text-red-600">Delete</button></td></tr>)}</tbody></table></div> : <p className="p-6 text-sm text-gray-600 dark:text-gray-300">Create your first plan, then assign it to an institute.</p>}
                </section>
                <form onSubmit={submit} className="h-fit rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
                    <div className="flex items-center justify-between"><h2 className="font-semibold text-gray-900 dark:text-white">{editing ? 'Edit Plan' : 'New Plan'}</h2>{editing && <button type="button" onClick={closeForm} className="text-sm">Cancel</button>}</div>
                    <Field label="Plan name" error={errors.name}><input value={data.name} onChange={(e) => setData('name', e.target.value)} required className="input" /></Field>
                    <Field label="Description" error={errors.description}><textarea value={data.description} onChange={(e) => setData('description', e.target.value)} className="input" rows="2" /></Field>
                    <div className="grid grid-cols-2 gap-3"><Field label="Price" error={errors.price}><input type="number" min="0" step="0.01" value={data.price} onChange={(e) => setData('price', e.target.value)} required className="input" /></Field><Field label="Billing" error={errors.billing_interval}><select value={data.billing_interval} onChange={(e) => setData('billing_interval', e.target.value)} className="input"><option value="monthly">Monthly</option><option value="yearly">Yearly</option></select></Field></div>
                    <Field label="Trial days" error={errors.trial_days}><input type="number" min="0" value={data.trial_days} onChange={(e) => setData('trial_days', e.target.value)} required className="input" /></Field>
                    <div className="grid grid-cols-3 gap-3"><Field label="Students" error={errors.student_limit}><input type="number" min="1" value={data.student_limit} onChange={(e) => setData('student_limit', e.target.value)} placeholder="Unlimited" className="input" /></Field><Field label="Teachers" error={errors.teacher_limit}><input type="number" min="1" value={data.teacher_limit} onChange={(e) => setData('teacher_limit', e.target.value)} placeholder="Unlimited" className="input" /></Field><Field label="Classes" error={errors.class_limit}><input type="number" min="1" value={data.class_limit} onChange={(e) => setData('class_limit', e.target.value)} placeholder="Unlimited" className="input" /></Field></div>
                    <Field label="Features" error={errors.features}><textarea value={data.features} onChange={(e) => setData('features', e.target.value)} placeholder="Attendance, Fees, Timetable..." className="input" rows="3" /></Field>
                    <label className="mt-4 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200"><input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} /> Active plan</label>
                    <button type="submit" disabled={processing} className="mt-5 w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50 dark:bg-indigo-600">{processing ? 'Saving...' : editing ? 'Update Plan' : 'Create Plan'}</button>
                </form>
            </div>
        </AdminLayout>
    );
}

function Field({ label, error, children }) {
    return <label className="mt-4 block text-sm font-medium text-gray-700 dark:text-gray-200">{label}{children}{error && <span className="mt-1 block text-xs text-red-600">{error}</span>}</label>;
}
