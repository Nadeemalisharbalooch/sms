<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\FeePayment;
use App\Models\FeeVoucher;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FeeReportController extends Controller
{
    public function collections(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['required', 'integer'], 'date_from' => ['required', 'date_format:Y-m-d'], 'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'payment_method' => ['nullable', 'in:cash,bank,easypaisa'], 'cashier_id' => ['nullable', 'integer'],
        ]);
        $instituteId = $this->instituteId($request);
        if (($error = $this->sessionError($data['session_id'], $instituteId)) !== null) return $error;

        $query = FeePayment::query()->where('institute_id', $instituteId)->whereHas('feeVoucher', fn ($q) => $q->where('session_id', $data['session_id']))
            ->whereBetween('payment_date', [$data['date_from'], $data['date_to']])
            ->when($data['payment_method'] ?? null, fn ($q, $v) => $q->where('payment_method', $v))
            ->when($data['cashier_id'] ?? null, fn ($q, $v) => $q->where('collected_by_user_id', $v))
            ->with(['feeVoucher.student.enrollments' => fn ($q) => $q->where('session_id', $data['session_id'])->with(['academicClass', 'section']), 'collectedBy']);
        $payments = $query->orderBy('payment_date')->orderBy('id')->get();
        $byMethod = $payments->groupBy('payment_method')->map(fn ($rows) => round((float) $rows->sum('amount_paid'), 2));
        foreach (['cash', 'bank', 'easypaisa'] as $method) $byMethod->put($method, (float) $byMethod->get($method, 0));

        return ResponseService::success([
            'summary' => ['total_collected' => round((float) $payments->sum('amount_paid'), 2), 'by_method' => $byMethod],
            'transactions' => $payments->map(function (FeePayment $payment) {
                $voucher = $payment->feeVoucher; $student = $voucher?->student; $enrollment = $student?->enrollments?->first();
                return ['receipt_id' => $payment->id, 'receipt_no' => $this->receiptNo($payment), 'student_name' => trim(($student?->first_name ?? '').' '.($student?->last_name ?? '')),
                    'roll_number' => $enrollment?->roll_number, 'class_name' => $enrollment?->academicClass ? trim($enrollment->academicClass->name.' - '.($enrollment->section?->name ?? '')) : null,
                    'amount' => round((float) $payment->amount_paid, 2), 'payment_method' => $payment->payment_method,
                    'collected_at' => $payment->created_at?->toISOString(), 'cashier_name' => $payment->collectedBy?->name];
            })->values(),
        ], 'Collections retrieved successfully');
    }

    public function defaulters(Request $request): JsonResponse
    {
        $data = $request->validate(['session_id' => ['required', 'integer'], 'class_id' => ['nullable', 'integer'], 'section_id' => ['nullable', 'integer'], 'min_arrears' => ['nullable', 'numeric', 'min:0'], 'month' => ['nullable', 'date_format:Y-m']]);
        $instituteId = $this->instituteId($request);
        if (($error = $this->sessionError($data['session_id'], $instituteId)) !== null) return $error;
        if (($error = $this->scopeFilterError($data, $instituteId)) !== null) return $error;
        $month = $data['month'] ?? now()->format('Y-m');
        $vouchers = FeeVoucher::query()->where('institute_id', $instituteId)->where('session_id', $data['session_id'])->where('billing_month', '<=', $month)
            ->whereRaw('(total_amount - paid_amount) > ?', [$data['min_arrears'] ?? 0])
            ->with(['student.enrollments' => fn ($q) => $q->where('session_id', $data['session_id'])->with(['academicClass', 'section']), 'payments'])
            ->when(isset($data['class_id']) || isset($data['section_id']), fn ($q) => $q->whereHas('student.enrollments', function ($e) use ($data) {
                $e->where('session_id', $data['session_id'])->when(isset($data['class_id']), fn ($e) => $e->where('class_id', $data['class_id']))->when(isset($data['section_id']), fn ($e) => $e->where('section_id', $data['section_id']));
            }))->get();
        $records = $vouchers->groupBy('student_id')->map(function ($rows) {
            $student = $rows->first()->student; $enrollment = $student?->enrollments?->first();
            $lastPaid = $rows->flatMap(fn ($voucher) => $voucher->payments)->pluck('payment_date')->filter()->max();
            return ['student_id' => $student->id, 'roll_number' => $enrollment?->roll_number, 'full_name' => trim($student->first_name.' '.$student->last_name),
                'class_name' => $enrollment?->academicClass?->name, 'section_name' => $enrollment?->section?->name, 'guardian_name' => $student->guardian_name,
                'guardian_phone' => $student->guardian_phone, 'pending_months' => $rows->count(), 'total_due' => round((float) $rows->sum(fn ($v) => $v->total_amount - $v->paid_amount), 2),
                'last_paid_date' => $lastPaid ? Carbon::parse($lastPaid)->toDateString() : null];
        })->values();
        return ResponseService::success(['summary' => ['total_defaulters' => $records->count(), 'total_arrears_amount' => round((float) $records->sum('total_due'), 2)], 'records' => $records], 'Defaulters list retrieved successfully');
    }

    public function classRecovery(Request $request): JsonResponse
    {
        $data = $request->validate(['session_id' => ['required', 'integer'], 'month' => ['required', 'date_format:Y-m']]);
        $instituteId = $this->instituteId($request);
        if (($error = $this->sessionError($data['session_id'], $instituteId)) !== null) return $error;
        $rows = FeeVoucher::query()->where('institute_id', $instituteId)->where('session_id', $data['session_id'])->where('billing_month', $data['month'])->with(['student.enrollments' => fn ($q) => $q->where('session_id', $data['session_id'])->with('academicClass')])->get();
        $classes = $rows->groupBy(fn ($v) => $v->student?->enrollments?->first()?->class_id)->map(function ($vouchers) {
            $class = $vouchers->first()->student?->enrollments?->first()?->academicClass;
            $billed = (float) $vouchers->sum('total_amount'); $paid = (float) $vouchers->sum('paid_amount');
            return ['class_id' => $class?->id, 'class_name' => $class?->name, 'total_students' => $vouchers->pluck('student_id')->unique()->count(), 'net_receivable' => round($billed, 2), 'collected' => round($paid, 2), 'pending' => round($billed - $paid, 2), 'recovery_percentage' => $billed > 0 ? round($paid / $billed * 100, 2) : 0.0];
        })->filter(fn ($row) => $row['class_id'] !== null)->values();
        $billed = (float) $rows->sum('total_amount'); $paid = (float) $rows->sum('paid_amount');
        return ResponseService::success(['month' => $data['month'], 'overall' => ['total_billed' => round($billed, 2), 'total_concessions' => 0.0, 'net_receivable' => round($billed, 2), 'total_collected' => round($paid, 2), 'total_pending' => round($billed - $paid, 2), 'recovery_percentage' => $billed > 0 ? round($paid / $billed * 100, 2) : 0.0], 'classes' => $classes], 'Class recovery summary retrieved successfully');
    }

    public function categorySummary(Request $request): JsonResponse
    {
        $data = $request->validate(['session_id' => ['required', 'integer'], 'date_from' => ['required', 'date_format:Y-m-d'], 'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from']]);
        $instituteId = $this->instituteId($request);
        if (($error = $this->sessionError($data['session_id'], $instituteId)) !== null) return $error;
        $billedByHead = DB::table('fee_voucher_items')->join('fee_vouchers', 'fee_vouchers.id', '=', 'fee_voucher_items.fee_voucher_id')->where('fee_vouchers.institute_id', $instituteId)->where('fee_vouchers.session_id', $data['session_id'])->whereBetween('fee_vouchers.billing_month', [substr($data['date_from'], 0, 7), substr($data['date_to'], 0, 7)])->select('fee_voucher_items.fee_name')->selectRaw('SUM(fee_voucher_items.amount) as billed')->groupBy('fee_voucher_items.fee_name')->get()->keyBy('fee_name');
        $paidByHead = [];
        $payments = FeePayment::query()->where('institute_id', $instituteId)->whereBetween('payment_date', [$data['date_from'], $data['date_to']])->whereHas('feeVoucher', fn ($q) => $q->where('session_id', $data['session_id']))->with('feeVoucher.items')->get();
        foreach ($payments as $payment) {
            $items = $payment->feeVoucher->items; $total = (float) $items->sum('amount');
            foreach ($items as $item) $paidByHead[$item->fee_name] = ($paidByHead[$item->fee_name] ?? 0) + ($total > 0 ? $payment->amount_paid * $item->amount / $total : 0);
        }
        $heads = collect($billedByHead->keys())->merge(array_keys($paidByHead))->unique()->map(function ($name) use ($billedByHead, $paidByHead) {
            $billed = (float) ($billedByHead[$name]->billed ?? 0); $collected = (float) ($paidByHead[$name] ?? 0);
            return ['head_id' => null, 'head_name' => $name, 'amount_billed' => round($billed, 2), 'amount_collected' => round($collected, 2), 'amount_pending' => round($billed - $collected, 2), 'collection_percentage' => $billed > 0 ? round($collected / $billed * 100, 2) : 0.0];
        })->values();
        return ResponseService::success(['period' => ['from' => $data['date_from'], 'to' => $data['date_to']], 'total_collected' => round((float) $payments->sum('amount_paid'), 2), 'fee_heads' => $heads], 'Category summary retrieved successfully');
    }

    public function studentLedger(Request $request, int $studentId): JsonResponse
    {
        $data = $request->validate(['session_id' => ['required', 'integer']]); $instituteId = $this->instituteId($request);
        if (($error = $this->sessionError($data['session_id'], $instituteId)) !== null) return $error;
        $student = Student::query()->where('institute_id', $instituteId)->with(['enrollments' => fn ($q) => $q->where('session_id', $data['session_id'])->with(['academicClass', 'section'])])->find($studentId);
        if (!$student) return ResponseService::notFound('Student not found');
        $vouchers = FeeVoucher::query()->where('institute_id', $instituteId)->where('session_id', $data['session_id'])->where('student_id', $studentId)->with(['items', 'payments'])->get();
        $entries = collect();
        foreach ($vouchers as $voucher) {
            $entries->push(['sort_date' => $voucher->billing_month.'-01', 'sort_order' => 0, 'sort_id' => $voucher->id, 'type' => 'voucher', 'transaction_id' => $voucher->id, 'date' => $voucher->billing_month.'-01', 'reference_no' => 'INV-'.str_replace('-', '', $voucher->billing_month).'-'.str_pad((string) $voucher->id, 5, '0', STR_PAD_LEFT), 'description' => Carbon::createFromFormat('Y-m-d', $voucher->billing_month.'-01')->format('F Y').' Fee', 'debit' => (float) $voucher->total_amount, 'credit' => 0.0]);
            foreach ($voucher->payments as $payment) $entries->push(['sort_date' => $payment->payment_date?->toDateString(), 'sort_order' => 1, 'sort_id' => $payment->id, 'type' => 'receipt', 'transaction_id' => $payment->id, 'date' => $payment->payment_date?->toDateString(), 'reference_no' => $this->receiptNo($payment), 'description' => 'Payment via '.ucfirst($payment->payment_method), 'debit' => 0.0, 'credit' => (float) $payment->amount_paid]);
        }
        $balance = 0.0;
        $ledger = $entries->sortBy([['sort_date', 'asc'], ['sort_order', 'asc'], ['sort_id', 'asc']])->map(function ($entry) use (&$balance) { $balance += $entry['debit'] - $entry['credit']; unset($entry['sort_date'], $entry['sort_order'], $entry['sort_id']); $entry['debit'] = round($entry['debit'], 2); $entry['credit'] = round($entry['credit'], 2); $entry['balance'] = round($balance, 2); return $entry; })->values();
        $enrollment = $student->enrollments->first();
        return ResponseService::success(['student' => ['student_id' => $student->id, 'full_name' => trim($student->first_name.' '.$student->last_name), 'roll_number' => $enrollment?->roll_number, 'class_name' => $enrollment?->academicClass?->name, 'section_name' => $enrollment?->section?->name, 'current_balance' => round($balance, 2)], 'ledger' => $ledger], 'Student ledger retrieved successfully');
    }

    private function instituteId(Request $request): ?int { $id = InstituteUser::where('user_id', $request->user()->id)->where('is_active', true)->value('institute_id'); return $id === null ? null : (int) $id; }
    private function sessionError(int $sessionId, ?int $instituteId): ?JsonResponse { if ($instituteId === null) return ResponseService::error('No active institute is associated with this user', 422); if (!AcademicSession::whereKey($sessionId)->where('institute_id', $instituteId)->exists()) return ResponseService::error('Validation failed', 422, ['session_id' => ['The selected session does not belong to the active institute.']]); return null; }
    private function scopeFilterError(array $data, int $instituteId): ?JsonResponse { if (isset($data['class_id']) && !DB::table('classes')->where('id', $data['class_id'])->where('institute_id', $instituteId)->exists()) return ResponseService::error('Validation failed', 422, ['class_id' => ['The selected class does not belong to the active institute.']]); if (isset($data['section_id']) && !DB::table('sections')->join('classes', 'classes.id', '=', 'sections.class_id')->where('sections.id', $data['section_id'])->where('classes.institute_id', $instituteId)->when(isset($data['class_id']), fn ($q) => $q->where('sections.class_id', $data['class_id']))->exists()) return ResponseService::error('Validation failed', 422, ['section_id' => ['The selected section does not belong to the active institute or selected class.']]); return null; }
    private function receiptNo(FeePayment $payment): string { return 'REC-'.($payment->payment_date?->format('Ymd') ?? '00000000').'-'.str_pad((string) $payment->id, 2, '0', STR_PAD_LEFT); }
}
