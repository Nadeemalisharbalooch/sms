import { Head, useForm } from '@inertiajs/react';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({ email: '', password: '', remember: false });

    function submit(event) {
        event.preventDefault();
        post(route('login.store'));
    }

    return (
        <>
            <Head title="Log in" />
            <main className="min-h-screen bg-gray-100 flex items-center justify-center p-6">
                <form onSubmit={submit} className="w-full max-w-sm rounded-lg bg-white p-6 shadow-sm">
                    <h1 className="text-xl font-semibold text-gray-900">Log in</h1>
                    <p className="mt-1 text-sm text-gray-600">Enter your account details to continue.</p>
                    <div className="mt-6">
                        <label htmlFor="email" className="block text-sm font-medium text-gray-700">Email</label>
                        <input id="email" type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" autoComplete="username" autoFocus required />
                        {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
                    </div>
                    <div className="mt-4">
                        <label htmlFor="password" className="block text-sm font-medium text-gray-700">Password</label>
                        <input id="password" type="password" value={data.password} onChange={(event) => setData('password', event.target.value)} className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" autoComplete="current-password" required />
                    </div>
                    <label className="mt-4 flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" checked={data.remember} onChange={(event) => setData('remember', event.target.checked)} className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        Remember me
                    </label>
                    <button type="submit" disabled={processing} className="mt-6 w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50">
                        {processing ? 'Logging in…' : 'Log in'}
                    </button>
                </form>
            </main>
        </>
    );
}
