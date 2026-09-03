import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../Layouts/AdminLayout';

const emptyInstitute = {
    name: '',
    email: '',
    phone: '',
    address: '',
    attendance_mode: 'class',
};

export default function InstitutesIndex({ user, institutes }) {
    const [editing, setEditing] = useState(null);
    const { data, setData, post, put, processing, errors, reset } = useForm(emptyInstitute);

    function submit(event) {
        event.preventDefault();

        if (editing) {
            put(route('institutes.update', editing.public_id), { onSuccess: closeForm });
            return;
        }

        post(route('institutes.store'), { onSuccess: closeForm });
    }

    function startEdit(institute) {
        setEditing(institute);
        setData({
            name: institute.name || '',
            email: institute.email || '',
            phone: institute.phone || '',
            address: institute.address || '',
            attendance_mode: institute.attendance_mode || 'class',
        });
    }

    function closeForm() {
        setEditing(null);
        reset();
    }

    function remove(institute) {
        if (window.confirm(`Delete ${institute.name}?`)) {
            router.delete(route('institutes.destroy', institute.public_id));
        }
    }

    return (
        <AdminLayout user={user} title="Institutes" onLogout={() => router.post(route('logout'))}>
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
                                    <tr><th className="px-6 py-3">Name</th><th className="px-6 py-3">Email</th><th className="px-6 py-3">Mode</th><th className="px-6 py-3">Actions</th></tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                    {institutes.map((institute) => (
                                        <tr key={institute.public_id} className="text-gray-700 dark:text-gray-200">
                                            <td className="px-6 py-4 font-medium">{institute.name}</td>
                                            <td className="px-6 py-4">{institute.email || '-'}</td>
                                            <td className="px-6 py-4 capitalize">{institute.attendance_mode}</td>
                                            <td className="space-x-3 px-6 py-4 whitespace-nowrap">
                                                <button type="button" onClick={() => startEdit(institute)} className="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">Edit</button>
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
                    <Field label="Attendance mode" error={errors.attendance_mode}><select value={data.attendance_mode} onChange={(event) => setData('attendance_mode', event.target.value)} className="input"><option value="class">Class</option><option value="subject">Subject</option></select></Field>
                    <button type="submit" disabled={processing} className="mt-5 w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50 dark:bg-indigo-600 dark:hover:bg-indigo-500">{processing ? 'Saving...' : editing ? 'Update Institute' : 'Create Institute'}</button>
                </form>
            </div>
        </AdminLayout>
    );
}

function Field({ label, error, children }) {
    return <label className="mt-4 block text-sm font-medium text-gray-700 dark:text-gray-200">{label}{children}{error && <span className="mt-1 block text-xs text-red-600">{error}</span>}</label>;
}