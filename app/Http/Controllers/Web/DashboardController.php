<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\FeePayment;
use App\Models\FeeVoucher;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Models\TimetableEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return Inertia::render('Dashboard', [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'kpis' => null,
                'recent_activities' => [],
            ]);
        }

        $sessionId = $this->activeSessionId($instituteId);

        if ($sessionId === null) {
            return Inertia::render('Dashboard', [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'kpis' => null,
                'recent_activities' => [],
            ]);
        }

        $today = Carbon::today();
        $currentMonth = Carbon::now()->startOfMonth();
        $previousMonth = Carbon::now()->subMonth()->startOfMonth();

        // General KPIs
        $totalStudents = Enrollment::query()
            ->where('session_id', $sessionId)
            ->count('student_id');

        $totalStaff = InstituteUser::query()
            ->where('institute_id', $instituteId)
            ->where('is_active', true)
            ->count('user_id');

        // Attendance
        $todayTotal = Attendance::query()
            ->where('session_id', $sessionId)
            ->where('date', $today)
            ->count();

        $todayPresent = Attendance::query()
            ->where('session_id', $sessionId)
            ->where('date', $today)
            ->whereIn('status', ['present', 'late'])
            ->count();

        $attendanceRate = $todayTotal > 0
            ? round(($todayPresent / $todayTotal) * 100, 1)
            : 0.0;

        // Classes Today
        $todayDayOfWeek = strtolower($today->englishDayOfWeek);
        $classesScheduledToday = TimetableEntry::query()
            ->where('session_id', $sessionId)
            ->where('day_of_week', $todayDayOfWeek)
            ->selectRaw('COUNT(DISTINCT CONCAT(class_id, "-", COALESCE(section_id, 0))) as unique_classes')
            ->value('unique_classes') ?? 0;

        // Financial KPIs
        $monthlyRevenue = FeePayment::query()
            ->where('institute_id', $instituteId)
            ->where('payment_date', '>=', $currentMonth)
            ->sum('amount_paid');

        $todayCollection = FeePayment::query()
            ->where('institute_id', $instituteId)
            ->where('payment_date', $today)
            ->sum('amount_paid');

        $outstandingArrears = FeeVoucher::query()
            ->where('institute_id', $instituteId)
            ->where('session_id', $sessionId)
            ->where('status', '!=', 'paid')
            ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as total_arrears')
            ->value('total_arrears') ?? 0;

        $totalDefaulters = FeeVoucher::query()
            ->where('institute_id', $instituteId)
            ->where('session_id', $sessionId)
            ->where('status', '!=', 'paid')
            ->distinct('student_id')
            ->count('student_id');

        // Recent Activities
        $recentActivities = $this->buildRecentActivities($instituteId, $sessionId);

        return Inertia::render('Dashboard', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'kpis' => [
                'total_students' => $totalStudents,
                'total_staff' => $totalStaff,
                'attendance_rate' => $attendanceRate,
                'classes_today' => $classesScheduledToday,
                'monthly_revenue' => (float) $monthlyRevenue,
                'today_collection' => (float) $todayCollection,
                'outstanding_arrears' => (float) $outstandingArrears,
                'total_defaulters' => $totalDefaulters,
            ],
            'recent_activities' => $recentActivities,
        ]);
    }

    private function activeInstituteId(Request $request): ?int
    {
        $instituteId = InstituteUser::query()
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->value('institute_id');

        return $instituteId === null ? null : (int) $instituteId;
    }

    private function activeSessionId(int $instituteId): ?int
    {
        $sessionId = AcademicSession::query()
            ->where('institute_id', $instituteId)
            ->where('is_active', true)
            ->value('id');

        return $sessionId === null ? null : (int) $sessionId;
    }

    private function buildRecentActivities(int $instituteId, int $sessionId): array
    {
        $activities = [];

        $recentPayments = FeePayment::query()
            ->where('institute_id', $instituteId)
            ->with(['feeVoucher.student'])
            ->latest('payment_date')
            ->limit(5)
            ->get();

        foreach ($recentPayments as $payment) {
            $studentName = $payment->feeVoucher?->student
                ? trim($payment->feeVoucher->student->first_name . ' ' . $payment->feeVoucher->student->last_name)
                : 'Unknown Student';

            $activities[] = [
                'id' => 'act_pay_' . $payment->id,
                'type' => 'payment',
                'title' => 'Fee Collected',
                'description' => number_format($payment->amount_paid, 0) . ' PKR collected from ' . $studentName,
                'timestamp' => $payment->payment_date->toIso8601String(),
            ];
        }

        $recentEnrollments = Enrollment::query()
            ->where('session_id', $sessionId)
            ->with(['student', 'academicClass'])
            ->latest('created_at')
            ->limit(5)
            ->get();

        foreach ($recentEnrollments as $enrollment) {
            $studentName = $enrollment->student
                ? trim($enrollment->student->first_name . ' ' . $enrollment->student->last_name)
                : 'Unknown Student';
            $className = $enrollment->academicClass?->name ?? 'Unknown Class';

            $activities[] = [
                'id' => 'act_adm_' . $enrollment->id,
                'type' => 'admission',
                'title' => 'New Admission',
                'description' => $studentName . ' enrolled in ' . $className,
                'timestamp' => $enrollment->created_at->toIso8601String(),
            ];
        }

        usort($activities, fn ($a, $b) => strcmp($b['timestamp'], $a['timestamp']));

        return array_slice($activities, 0, 10);
    }
}
