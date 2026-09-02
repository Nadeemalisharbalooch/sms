import { Head, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

function KpiCard({ title, value, icon, color, suffix = '' }) {
    return (
        <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 hover:shadow-md transition-shadow">
            <div className="flex items-center justify-between">
                <div>
                    <p className="text-sm font-medium text-gray-500">{title}</p>
                    <p className="text-2xl font-bold text-gray-900 mt-1">
                        {suffix === 'PKR' && 'PKR '}{typeof value === 'number' ? value.toLocaleString() : value}{suffix && suffix !== 'PKR' ? ` ${suffix}` : ''}
                    </p>
                </div>
                <div className={`w-12 h-12 rounded-xl flex items-center justify-center ${color}`}>
                    <span className="text-xl">{icon}</span>
                </div>
            </div>
        </div>
    );
}

function ActivityItem({ activity }) {
    const icons = {
        payment: '💰',
        admission: '🎓',
        attendance: '📋',
    };

    const colors = {
        payment: 'bg-green-100',
        admission: 'bg-blue-100',
        attendance: 'bg-yellow-100',
    };

    return (
        <div className="flex items-start gap-3 py-3 border-b border-gray-50 last:border-0">
            <div className={`w-10 h-10 rounded-lg flex items-center justify-center ${colors[activity.type] || 'bg-gray-100'} shrink-0`}>
                <span>{icons[activity.type] || '📌'}</span>
            </div>
            <div className="flex-1 min-w-0">
                <p className="text-sm font-medium text-gray-900">{activity.title}</p>
                <p className="text-sm text-gray-500 truncate">{activity.description}</p>
            </div>
            <span className="text-xs text-gray-400 shrink-0 mt-0.5">
                {new Date(activity.timestamp).toLocaleDateString()}
            </span>
        </div>
    );
}

export default function Dashboard({ user, kpis, recent_activities }) {
    const handleLogout = () => {
        router.post(route('logout'));
    };

    return (
        <AdminLayout user={user} title="Dashboard" onLogout={handleLogout}>
            <Head title="Dashboard" />

            <div className="space-y-6">
                {/* Welcome Banner */}
                <div className="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-2xl p-6 text-white">
                    <h1 className="text-2xl font-bold">Welcome back, {user.name}! 👋</h1>
                    <p className="text-indigo-100 mt-1">Here's what's happening at your institute today.</p>
                </div>

                {kpis ? (
                    <>
                        {/* General KPIs */}
                        <div>
                            <h2 className="text-lg font-semibold text-gray-900 mb-3">📊 General Overview</h2>
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                <KpiCard
                                    title="Total Students"
                                    value={kpis.total_students}
                                    icon="🎓"
                                    color="bg-blue-100"
                                />
                                <KpiCard
                                    title="Total Staff"
                                    value={kpis.total_staff}
                                    icon="👥"
                                    color="bg-purple-100"
                                />
                                <KpiCard
                                    title="Attendance Rate"
                                    value={kpis.attendance_rate}
                                    icon="📋"
                                    color="bg-green-100"
                                    suffix="%"
                                />
                                <KpiCard
                                    title="Classes Today"
                                    value={kpis.classes_today}
                                    icon="🏫"
                                    color="bg-orange-100"
                                />
                            </div>
                        </div>

                        {/* Financial KPIs */}
                        <div>
                            <h2 className="text-lg font-semibold text-gray-900 mb-3">💰 Financial Overview</h2>
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                <KpiCard
                                    title="Monthly Revenue"
                                    value={kpis.monthly_revenue}
                                    icon="📈"
                                    color="bg-emerald-100"
                                    suffix="PKR"
                                />
                                <KpiCard
                                    title="Today's Collection"
                                    value={kpis.today_collection}
                                    icon="💵"
                                    color="bg-teal-100"
                                    suffix="PKR"
                                />
                                <KpiCard
                                    title="Outstanding Arrears"
                                    value={kpis.outstanding_arrears}
                                    icon="⚠️"
                                    color="bg-red-100"
                                    suffix="PKR"
                                />
                                <KpiCard
                                    title="Total Defaulters"
                                    value={kpis.total_defaulters}
                                    icon="👤"
                                    color="bg-amber-100"
                                />
                            </div>
                        </div>
                    </>
                ) : (
                    <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 text-center">
                        <p className="text-gray-500">No institute data available. Please contact your administrator.</p>
                    </div>
                )}

                {/* Recent Activities */}
                <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                    <h2 className="text-lg font-semibold text-gray-900 mb-4">🕐 Recent Activities</h2>
                    {recent_activities && recent_activities.length > 0 ? (
                        <div>
                            {recent_activities.map((activity) => (
                                <ActivityItem key={activity.id} activity={activity} />
                            ))}
                        </div>
                    ) : (
                        <p className="text-gray-400 text-sm py-4 text-center">No recent activities</p>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
