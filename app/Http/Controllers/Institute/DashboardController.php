<?php

namespace App\Http\Controllers\Institute;

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
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $sessionId = $request->integer('session_id') ?: $this->activeSessionId($instituteId);

        if ($sessionId === null) {
            return ResponseService::error('Validation failed', 422, [
                'session_id' => ['No active academic session exists for the active institute.'],
            ]);
        }

        $activityLimit = min(50, max(1, $request->integer('activity_limit', 5)));

        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $currentMonth = Carbon::now()->startOfMonth();
        $previousMonth = Carbon::now()->subMonth()->startOfMonth();

        // ── General KPIs ────────────────────────────────────────────────

        // Total Students: enrolled in the given session
        $totalStudents = Enrollment::query()
            ->where('session_id', $sessionId)
            ->count('student_id');

        // Previous period count for trend (previous month's enrollment snapshot)
        $previousMonthStudents = Enrollment::query()
            ->where('session_id', $sessionId)
            ->where('created_at', '<', $currentMonth)
            ->count('student_id');

        $studentTrend = $this->calculateTrend($totalStudents, $previousMonthStudents);

        // Total Staff: active users attached to this institute
        $totalStaff = InstituteUser::query()
            ->where('institute_id', $instituteId)
            ->where('is_active', true)
            ->count('user_id');

        // Today's Student Attendance %
        $todayTotalAttendance = Attendance::query()
            ->where('session_id', $sessionId)
            ->where('date', $today)
            ->count();

        $todayPresentAttendance = Attendance::query()
            ->where('session_id', $sessionId)
            ->where('date', $today)
            ->whereIn('status', ['present', 'late'])
            ->count();

        $todayAttendanceRate = $todayTotalAttendance > 0
            ? round(($todayPresentAttendance / $todayTotalAttendance) * 100, 1)
            : 0.0;

        // Yesterday's attendance for trend
        $yesterdayTotalAttendance = Attendance::query()
            ->where('session_id', $sessionId)
            ->where('date', $yesterday)
            ->count();

        $yesterdayPresentAttendance = Attendance::query()
            ->where('session_id', $sessionId)
            ->where('date', $yesterday)
            ->whereIn('status', ['present', 'late'])
            ->count();

        $yesterdayAttendanceRate = $yesterdayTotalAttendance > 0
            ? round(($yesterdayPresentAttendance / $yesterdayTotalAttendance) * 100, 1)
            : 0.0;

        $attendanceTrend = $this->calculateTrend($todayAttendanceRate, $yesterdayAttendanceRate);

        // Classes Scheduled Today
        $todayDayOfWeek = strtolower($today->englishDayOfWeek);
        $classesScheduledToday = TimetableEntry::query()
            ->where('session_id', $sessionId)
            ->where('day_of_week', $todayDayOfWeek)
            ->selectRaw('COUNT(DISTINCT CONCAT(class_id, "-", COALESCE(section_id, 0))) as unique_classes')
            ->value('unique_classes') ?? 0;

        // ── Financial KPIs ──────────────────────────────────────────────

        // Monthly Revenue (current month)
        $monthlyRevenue = FeePayment::query()
            ->where('institute_id', $instituteId)
            ->where('payment_date', '>=', $currentMonth)
            ->where('payment_date', '<', $currentMonth->copy()->addMonth())
            ->sum('amount_paid');

        // Previous month revenue for trend
        $previousMonthRevenue = FeePayment::query()
            ->where('institute_id', $instituteId)
            ->where('payment_date', '>=', $previousMonth)
            ->where('payment_date', '<', $currentMonth)
            ->sum('amount_paid');

        $revenueTrend = $this->calculateTrend((float) $monthlyRevenue, (float) $previousMonthRevenue);

        // Today's Collection
        $todayCollection = FeePayment::query()
            ->where('institute_id', $instituteId)
            ->where('payment_date', $today)
            ->sum('amount_paid');

        // Outstanding Arrears (total unpaid balance across all vouchers in session)
        $outstandingArrears = FeeVoucher::query()
            ->where('institute_id', $instituteId)
            ->where('session_id', $sessionId)
            ->where('status', '!=', 'paid')
            ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as total_arrears')
            ->value('total_arrears') ?? 0;

        // Total Defaulters (students with at least one unpaid/partial voucher)
        $totalDefaulters = FeeVoucher::query()
            ->where('institute_id', $instituteId)
            ->where('session_id', $sessionId)
            ->where('status', '!=', 'paid')
            ->distinct('student_id')
            ->count('student_id');

        // ── Revenue Chart (Last 6 months) ───────────────────────────────

        $revenueChart = $this->buildRevenueChart($instituteId);

        // ── Recent Activities ───────────────────────────────────────────

        $recentActivities = $this->buildRecentActivities($instituteId, $sessionId, $activityLimit);

        return ResponseService::success([
            'kpis' => [
                'general' => [
                    'total_students' => [
                        'value' => $totalStudents,
                        'trend_percentage' => $studentTrend['percentage'],
                        'trend_direction' => $studentTrend['direction'],
                    ],
                    'total_staff' => [
                        'value' => $totalStaff,
                    ],
                    'today_student_attendance' => [
                        'value' => $todayAttendanceRate,
                        'trend_percentage' => $attendanceTrend['percentage'],
                        'trend_direction' => $attendanceTrend['direction'],
                    ],
                    'classes_scheduled_today' => [
                        'value' => $classesScheduledToday,
                    ],
                ],
                'financials' => [
                    'monthly_revenue' => [
                        'value' => (float) $monthlyRevenue,
                        'trend_percentage' => $revenueTrend['percentage'],
                        'trend_direction' => $revenueTrend['direction'],
                    ],
                    'today_collection' => [
                        'value' => (float) $todayCollection,
                    ],
                    'outstanding_arrears' => [
                        'value' => (float) $outstandingArrears,
                    ],
                    'total_defaulters' => [
                        'value' => $totalDefaulters,
                    ],
                ],
            ],
            'revenue_chart' => $revenueChart,
            'recent_activities' => $recentActivities,
        ], 'Dashboard summary retrieved successfully');
    }

    // ── Private Helpers ─────────────────────────────────────────────────

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

    /**
     * Calculate trend between current and previous values.
     *
     * @return array{percentage: float|null, direction: string}
     */
    private function calculateTrend(float $current, float $previous): array
    {
        if ($previous == 0) {
            return [
                'percentage' => $current > 0 ? 100.0 : 0.0,
                'direction' => $current > 0 ? 'up' : 'neutral',
            ];
        }

        $percentage = round((($current - $previous) / abs($previous)) * 100, 1);

        if ($percentage > 0) {
            $direction = 'up';
        } elseif ($percentage < 0) {
            $direction = 'down';
        } else {
            $direction = 'neutral';
        }

        return [
            'percentage' => abs($percentage),
            'direction' => $direction,
        ];
    }

    /**
     * Build revenue chart for the last 6 months (including current month).
     *
     * @return array<int, array{month: string, collected: float}>
     */
    private function buildRevenueChart(int $instituteId): array
    {
        $now = Carbon::now();
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = $now->copy()->subMonths($i);
            $months[] = [
                'month' => $month->format('M'),
                'start' => $month->copy()->startOfMonth(),
                'end' => $month->copy()->endOfMonth(),
            ];
        }

        $chart = [];
        foreach ($months as $m) {
            $collected = FeePayment::query()
                ->where('institute_id', $instituteId)
                ->where('payment_date', '>=', $m['start'])
                ->where('payment_date', '<=', $m['end'])
                ->sum('amount_paid');

            $chart[] = [
                'month' => $m['month'],
                'collected' => (float) $collected,
            ];
        }

        return $chart;
    }

    /**
     * Build recent activities from payments and admissions.
     *
     * @return array<int, array{id: string, type: string, title: string, description: string, timestamp: string}>
     */
    private function buildRecentActivities(int $instituteId, int $sessionId, int $limit): array
    {
        $activities = [];

        // Recent fee payments
        $recentPayments = FeePayment::query()
            ->where('institute_id', $instituteId)
            ->with(['feeVoucher.student'])
            ->latest('payment_date')
            ->limit($limit)
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

        // Recent admissions (enrollments created in current session)
        $recentEnrollments = Enrollment::query()
            ->where('session_id', $sessionId)
            ->with(['student', 'academicClass'])
            ->latest('created_at')
            ->limit($limit)
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

        // Recent attendance activity (today's marking summary)
        $today = Carbon::today();
        $todayAttendanceMarked = Attendance::query()
            ->where('session_id', $sessionId)
            ->where('date', $today)
            ->latest('created_at')
            ->first();

        if ($todayAttendanceMarked !== null) {
            $markedBy = User::find($todayAttendanceMarked->marked_by_user_id);
            $totalMarkedToday = Attendance::query()
                ->where('session_id', $sessionId)
                ->where('date', $today)
                ->count();

            $activities[] = [
                'id' => 'act_att_' . $todayAttendanceMarked->id,
                'type' => 'attendance',
                'title' => 'Attendance Marked',
                'description' => $totalMarkedToday . ' student attendance records marked for ' . $today->format('M d'),
                'timestamp' => $todayAttendanceMarked->created_at->toIso8601String(),
            ];
        }

        // Sort by timestamp descending and limit
        usort($activities, fn ($a, $b) => strcmp($b['timestamp'], $a['timestamp']));

        return array_slice($activities, 0, $limit);
    }
}
