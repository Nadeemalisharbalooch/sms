<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institute\CollectFeePaymentRequest;
use App\Http\Requests\Institute\GenerateVouchersRequest;
use App\Http\Requests\Institute\StoreFeeCategoryRequest;
use App\Http\Requests\Institute\StoreFeeStructureRequest;
use App\Http\Requests\Institute\StoreStudentFeeAssignmentRequest;
use App\Http\Resources\Institute\FeeCategoryResource;
use App\Http\Resources\Institute\FeePaymentResource;
use App\Http\Resources\Institute\FeeStructureResource;
use App\Http\Resources\Institute\FeeVoucherResource;
use App\Http\Resources\Institute\StudentFeeAssignmentResource;
use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\FeeCategory;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\FeeVoucher;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeeController extends Controller
{
    // =====================================================================
    // API 1: Fee Categories
    // =====================================================================

    public function indexCategories(Request $request): JsonResponse
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $categories = FeeCategory::query()
            ->where('institute_id', $instituteId)
            ->orderBy('name')
            ->paginate();

        return ResponseService::success(
            FeeCategoryResource::collection($categories),
            'Fee categories retrieved successfully'
        );
    }

    public function storeCategory(StoreFeeCategoryRequest $request): JsonResponse
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $validated = $request->validated();

        if (FeeCategory::query()->where('institute_id', $instituteId)->where('name', $validated['name'])->exists()) {
            return ResponseService::error(
                'Validation failed',
                422,
                ['name' => ['The fee category name has already been taken.']]
            );
        }

        $category = FeeCategory::create([
            ...$validated,
            'institute_id' => $instituteId,
        ]);

        return ResponseService::success(
            new FeeCategoryResource($category),
            'Fee category created successfully',
            201
        );
    }

    public function updateCategory(Request $request, int $categoryId): JsonResponse
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $category = FeeCategory::query()
            ->where('institute_id', $instituteId)
            ->find($categoryId);

        if ($category === null) {
            return ResponseService::notFound('Fee category not found');
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        if (isset($validated['name'])
            && FeeCategory::query()
                ->where('institute_id', $instituteId)
                ->where('name', $validated['name'])
                ->whereKeyNot($category->id)
                ->exists()) {
            return ResponseService::error(
                'Validation failed',
                422,
                ['name' => ['The fee category name has already been taken.']]
            );
        }

        $category->update($validated);

        return ResponseService::success(
            new FeeCategoryResource($category->fresh()),
            'Fee category updated successfully'
        );
    }

    public function destroyCategory(Request $request, int $categoryId): JsonResponse
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $category = FeeCategory::query()
            ->where('institute_id', $instituteId)
            ->find($categoryId);

        if ($category === null) {
            return ResponseService::notFound('Fee category not found');
        }

        $isInUse = FeeStructure::query()
            ->where('institute_id', $instituteId)
            ->where('fee_category_id', $category->id)
            ->exists()
            || StudentFeeAssignment::query()
                ->where('institute_id', $instituteId)
                ->where('fee_category_id', $category->id)
                ->exists();

        if ($isInUse) {
            return ResponseService::error(
                'This fee category cannot be deleted while it is used by a fee structure or student assignment.',
                422,
                ['category' => ['Remove its fee structures and student assignments first.']]
            );
        }

        $category->delete();

        return ResponseService::success(null, 'Fee category deleted successfully');
    }

    // =====================================================================
    // API 2: Class Fee Structures
    // =====================================================================

    public function indexFeeStructures(Request $request): JsonResponse
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $sessionId = $this->activeSessionId($instituteId);

        if ($sessionId === null) {
            return ResponseService::error('Validation failed', 422, [
                'session_id' => ['No active academic session exists for the active institute.'],
            ]);
        }

        $structures = FeeStructure::query()
            ->where('institute_id', $instituteId)
            ->where('session_id', $sessionId)
            ->with('feeCategory')
            ->orderBy('class_id')
            ->paginate();

        return ResponseService::success(
            FeeStructureResource::collection($structures),
            'Fee structures retrieved successfully'
        );
    }

    public function storeFeeStructure(StoreFeeStructureRequest $request): JsonResponse
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $sessionId = $this->activeSessionId($instituteId);

        if ($sessionId === null) {
            return ResponseService::error('Validation failed', 422, [
                'session_id' => ['No active academic session exists for the active institute.'],
            ]);
        }

        $validated = $request->validated();

        $error = $this->validateClassAndCategoryBelongToInstitute($instituteId, $validated);

        if ($error !== null) {
            return $error;
        }

        $structure = DB::transaction(function () use ($instituteId, $sessionId, $validated) {
            return FeeStructure::query()->updateOrCreate(
                [
                    'session_id' => $sessionId,
                    'class_id' => $validated['class_id'],
                    'fee_category_id' => $validated['fee_category_id'],
                ],
                [
                    'institute_id' => $instituteId,
                    'amount' => $validated['amount'],
                    'recurrence' => $validated['recurrence'],
                ]
            );
        });

        return ResponseService::success(
            new FeeStructureResource($structure->fresh()->load('feeCategory')),
            $structure->wasRecentlyCreated ? 'Fee structure created successfully' : 'Fee structure updated successfully',
            $structure->wasRecentlyCreated ? 201 : 200
        );
    }

    public function destroyFeeStructure(Request $request, int $structureId): JsonResponse
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $structure = FeeStructure::query()
            ->where('institute_id', $instituteId)
            ->find($structureId);

        if ($structure === null) {
            return ResponseService::notFound('Fee structure not found');
        }

        $structure->delete();

        return ResponseService::success(null, 'Fee structure deleted successfully');
    }

    // =====================================================================
    // API 3: Student-Specific Fee Assignments
    // =====================================================================

    public function indexStudentAssignments(Request $request): JsonResponse
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $sessionId = $this->activeSessionId($instituteId);

        if ($sessionId === null) {
            return ResponseService::error('Validation failed', 422, [
                'session_id' => ['No active academic session exists for the active institute.'],
            ]);
        }

        $assignments = StudentFeeAssignment::query()
            ->where('institute_id', $instituteId)
            ->where('session_id', $sessionId)
            ->with('feeCategory')
            ->when($request->filled('student_id'), fn ($query) => $query->where('student_id', $request->integer('student_id')))
            ->orderBy('student_id')
            ->paginate();

        return ResponseService::success(
            StudentFeeAssignmentResource::collection($assignments),
            'Student fee assignments retrieved successfully'
        );
    }

    public function storeStudentAssignment(StoreStudentFeeAssignmentRequest $request): JsonResponse
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $sessionId = $this->activeSessionId($instituteId);

        if ($sessionId === null) {
            return ResponseService::error('Validation failed', 422, [
                'session_id' => ['No active academic session exists for the active institute.'],
            ]);
        }

        $validated = $request->validated();

        $error = $this->validateStudentAndCategoryBelongToInstitute($instituteId, $validated);

        if ($error !== null) {
            return $error;
        }

        $assignment = DB::transaction(function () use ($instituteId, $sessionId, $validated) {
            return StudentFeeAssignment::query()->updateOrCreate(
                [
                    'session_id' => $sessionId,
                    'student_id' => $validated['student_id'],
                    'fee_category_id' => $validated['fee_category_id'],
                ],
                [
                    'institute_id' => $instituteId,
                    'amount' => $validated['amount'],
                ]
            );
        });

        return ResponseService::success(
            new StudentFeeAssignmentResource($assignment->fresh()->load('feeCategory')),
            $assignment->wasRecentlyCreated ? 'Student fee assignment created successfully' : 'Student fee assignment updated successfully',
            $assignment->wasRecentlyCreated ? 201 : 200
        );
    }

    public function destroyStudentAssignment(Request $request, int $assignmentId): JsonResponse
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $assignment = StudentFeeAssignment::query()
            ->where('institute_id', $instituteId)
            ->find($assignmentId);

        if ($assignment === null) {
            return ResponseService::notFound('Student fee assignment not found');
        }

        $assignment->delete();

        return ResponseService::success(null, 'Student fee assignment deleted successfully');
    }

    // =====================================================================
    // API 4: Smart Bulk Voucher Generation (The Engine)
    // =====================================================================

    public function generateVouchers(GenerateVouchersRequest $request): JsonResponse
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $sessionId = $this->activeSessionId($instituteId);

        if ($sessionId === null) {
            return ResponseService::error('Validation failed', 422, [
                'session_id' => ['No active academic session exists for the active institute.'],
            ]);
        }

        $validated = $request->validated();
        $classId = $validated['class_id'] ?? null;
        $billingMonth = $validated['billing_month'];
        $dueDate = $validated['due_date'];

        if ($classId !== null && ! AcademicClass::query()->whereKey($classId)->where('institute_id', $instituteId)->exists()) {
            return ResponseService::error(
                'Validation failed',
                422,
                ['class_id' => ['The selected class does not belong to the active institute.']]
            );
        }

        $counts = DB::transaction(function () use ($instituteId, $sessionId, $classId, $billingMonth, $dueDate) {
            // Serialize voucher generation within a session. Without this lock,
            // simultaneous requests can both decide that a voucher is missing
            // and then violate the unique session/student/month constraint.
            AcademicSession::query()
                ->whereKey($sessionId)
                ->lockForUpdate()
                ->firstOrFail();

            $students = Student::query()
                ->where('institute_id', $instituteId)
                ->whereHas('enrollments', function ($query) use ($sessionId, $classId) {
                    $query->where('session_id', $sessionId)
                        ->when($classId !== null, fn ($classQuery) => $classQuery->where('class_id', $classId));
                })
                ->with(['enrollments' => function ($query) use ($sessionId, $classId) {
                    $query->where('session_id', $sessionId)
                        ->when($classId !== null, fn ($classQuery) => $classQuery->where('class_id', $classId));
                }])
                ->get();

            $existingVoucherStudentIds = FeeVoucher::query()
                ->where('session_id', $sessionId)
                ->where('billing_month', $billingMonth)
                ->pluck('student_id');

            $classIds = $students->pluck('enrollments')->flatten()->pluck('class_id')->unique()->filter();
            $studentIds = $students->pluck('id');

            $classFees = FeeStructure::query()
                ->where('session_id', $sessionId)
                ->whereIn('class_id', $classIds)
                ->with('feeCategory')
                ->get()
                ->groupBy('class_id');

            $studentFees = StudentFeeAssignment::query()
                ->where('session_id', $sessionId)
                ->whereIn('student_id', $studentIds)
                ->with('feeCategory')
                ->get()
                ->groupBy('student_id');

            // Yearly and one-time class fees are charged only once per student
            // within an academic session. Voucher items retain the fee name, which
            // is unique per institute through fee_categories.
            $alreadyChargedNonMonthlyFees = FeeVoucher::query()
                ->where('session_id', $sessionId)
                ->whereIn('student_id', $studentIds)
                ->with('items:id,fee_voucher_id,fee_name')
                ->get()
                ->groupBy('student_id')
                ->map(fn ($vouchers) => $vouchers
                    ->flatMap(fn (FeeVoucher $voucher) => $voucher->items->pluck('fee_name'))
                    ->unique()
                    ->values());

            $generatedCount = 0;
            $skippedCount = 0;

            foreach ($students as $student) {
                if ($existingVoucherStudentIds->contains($student->id)) {
                    $skippedCount++;

                    continue;
                }

                $enrollment = $student->enrollments->first();
                $classIdForStudent = $enrollment?->class_id;

                $lineItems = collect();

                // Sum of standard Class fee_structures
                $chargedNonMonthlyFees = $alreadyChargedNonMonthlyFees->get($student->id, collect());

                collect($classFees->get($classIdForStudent, collect()))
                    ->each(function (FeeStructure $structure) use ($lineItems, $chargedNonMonthlyFees) {
                        $feeName = $structure->feeCategory?->name ?? 'Fee';

                        if ($structure->recurrence !== 'monthly' && $chargedNonMonthlyFees->contains($feeName)) {
                            return;
                        }

                        $lineItems->push([
                            'fee_name' => $feeName,
                            'amount' => (float) $structure->amount,
                        ]);
                    });

                // Sum of optional Student fee_assignments
                collect($studentFees->get($student->id, collect()))
                    ->each(function (StudentFeeAssignment $assignment) use ($lineItems) {
                        $lineItems->push([
                            'fee_name' => $assignment->feeCategory?->name ?? 'Fee',
                            'amount' => (float) $assignment->amount,
                        ]);
                    });

                // A full discount can produce a zero-value voucher, but it must
                // still be generated so the billing run remains complete.
                $totalAmount = max(0, (float) $lineItems->sum('amount'));

                $voucher = FeeVoucher::create([
                    'institute_id' => $instituteId,
                    'session_id' => $sessionId,
                    'student_id' => $student->id,
                    'billing_month' => $billingMonth,
                    'due_date' => $dueDate,
                    'total_amount' => $totalAmount,
                    'paid_amount' => 0,
                    'status' => $totalAmount === 0.0 ? 'paid' : 'unpaid',
                ]);

                foreach ($lineItems as $item) {
                    $voucher->items()->create($item);
                }

                $generatedCount++;
            }

            return ['generated_count' => $generatedCount, 'skipped_count' => $skippedCount];
        });

        return ResponseService::success(
            $counts,
            'Vouchers generated successfully'
        );
    }

    // =====================================================================
    // API 5: Fetch Student Ledger (Cashier Search)
    // =====================================================================

    public function ledger(Request $request): JsonResponse
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $sessionId = $this->activeSessionId($instituteId);

        if ($sessionId === null) {
            return ResponseService::error('Validation failed', 422, [
                'session_id' => ['No active academic session exists for the active institute.'],
            ]);
        }

        $request->validate([
            'search' => ['required', 'string', 'max:100'],
        ]);

        $search = $request->string('search')->trim()->toString();

        $student = Student::query()
            ->where('institute_id', $instituteId)
            ->where(function ($query) use ($search, $sessionId) {
                $query
                    ->whereKey(is_numeric($search) ? (int) $search : 0)
                    ->orWhereHas('enrollments', function ($enrollmentQuery) use ($search, $sessionId) {
                        $enrollmentQuery
                            ->where('session_id', $sessionId)
                            ->where('roll_number', $search);
                    })
                    ->orWhere(function ($profileQuery) use ($search) {
                        $profileQuery
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
                    });
            })
            ->with(['enrollments' => function ($query) use ($sessionId) {
                $query->where('session_id', $sessionId)->with('academicClass');
            }])
            ->first();

        if ($student === null) {
            return ResponseService::error(
                'Student not found',
                404,
                ['search' => ['No student matched the provided student ID, roll number, or name.']]
            );
        }

        $enrollment = $student->enrollments->first();

        $vouchers = FeeVoucher::query()
            ->where('student_id', $student->id)
            ->where('session_id', $sessionId)
            ->with('items')
            ->orderBy('billing_month')
            ->get();

        $totalDue = (float) $vouchers->filter(fn (FeeVoucher $voucher) => $voucher->status !== 'paid')->sum('balance_due');
        $totalPaid = (float) $vouchers->sum('paid_amount');

        $data = [
            'student' => [
                'id' => $student->id,
                'name' => trim($student->first_name.' '.$student->last_name),
                'class' => $enrollment?->academicClass?->name ?? 'N/A',
                'roll_number' => $enrollment?->roll_number,
            ],
            'summary' => [
                'total_due' => round($totalDue, 2),
                'total_paid' => round($totalPaid, 2),
            ],
            'vouchers' => FeeVoucherResource::collection($vouchers),
        ];

        return ResponseService::success($data, 'Student ledger retrieved successfully');
    }

    // =====================================================================
    // API 6: Collect Payment
    // =====================================================================

    public function collect(CollectFeePaymentRequest $request): JsonResponse
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $sessionId = $this->activeSessionId($instituteId);

        if ($sessionId === null) {
            return ResponseService::error('Validation failed', 422, [
                'session_id' => ['No active academic session exists for the active institute.'],
            ]);
        }

        $validated = $request->validated();
        $amountPaid = (float) $validated['amount_paid'];
        $collectedByUserId = $request->user()->id;

        $result = DB::transaction(function () use ($instituteId, $sessionId, $validated, $amountPaid, $collectedByUserId) {
            $voucher = FeeVoucher::query()
                ->where('institute_id', $instituteId)
                ->where('session_id', $sessionId)
                ->with('items')
                ->lockForUpdate()
                ->find($validated['fee_voucher_id']);

            if ($voucher === null) {
                return null;
            }

            $balanceDue = (float) $voucher->balance_due;

            if ($balanceDue <= 0) {
                return ['error' => 'This voucher has already been fully paid.'];
            }

            if ($amountPaid > $balanceDue) {
                return ['error' => 'The payment amount cannot exceed the balance due of '.number_format($balanceDue, 2).'.'];
            }

            $payment = FeePayment::create([
                'institute_id' => $instituteId,
                'fee_voucher_id' => $voucher->id,
                'amount_paid' => $amountPaid,
                'payment_date' => $validated['payment_date'],
                'payment_method' => $validated['payment_method'],
                'collected_by_user_id' => $collectedByUserId,
            ]);

            $newPaidAmount = (float) $voucher->paid_amount + $amountPaid;

            $voucher->update([
                'paid_amount' => $newPaidAmount,
                'status' => $newPaidAmount >= (float) $voucher->total_amount ? 'paid' : 'partial',
            ]);

            return ['payment' => $payment, 'voucher' => $voucher];
        });

        if ($result === null) {
            return ResponseService::notFound('Fee voucher not found');
        }

        if (isset($result['error'])) {
            return ResponseService::error('Validation failed', 422, ['amount_paid' => [$result['error']]]);
        }

        return ResponseService::success(
            [
                'payment' => new FeePaymentResource($result['payment']->fresh()),
                'voucher' => new FeeVoucherResource($result['voucher']->fresh()->load('items')),
            ],
            'Payment collected successfully',
            201
        );
    }

    // =====================================================================
    // Helpers
    // =====================================================================

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

    private function validateClassAndCategoryBelongToInstitute(int $instituteId, array $validated): ?JsonResponse
    {
        if (! AcademicClass::query()->whereKey($validated['class_id'])->where('institute_id', $instituteId)->exists()) {
            return ResponseService::error(
                'Validation failed',
                422,
                ['class_id' => ['The selected class does not belong to the active institute.']]
            );
        }

        if (! FeeCategory::query()->whereKey($validated['fee_category_id'])->where('institute_id', $instituteId)->exists()) {
            return ResponseService::error(
                'Validation failed',
                422,
                ['fee_category_id' => ['The selected fee category does not belong to the active institute.']]
            );
        }

        return null;
    }

    private function validateStudentAndCategoryBelongToInstitute(int $instituteId, array $validated): ?JsonResponse
    {
        if (! Student::query()->whereKey($validated['student_id'])->where('institute_id', $instituteId)->exists()) {
            return ResponseService::error(
                'Validation failed',
                422,
                ['student_id' => ['The selected student does not belong to the active institute.']]
            );
        }

        if (! FeeCategory::query()->whereKey($validated['fee_category_id'])->where('institute_id', $instituteId)->exists()) {
            return ResponseService::error(
                'Validation failed',
                422,
                ['fee_category_id' => ['The selected fee category does not belong to the active institute.']]
            );
        }

        return null;
    }
}
