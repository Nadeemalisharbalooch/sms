import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AdminLayout from '../Layouts/AdminLayout';

const options = [
    { value: 'light', title: 'Light', description: 'Use the light appearance.' },
    { value: 'dark', title: 'Dark', description: 'Use the dark appearance.' },
    { value: 'system', title: 'System', description: 'Match your device preference.' },
];

function applyTheme(theme) {
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    document.documentElement.classList.toggle('dark', theme === 'dark' || (theme === 'system' && prefersDark));
}

export default function Settings({ user }) {
    const [theme, setTheme] = useState(() => localStorage.getItem('theme') || 'system');

    useEffect(() => {
        applyTheme(theme);
        localStorage.setItem('theme', theme);

        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const handleChange = () => theme === 'system' && applyTheme(theme);
        media.addEventListener('change', handleChange);

        return () => media.removeEventListener('change', handleChange);
    }, [theme]);

    return (
        <AdminLayout user={user} title="System Settings" onLogout={() => router.post(route('logout'))}>
            <Head title="System Settings" />
            <section className="max-w-2xl rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
                <h2 className="text-base font-semibold text-gray-900 dark:text-white">Appearance</h2>
                <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">Choose how the admin panel looks on this device.</p>

                <div className="mt-6 grid gap-3 sm:grid-cols-3">
                    {options.map((option) => (
                        <button
                            key={option.value}
                            type="button"
                            onClick={() => setTheme(option.value)}
                            className={`rounded-lg border p-4 text-left transition ${theme === option.value ? 'border-indigo-600 bg-indigo-50 ring-1 ring-indigo-600 dark:border-indigo-400 dark:bg-gray-700' : 'border-gray-200 hover:border-gray-300 dark:border-gray-600 dark:hover:border-gray-500'}`}
                        >
                            <span className="block text-sm font-medium text-gray-900 dark:text-white">{option.title}</span>
                            <span className="mt-1 block text-xs text-gray-600 dark:text-gray-300">{option.description}</span>
                        </button>
                    ))}
                </div>
            </section>
        </AdminLayout>
    );
}
