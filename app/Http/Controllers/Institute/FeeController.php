<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institute\CollectFeePaymentRequest;
use App\Http\Requests\Institute\DeleteVouchersRequest;
use App\Http\Requests\Institute\FeeRecordsRequest;
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
use App\Models\AcademicSection;
use App\Models\AcademicSession;
use App\Models\FeeCategory;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\FeeVoucher;
use App\Models\FinancialSetting;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use App\Models\StudentOneTimeFee;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    public function financialSettings(Request $request): JsonResponse
    {
        $instituteId = $this->activeInstituteId($request);
        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $settings = FinancialSetting::firstOrCreate(['institute_id' => $instituteId]);

        return ResponseService::success($settings, 'Financial settings retrieved successfully');
    }

    public function updateFinancialSettings(Request $request): JsonResponse
    {
        $instituteId = $this->activeInstituteId($request);
        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $validated = $request->validate([
            'late_fee_type' => ['required', 'in:fixed,percentage'],
            'late_fee_value' => ['required', 'numeric', 'min:0'],
        ]);
        $settings = FinancialSetting::updateOrCreate(['institute_id' => $instituteId], $validated);

        return ResponseService::success($settings, 'Financial settings updated successfully');
    }

    public function storeCategory(StoreFeeCategoryRequest $request): JsonResponse
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $validated = $request->validated();

        if ($validated['type'] === 'one_time' && $validated['default_months'] !== []) {
            return ResponseService::error('Validation failed', 422, [
                'default_months' => ['One-time fee categories must have an empty default_months array.'],
            ]);
        }

        if (FeeCategory::query()->where('institute_id', $instituteId)->where('name', $validated['name'])->exists()) {
            return ResponseService::error(
                'Validation failed',
                422,
                ['name' => ['The fee category name has already been taken.']]
            );
        }

        $category = FeeCategory::create([
            ...$validated,
            'default_months' => ($validated['type'] ?? 'recurring') === 'one_time' ? [] : ($validated['default_months'] ?? []),
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
            'type' => ['sometimes', 'required', 'in:recurring,one_time'],
            'default_months' => ['sometimes', 'array'],
            'default_months.*' => ['string', 'in:Jan,Feb,Mar,Apr,May,Jun,Jul,Aug,Sep,Oct,Nov,Dec'],
        ]);

        if (($validated['type'] ?? $category->type) === 'one_time'
            && isset($validated['default_months'])
            && $validated['default_months'] !== []) {
            return ResponseService::error('Validation failed', 422, [
                'default_months' => ['One-time fee categories must have an empty default_months array.'],
            ]);
        }

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
        if (($validated['type'] ?? $category->type) === 'one_time' && ! array_key_exists('default_months', $validated)) {
            $category->update(['default_months' => []]);
        }

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
                ->exists()
            || StudentOneTimeFee::query()
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

    public function storeFeeStructure(StoreFeeStructureRequest $request, ?int $classId = null): JsonResponse
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
        $categoryDefaults = FeeCategory::query()->find($validated['fee_category_id'])?->default_months;
        $validated['charge_months'] ??= $categoryDefaults ?? [];

        $error = $this->validateClassAndCategoryBelongToInstitute($instituteId, $validated);

        if ($error !== null) {
            return $error;
        }

        $structure = DB::transaction(function () use ($instituteId, $sessionId, $validated, $categoryDefaults) {
            return FeeStructure::query()->updateOrCreate(
                [
                    'session_id' => $sessionId,
                    'class_id' => $validated['class_id'],
                    'fee_category_id' => $validated['fee_category_id'],
                ],
                [
                    'institute_id' => $instituteId,
                    'amount' => $validated['amount'],
                    'charge_months' => $validated['charge_months'],
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

    public function storeStudentAssignment(StoreStudentFeeAssignmentRequest $request, ?int $studentId = null): JsonResponse
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

        $category = FeeCategory::query()->findOrFail($validated['fee_category_id']);
        if (($category->type ?? 'recurring') === 'one_time') {
            if ($validated['is_discount']) {
                return ResponseService::error('Validation failed', 422, [
                    'is_discount' => ['One-time fees must be charges.'],
                ]);
            }

            $fee = StudentOneTimeFee::create([
                'institute_id' => $instituteId,
                'session_id' => $sessionId,
                'student_id' => $validated['student_id'],
                'fee_category_id' => $validated['fee_category_id'],
                'amount' => abs((float) $validated['amount']),
            ]);

            return ResponseService::success($fee, 'Student one-time fee created successfully', 201);
        }

        $validated['charge_months'] ??= $category->default_months ?? [];

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
                    'is_discount' => $validated['is_discount'] ?? ((float) $validated['amount'] < 0),
                    'charge_months' => $validated['charge_months'] ?? null,
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
        $billingMonth = $validated['billing_month'];
        $dueDate = $validated['due_date'];

        $counts = DB::transaction(function () use ($instituteId, $sessionId, $billingMonth, $dueDate) {
            // Serialize voucher generation within a session. Without this lock,
            // simultaneous requests can both decide that a voucher is missing
            // and then violate the unique session/student/month constraint.
            AcademicSession::query()
                ->whereKey($sessionId)
                ->lockForUpdate()
                ->firstOrFail();

            // The batch_id column is a UUID, so use a database-compatible
            // identifier instead of a prefixed string.
            $batchId = (string) Str::uuid();
            $targetMonth = date('M', strtotime($billingMonth.'-01'));
            $financialSettings = FinancialSetting::firstOrCreate(['institute_id' => $instituteId]);

            $students = Student::query()
                ->where('institute_id', $instituteId)
                ->whereHas('enrollments', function ($query) use ($sessionId) {
                    $query->where('session_id', $sessionId);
                })
                ->with(['enrollments' => function ($query) use ($sessionId) {
                    $query->where('session_id', $sessionId);
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

            $oneTimeFeesQuery = StudentOneTimeFee::query()
                ->where('institute_id', $instituteId)
                ->where('session_id', $sessionId)
                ->whereIn('student_id', $studentIds)
                ->where('is_billed', false)
                ->with('feeCategory');
            $oneTimeFees = $oneTimeFeesQuery->get()->groupBy('student_id');

            $arrears = FeeVoucher::query()
                ->where('institute_id', $instituteId)
                ->where('session_id', $sessionId)
                ->whereIn('student_id', $studentIds)
                ->where('billing_month', '<', $billingMonth)
                ->whereIn('status', ['unpaid', 'partial', 'overdue'])
                ->get()
                ->filter(fn (FeeVoucher $voucher) => $voucher->balance_due > 0)
                ->groupBy('student_id');

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

                // Class charges apply only when the billing month is selected.
                collect($classFees->get($classIdForStudent, collect()))
                    ->each(function (FeeStructure $structure) use ($lineItems, $targetMonth) {
                        $feeName = $structure->feeCategory?->name ?? 'Fee';
                        if (! in_array($targetMonth, $structure->charge_months ?? [], true)) {
                            return;
                        }

                        $lineItems->push([
                            'description' => $feeName,
                            'amount' => (float) $structure->amount,
                            'type' => 'charge',
                        ]);
                    });

                // Student-specific add-ons and discounts follow their own month map.
                collect($studentFees->get($student->id, collect()))
                    ->each(function (StudentFeeAssignment $assignment) use ($lineItems, $targetMonth) {
                        if (! in_array($targetMonth, $assignment->charge_months ?? [], true)) {
                            return;
                        }
                        $lineItems->push([
                            'description' => $assignment->feeCategory?->name ?? 'Fee',
                            'amount' => abs((float) $assignment->amount),
                    'type' => $assignment->is_discount ? 'discount' : 'charge',
                        ]);
                    });

                foreach ($oneTimeFees->get($student->id, collect()) as $oneTimeFee) {
                    $lineItems->push([
                        'description' => $oneTimeFee->feeCategory?->name ?? 'Fee',
                        'amount' => (float) $oneTimeFee->amount,
                        'type' => 'charge',
                    ]);
                }

                foreach ($arrears->get($student->id, collect()) as $previousVoucher) {
                    $remaining = (float) $previousVoucher->balance_due;
                    if ($remaining > 0) {
                        $lineItems->push([
                            'description' => 'Arrears: '.$previousVoucher->billing_month,
                            'amount' => $remaining,
                            'type' => 'charge',
                        ]);
                    }
                }

                // Students with no charges, add-ons, one-time fees, or arrears need no voucher.
                if ($lineItems->isEmpty()) {
                    $skippedCount++;

                    continue;
                }

                // A student can have a class fee and an individual assignment
                // for the same category. Store one line per fee name so a
                // voucher never contains duplicate-looking items.
                $lineItems = $lineItems
                    ->groupBy(fn ($item) => $item['description'].'|'.$item['type'])
                    ->map(fn ($items) => [
                        'description' => $items->first()['description'],
                        'amount' => round((float) $items->sum('amount'), 2),
                        'type' => $items->first()['type'],
                    ])
                    ->values();

                // A full discount can produce a zero-value voucher, but it must
                // still be generated so the billing run remains complete.
                $totalAmount = max(0, (float) $lineItems->where('type', 'charge')->sum('amount') - (float) $lineItems->where('type', 'discount')->sum('amount'));
                $lateFeeAmount = $totalAmount <= 0 ? 0 : ($financialSettings->late_fee_type === 'percentage'
                    ? round($totalAmount * (float) $financialSettings->late_fee_value / 100, 2)
                    : (float) $financialSettings->late_fee_value);

                $voucher = FeeVoucher::create([
                    'institute_id' => $instituteId,
                    'session_id' => $sessionId,
                    'student_id' => $student->id,
                    'batch_id' => $batchId,
                    'billing_month' => $billingMonth,
                    'due_date' => $dueDate,
                    'total_amount' => $totalAmount,
                    'late_fee_amount' => $lateFeeAmount,
                    'paid_amount' => 0,
                    'status' => $totalAmount === 0.0 ? 'paid' : 'unpaid',
                ]);

                foreach ($lineItems as $item) {
                    $voucher->items()->create($item);
                }

                foreach ($oneTimeFees->get($student->id, collect()) as $oneTimeFee) {
                    $oneTimeFee->update(['is_billed' => true]);
                }

                foreach ($arrears->get($student->id, collect()) as $previousVoucher) {
                    $previousVoucher->update([
                        'carried_forward_amount' => (float) $previousVoucher->carried_forward_amount + (float) $previousVoucher->balance_due,
                        'status' => 'carried_forward',
                    ]);
                }

                $generatedCount++;
            }

            return ['generated_count' => $generatedCount, 'skipped_count' => $skippedCount, 'batch_id' => $batchId];
        });

        return ResponseService::success(
            $counts,
            'Vouchers generated successfully'
        );
    }

    /**
     * GET: Retrieve generated vouchers, list batches, or preview upcoming voucher generation.
     */
    public function indexGenerateVouchers(Request $request): JsonResponse
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

        $billingMonth = $request->input('billing_month');
        $classId = $request->integer('class_id') ?: null;
        $sectionId = $request->integer('section_id') ?: null;
        $status = $request->input('status');
        $search = $request->string('search')->trim()->toString();
        $isPreview = $request->boolean('preview');
        $perPage = min(100, max(5, $request->integer('per_page', 25)));

        // Preview Mode
        if ($isPreview && $billingMonth) {
            $studentsQuery = Student::query()
                ->where('institute_id', $instituteId)
                ->whereHas('enrollments', function ($q) use ($sessionId, $classId, $sectionId) {
                    $q->where('session_id', $sessionId)
                        ->when($classId !== null, fn ($cq) => $cq->where('class_id', $classId))
                        ->when($sectionId !== null, fn ($sq) => $sq->where('section_id', $sectionId));
                });

            $totalEligible = $studentsQuery->count();
            $alreadyGeneratedStudentIds = FeeVoucher::query()
                ->where('institute_id', $instituteId)
                ->where('session_id', $sessionId)
                ->where('billing_month', $billingMonth)
                ->pluck('student_id');

            $alreadyGeneratedCount = $alreadyGeneratedStudentIds->count();
            $pendingGenerationCount = max(0, $totalEligible - $alreadyGeneratedCount);

            return ResponseService::success([
                'mode' => 'preview',
                'session_id' => $sessionId,
                'billing_month' => $billingMonth,
                'class_id' => $classId,
                'section_id' => $sectionId,
                'total_eligible_students' => $totalEligible,
                'already_generated_count' => $alreadyGeneratedCount,
                'pending_generation_count' => $pendingGenerationCount,
            ], 'Voucher generation preview retrieved successfully');
        }

        // Listing Mode
        $query = FeeVoucher::query()
            ->where('institute_id', $instituteId)
            ->where('session_id', $sessionId)
            ->when($billingMonth !== null, fn ($q) => $q->where('billing_month', $billingMonth))
            ->when($status !== null && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($classId !== null || $sectionId !== null, function ($q) use ($sessionId, $classId, $sectionId) {
                $q->whereHas('student.enrollments', function ($eq) use ($sessionId, $classId, $sectionId) {
                    $eq->where('session_id', $sessionId)
                        ->when($classId !== null, fn ($cq) => $cq->where('class_id', $classId))
                        ->when($sectionId !== null, fn ($sq) => $sq->where('section_id', $sectionId));
                });
            })
            ->when($search !== '', function ($q) use ($search, $sessionId) {
                $q->where(function ($sq) use ($search, $sessionId) {
                    $sq->where('id', is_numeric($search) ? (int) $search : 0)
                        ->orWhereHas('student', function ($stq) use ($search, $sessionId) {
                            $stq->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                                ->orWhereHas('enrollments', fn ($eq) => $eq->where('session_id', $sessionId)->where('roll_number', 'like', "%{$search}%"));
                        });
                });
            })
            ->with([
                'items',
                'student.enrollments' => function ($eq) use ($sessionId) {
                    $eq->where('session_id', $sessionId)->with(['academicClass', 'section']);
                },
            ])
            ->latest('id');

        // Summary metrics before pagination
        $allMatching = (clone $query)->get();
        $summary = [
            'total_vouchers' => $allMatching->count(),
            'total_amount' => round((float) $allMatching->sum('total_amount'), 2),
            'total_paid' => round((float) $allMatching->sum('paid_amount'), 2),
            'total_balance_due' => round((float) $allMatching->sum(fn ($v) => $v->total_amount - $v->paid_amount), 2),
            'paid_count' => $allMatching->where('status', 'paid')->count(),
            'unpaid_count' => $allMatching->where('status', 'unpaid')->count(),
            'partial_count' => $allMatching->where('status', 'partial')->count(),
        ];

        // Batches represent generation runs. Keep this list institute-wide so
        // the frontend can show every distinct batch for the active institute,
        // including batches created in a different academic session or month.
        $allInstituteBatchVouchers = FeeVoucher::query()
            ->where('institute_id', $instituteId)
            ->whereNotNull('batch_id')
            ->get();

        $batchSummaries = $allInstituteBatchVouchers
            ->groupBy(fn ($v) => $v->batch_id ?: '__unbatched__')
            ->map(function ($vouchers, $batchId) {
                $latestVoucher = $vouchers->sortByDesc('created_at')->first();

                return [
                    'batch_id' => $batchId === '__unbatched__' ? null : $batchId,
                    'total_vouchers' => $vouchers->count(),
                    'session_id' => $latestVoucher?->session_id,
                    'billing_month' => $latestVoucher?->billing_month,
                    'generated_at' => $latestVoucher?->created_at?->format('Y-m-d H:i:s'),
                    'total_amount' => round((float) $vouchers->sum('total_amount'), 2),
                    'total_paid' => round((float) $vouchers->sum('paid_amount'), 2),
                    'total_balance_due' => round((float) $vouchers->sum(fn ($v) => $v->total_amount - $v->paid_amount), 2),
                    'paid_count' => $vouchers->where('status', 'paid')->count(),
                    'unpaid_count' => $vouchers->where('status', 'unpaid')->count(),
                    'partial_count' => $vouchers->where('status', 'partial')->count(),
                ];
            })
            ->sortByDesc('generated_at')
            ->values()
            ->all();

        $paginated = $query->paginate($perPage);

        // Transform collection for clean frontend consumption
        $vouchers = $paginated->getCollection()->map(function ($voucher) {
            $enrollment = $voucher->student?->enrollments?->first();

            return [
                'id' => $voucher->id,
                'voucher_no' => $voucher->id,
                'batch_id' => $voucher->batch_id,
                'billing_month' => $voucher->billing_month,
                'due_date' => $voucher->due_date?->toDateString(),
                'total_amount' => $voucher->total_amount,
                'paid_amount' => $voucher->paid_amount,
                'balance_due' => round($voucher->total_amount - $voucher->paid_amount, 2),
                'status' => $voucher->status,
                'student' => $voucher->student ? [
                    'id' => $voucher->student->id,
                    'name' => trim($voucher->student->first_name.' '.$voucher->student->last_name),
                    'roll_number' => $enrollment?->roll_number,
                    'class' => $enrollment?->academicClass ? [
                        'id' => $enrollment->academicClass->id,
                        'name' => $enrollment->academicClass->name,
                    ] : null,
                    'section' => $enrollment?->section ? [
                        'id' => $enrollment->section->id,
                        'name' => $enrollment->section->name,
                    ] : null,
                ] : null,
                'items' => $voucher->items->map(fn ($item) => [
                    'id' => $item->id,
                    'description' => $item->description,
                    'amount' => $item->amount,
                ]),
                'created_at' => $voucher->created_at?->toISOString(),
            ];
        });

        // Available billing months for filter dropdown
        $availableMonths = FeeVoucher::query()
            ->where('institute_id', $instituteId)
            ->where('session_id', $sessionId)
            ->distinct()
            ->orderByDesc('billing_month')
            ->pluck('billing_month');

        $batchIds = FeeVoucher::query()
            ->where('institute_id', $instituteId)
            ->where('session_id', $sessionId)
            ->whereNotNull('batch_id')
            ->distinct()
            ->orderByDesc('batch_id')
            ->pluck('batch_id');

        return ResponseService::success([
            'summary' => $summary,
            'batch_summaries' => $batchSummaries,
            // 'available_months' => $availableMonths,
            // 'batch_ids' => $batchIds,
            // 'vouchers' => $vouchers,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ], 'Fee vouchers retrieved successfully');
    }

    /**
     * Bulk delete generated fee vouchers (with safety checks for paid vouchers).
     */
    public function destroyVouchers(DeleteVouchersRequest $request): JsonResponse
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $sessionId = $request->input('session_id') ?: $this->activeSessionId($instituteId);

        if ($sessionId === null) {
            return ResponseService::error('Validation failed', 422, [
                'session_id' => ['No active academic session exists for the active institute.'],
            ]);
        }

        $validated = $request->validated();
        $classId = $validated['class_id'] ?? null;
        $billingMonth = $validated['billing_month'] ?? null;
        $studentIds = $validated['student_ids'] ?? null;
        $voucherIds = $validated['voucher_ids'] ?? null;
        $batchId = $validated['batch_id'] ?? null;
        $force = (bool) ($validated['force'] ?? false);

        if ($classId !== null && ! AcademicClass::query()->whereKey($classId)->where('institute_id', $instituteId)->exists()) {
            return ResponseService::error(
                'Validation failed',
                422,
                ['class_id' => ['The selected class does not belong to the active institute.']]
            );
        }

        $query = FeeVoucher::query()
            ->where('institute_id', $instituteId)
            ->where('session_id', $sessionId)
            ->when($batchId !== null, fn ($q) => $q->where('batch_id', $batchId))
            ->when($billingMonth !== null, fn ($q) => $q->where('billing_month', $billingMonth))
            ->when(! empty($voucherIds), fn ($q) => $q->whereIn('id', $voucherIds))
            ->when(! empty($studentIds), fn ($q) => $q->whereIn('student_id', $studentIds))
            ->when($classId !== null, function ($q) use ($sessionId, $classId) {
                $q->whereHas('student.enrollments', function ($eq) use ($sessionId, $classId) {
                    $eq->where('session_id', $sessionId)->where('class_id', $classId);
                });
            });

        $vouchers = $query->with('payments')->get();

        if ($vouchers->isEmpty()) {
            return ResponseService::success([
                'deleted_count' => 0,
                'skipped_paid_count' => 0,
            ], 'No matching fee vouchers found to delete');
        }

        $deletedCount = 0;
        $skippedPaidCount = 0;

        DB::transaction(function () use ($vouchers, $force, &$deletedCount, &$skippedPaidCount) {
            foreach ($vouchers as $voucher) {
                $hasPayments = $voucher->paid_amount > 0 || $voucher->payments->isNotEmpty() || $voucher->status !== 'unpaid';

                if ($hasPayments && ! $force) {
                    $skippedPaidCount++;

                    continue;
                }

                $voucher->delete();
                $deletedCount++;
            }
        });

        $message = "{$deletedCount} fee voucher(s) deleted successfully.";
        if ($skippedPaidCount > 0) {
            $message .= " ({$skippedPaidCount} voucher(s) were skipped because they contain recorded payments).";
        }

        return ResponseService::success([
            'deleted_count' => $deletedCount,
            'skipped_paid_count' => $skippedPaidCount,
        ], $message);
    }

    /**
     * Delete a single fee voucher by ID.
     */
    public function destroyVoucher(Request $request, int $voucherId): JsonResponse
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $voucher = FeeVoucher::query()
            ->where('institute_id', $instituteId)
            ->whereKey($voucherId)
            ->with('payments')
            ->first();

        if ($voucher === null) {
            return ResponseService::notFound('Fee voucher not found');
        }

        $force = $request->boolean('force');
        $hasPayments = $voucher->paid_amount > 0 || $voucher->payments->isNotEmpty() || $voucher->status !== 'unpaid';

        if ($hasPayments && ! $force) {
            return ResponseService::error('Cannot delete a fee voucher with recorded payments. Use force=true if you really wish to delete it.', 422);
        }

        $voucher->delete();

        return ResponseService::success(null, 'Fee voucher deleted successfully');
    }

    public function destroyVouchersByBatch(Request $request, string $batchId): JsonResponse
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $sessionId = $request->input('session_id') ?: $this->activeSessionId($instituteId);

        if ($sessionId === null) {
            return ResponseService::error('Validation failed', 422, [
                'session_id' => ['No active academic session exists for the active institute.'],
            ]);
        }

        $force = $request->boolean('force');

        $query = FeeVoucher::query()
            ->where('institute_id', $instituteId)
            ->where('session_id', $sessionId)
            ->where('batch_id', $batchId);

        $vouchers = $query->with('payments')->get();

        if ($vouchers->isEmpty()) {
            return ResponseService::success([
                'deleted_count' => 0,
                'skipped_paid_count' => 0,
                'batch_id' => $batchId,
            ], 'No matching fee vouchers found to delete');
        }

        $deletedCount = 0;
        $skippedPaidCount = 0;

        DB::transaction(function () use ($vouchers, $force, &$deletedCount, &$skippedPaidCount) {
            foreach ($vouchers as $voucher) {
                $hasPayments = $voucher->paid_amount > 0 || $voucher->payments->isNotEmpty() || $voucher->status !== 'unpaid';

                if ($hasPayments && ! $force) {
                    $skippedPaidCount++;

                    continue;
                }

                $voucher->delete();
                $deletedCount++;
            }
        });

        $message = "{$deletedCount} fee voucher(s) deleted successfully from batch {$batchId}.";
        if ($skippedPaidCount > 0) {
            $message .= " ({$skippedPaidCount} voucher(s) were skipped because they contain recorded payments).";
        }

        return ResponseService::success([
            'deleted_count' => $deletedCount,
            'skipped_paid_count' => $skippedPaidCount,
            'batch_id' => $batchId,
        ], $message);
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

        $validated = $request->validate([
            'student_id' => ['nullable', 'integer'],
            'class_id' => ['nullable', 'integer'],
            // Kept for existing cashier clients. Numeric searches remain a
            // student ID search; use class_id for a class ledger.
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $selectorCount = count(array_filter([
            $validated['student_id'] ?? null,
            $validated['class_id'] ?? null,
            $request->string('search')->trim()->toString() ?: null,
        ], fn ($value) => $value !== null));

        if ($selectorCount > 1) {
            return ResponseService::error(
                'Validation failed', 422,
                ['filters' => ['Provide only one of student_id, class_id, or search.']]
            );
        }

        $student = null;
        $class = null;
        $studentId = $validated['student_id'] ?? null;
        $classId = $validated['class_id'] ?? null;
        $search = $request->string('search')->trim()->toString();

        if ($studentId !== null || $search !== '') {
            $student = Student::query()
                ->where('institute_id', $instituteId)
                ->when($studentId !== null,
                    fn ($query) => $query->whereKey($studentId),
                    function ($query) use ($search, $sessionId) {
                        $query->where(function ($studentQuery) use ($search, $sessionId) {
                            $studentQuery
                                ->whereKey(is_numeric($search) ? (int) $search : 0)
                                ->orWhereHas('enrollments', function ($enrollmentQuery) use ($search, $sessionId) {
                                    $enrollmentQuery->where('session_id', $sessionId)->where('roll_number', $search);
                                })
                                ->orWhere(function ($profileQuery) use ($search) {
                                    $profileQuery
                                        ->where('first_name', 'like', "%{$search}%")
                                        ->orWhere('last_name', 'like', "%{$search}%")
                                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
                                });
                        });
                    }
                )
                ->with(['enrollments' => function ($query) use ($sessionId) {
                    $query->where('session_id', $sessionId)->with('academicClass');
                }])
                ->first();

            if ($student === null) {
                return ResponseService::error(
                    'Student not found', 404,
                    ['student_id' => ['No student matched the provided student ID, roll number, or name.']]
                );
            }
        }

        if ($classId !== null) {
            $class = AcademicClass::query()
                ->whereKey($classId)
                ->where('institute_id', $instituteId)
                ->first();

            if ($class === null) {
                return ResponseService::error('Class not found', 404, ['class_id' => ['The selected class does not belong to the active institute.']]);
            }
        }

        $vouchers = FeeVoucher::query()
            ->where('institute_id', $instituteId)
            ->where('session_id', $sessionId)
            ->when($student !== null, fn ($query) => $query->where('student_id', $student->id))
            ->when($class !== null, function ($query) use ($class, $sessionId) {
                $query->whereHas('student.enrollments', fn ($enrollmentQuery) => $enrollmentQuery
                    ->where('session_id', $sessionId)
                    ->where('class_id', $class->id));
            })
            ->with('items')
            ->when($student === null, fn ($query) => $query->with(['student.enrollments' => function ($enrollmentQuery) use ($sessionId) {
                $enrollmentQuery->where('session_id', $sessionId)->with('academicClass');
            }]))
            ->orderBy('billing_month')
            ->orderBy('student_id')
            ->get();

        $totalDue = (float) $vouchers->filter(fn (FeeVoucher $voucher) => $voucher->status !== 'paid')->sum('balance_due');
        $totalPaid = (float) $vouchers->sum('paid_amount');

        $data = [
            'student' => $student === null ? null : [
                'id' => $student->id,
                'name' => trim($student->first_name.' '.$student->last_name),
                'class' => $student->enrollments->first()?->academicClass?->name ?? 'N/A',
            ],
            'class' => $class === null ? null : [
                'id' => $class->id,
                'name' => $class->name,
            ],
            'summary' => [
                'total_due' => round($totalDue, 2),
                'total_paid' => round($totalPaid, 2),
            ],
            'vouchers' => FeeVoucherResource::collection($vouchers),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    // =====================================================================
    // API 5A: Fetch Student Ledger & Summary (Institute / Class / Section / Student)
    // =====================================================================

    public function studentLedger(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'institute_id' => ['nullable', 'integer'],
            'institute' => ['nullable', 'integer'],
            'session_id' => ['nullable', 'integer'],
            'session' => ['nullable', 'integer'],
            'class_id' => ['nullable', 'integer'],
            'class' => ['nullable', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'section' => ['nullable', 'integer'],
            'student_id' => ['nullable', 'integer'],
            'student' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:100'],
            'student_name' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:100'],
            'query' => ['nullable', 'string', 'max:100'],
            'roll_number' => ['nullable', 'string', 'max:100'],
            'billing_month' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'status' => ['nullable', 'string', 'in:unpaid,paid,partially_paid,partial,overdue,cancelled,carried_forward'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $instituteParam = $validated['institute_id'] ?? $validated['institute'] ?? null;
        $sessionParam = $validated['session_id'] ?? $validated['session'] ?? null;
        $classParam = $validated['class_id'] ?? $validated['class'] ?? null;
        $sectionParam = $validated['section_id'] ?? $validated['section'] ?? null;
        $studentParam = $validated['student_id'] ?? $validated['student'] ?? null;
        $billingMonth = $validated['billing_month'] ?? null;
        $status = $validated['status'] ?? null;
        $search = trim((string) (
            $validated['search']
            ?? $validated['name']
            ?? $validated['student_name']
            ?? $validated['q']
            ?? $validated['query']
            ?? ''
        ));
        $rollNumberParam = trim((string) ($validated['roll_number'] ?? ''));

        $user = $request->user();
        if ($instituteParam !== null) {
            $instituteId = (int) $instituteParam;
            $hasAccess = InstituteUser::query()
                ->where('user_id', $user->id)
                ->where('institute_id', $instituteId)
                ->exists();

            if (! $hasAccess) {
                return ResponseService::error('You do not have access to the selected institute.', 403);
            }
        } else {
            $instituteId = $this->activeInstituteId($request);
        }

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $institute = Institute::query()->find($instituteId);
        if ($institute === null) {
            return ResponseService::error('Institute not found', 404);
        }

        if ($sessionParam !== null) {
            $sessionId = (int) $sessionParam;
            $session = AcademicSession::query()
                ->where('institute_id', $instituteId)
                ->find($sessionId);

            if ($session === null) {
                return ResponseService::error('Validation failed', 422, [
                    'session_id' => ['The selected academic session does not belong to the active institute.'],
                ]);
            }
        } else {
            $sessionId = $this->activeSessionId($instituteId);

            if ($sessionId === null) {
                return ResponseService::error('Validation failed', 422, [
                    'session_id' => ['No active academic session exists for the active institute.'],
                ]);
            }

            $session = AcademicSession::query()->find($sessionId);
        }

        $classModel = null;
        if ($classParam !== null) {
            $classModel = AcademicClass::query()
                ->where('institute_id', $instituteId)
                ->find((int) $classParam);

            if ($classModel === null) {
                return ResponseService::error('Class not found', 404, [
                    'class_id' => ['The selected class does not belong to the active institute.'],
                ]);
            }
        }

        $sectionModel = null;
        if ($sectionParam !== null) {
            $sectionQuery = AcademicSection::query()
                ->whereHas('academicClass', fn ($q) => $q->where('institute_id', $instituteId))
                ->whereKey((int) $sectionParam);

            if ($classModel !== null) {
                $sectionQuery->where('class_id', $classModel->id);
            }

            $sectionModel = $sectionQuery->first();

            if ($sectionModel === null) {
                return ResponseService::error('Section not found', 404, [
                    'section_id' => ['The selected section does not belong to the active institute or class.'],
                ]);
            }
        }

        $studentModel = null;
        if ($studentParam !== null) {
            $studentModel = Student::query()
                ->where('institute_id', $instituteId)
                ->find((int) $studentParam);

            if ($studentModel === null) {
                return ResponseService::error('Student not found', 404, [
                    'student_id' => ['The selected student does not belong to the active institute.'],
                ]);
            }
        }

        $studentsQuery = Student::query()
            ->where('students.institute_id', $instituteId);

        if ($studentModel !== null) {
            $studentsQuery->whereKey($studentModel->id);
        }

        $studentsQuery->whereHas('enrollments', function ($enrollmentQuery) use ($sessionId, $classModel, $sectionModel) {
            $enrollmentQuery->where('session_id', $sessionId);

            if ($classModel !== null) {
                $enrollmentQuery->where('class_id', $classModel->id);
            }

            if ($sectionModel !== null) {
                $enrollmentQuery->where('section_id', $sectionModel->id);
            }
        });

        if ($search !== '') {
            $studentsQuery->where(function ($query) use ($search, $sessionId) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                    ->orWhereHas('enrollments', function ($enrollmentQuery) use ($search, $sessionId) {
                        $enrollmentQuery->where('session_id', $sessionId)->where('roll_number', 'like', "%{$search}%");
                    });

                if (is_numeric($search)) {
                    $query->orWhere('id', (int) $search);
                }
            });
        }

        if ($rollNumberParam !== '') {
            $studentsQuery->whereHas('enrollments', function ($enrollmentQuery) use ($rollNumberParam, $sessionId) {
                $enrollmentQuery->where('session_id', $sessionId)->where('roll_number', 'like', "%{$rollNumberParam}%");
            });
        }

        // Calculate overall scope financial summary across all matching students
        $allScopeStudentIds = (clone $studentsQuery)->pluck('students.id');
        $totalStudentsCount = $allScopeStudentIds->count();

        $overallTotalVouchers = 0;
        $overallTotalAmount = 0.0;
        $overallTotalPaid = 0.0;
        $overallTotalDue = 0.0;

        if ($totalStudentsCount > 0) {
            $overallVouchersQuery = FeeVoucher::query()
                ->where('institute_id', $instituteId)
                ->where('session_id', $sessionId)
                ->whereIn('student_id', $allScopeStudentIds);

            if ($billingMonth !== null) {
                $overallVouchersQuery->where('billing_month', $billingMonth);
            }

            if ($status !== null) {
                $voucherStatus = $status === 'partially_paid' ? 'partial' : $status;
                $overallVouchersQuery->where('status', $voucherStatus);
            }

            $overallSummary = $overallVouchersQuery->selectRaw('
                COUNT(id) as total_vouchers,
                COALESCE(SUM(total_amount), 0) as total_amount,
                COALESCE(SUM(paid_amount), 0) as total_paid,
                COALESCE(SUM(total_amount - paid_amount), 0) as total_due
            ')->first();

            $overallTotalVouchers = (int) ($overallSummary->total_vouchers ?? 0);
            $overallTotalAmount = (float) ($overallSummary->total_amount ?? 0);
            $overallTotalPaid = (float) ($overallSummary->total_paid ?? 0);
            $overallTotalDue = round(max(0, (float) ($overallSummary->total_due ?? 0)), 2);
        }

        $perPage = $request->integer('per_page') > 0 ? $request->integer('per_page') : 15;

        $paginatedStudents = $studentsQuery
            ->with([
                'enrollments' => function ($enrollmentQuery) use ($sessionId) {
                    $enrollmentQuery->where('session_id', $sessionId)->with(['academicClass', 'section']);
                },
            ])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate($perPage);

        $pageStudentIds = $paginatedStudents->pluck('id')->all();

        $pageVouchers = collect();
        if (! empty($pageStudentIds)) {
            $pageVouchersQuery = FeeVoucher::query()
                ->where('institute_id', $instituteId)
                ->where('session_id', $sessionId)
                ->whereIn('student_id', $pageStudentIds);

            if ($billingMonth !== null) {
                $pageVouchersQuery->where('billing_month', $billingMonth);
            }

            if ($status !== null) {
                $voucherStatus = $status === 'partially_paid' ? 'partial' : $status;
                $pageVouchersQuery->where('status', $voucherStatus);
            }

            $pageVouchers = $pageVouchersQuery->get();
        }

        $vouchersByStudent = $pageVouchers->groupBy('student_id');

        $studentList = $paginatedStudents->getCollection()->map(function (Student $student) use ($vouchersByStudent) {
            $enrollment = $student->enrollments->first();
            $studentVouchers = $vouchersByStudent->get($student->id, collect());

            $totalAmount = (float) $studentVouchers->sum('total_amount');
            $totalPaid = (float) $studentVouchers->sum('paid_amount');
            $totalDue = round(max(0, $totalAmount - $totalPaid), 2);
            $vouchersCount = $studentVouchers->count();

            $studentStatus = 'no_vouchers';
            if ($vouchersCount > 0) {
                if ($totalDue <= 0) {
                    $studentStatus = 'paid';
                } elseif ($totalPaid > 0) {
                    $studentStatus = 'partial';
                } else {
                    $studentStatus = 'unpaid';
                }
            }

            return [
                'id' => $student->id,
                'student_id' => $student->id,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'name' => trim($student->first_name.' '.$student->last_name),
                'roll_number' => $enrollment?->roll_number,
                'class_id' => $enrollment?->class_id,
                'class_name' => $enrollment?->academicClass?->name,
                'class' => $enrollment?->academicClass?->name ?? 'N/A',
                'section_id' => $enrollment?->section_id,
                'section_name' => $enrollment?->section?->name,
                'section' => $enrollment?->section?->name ?? 'N/A',
                'guardian_name' => $student->guardian_name,
                'guardian_phone' => $student->guardian_phone,
                'summary' => [
                    'total_vouchers' => $vouchersCount,
                    'total_amount' => round($totalAmount, 2),
                    'total_paid' => round($totalPaid, 2),
                    'total_due' => round($totalDue, 2),
                    'status' => $studentStatus,
                ],
            ];
        })->values();

        $studentData = null;
        if ($studentModel !== null) {
            $firstEnrollment = $paginatedStudents->first()?->enrollments?->first();
            $studentData = [
                'id' => $studentModel->id,
                'name' => trim($studentModel->first_name.' '.$studentModel->last_name),
                'class' => $firstEnrollment?->academicClass?->name ?? 'N/A',
                'section' => $firstEnrollment?->section?->name ?? 'N/A',
                'roll_number' => $firstEnrollment?->roll_number,
            ];
        }

        $classData = null;
        if ($classModel !== null) {
            $classData = [
                'id' => $classModel->id,
                'name' => $classModel->name,
            ];
        }

        $sectionData = null;
        if ($sectionModel !== null) {
            $sectionData = [
                'id' => $sectionModel->id,
                'name' => $sectionModel->name,
            ];
        }

        $pagination = [
            'current_page' => $paginatedStudents->currentPage(),
            'per_page' => $paginatedStudents->perPage(),
            'total' => $paginatedStudents->total(),
            'last_page' => $paginatedStudents->lastPage(),
            'from' => $paginatedStudents->firstItem(),
            'to' => $paginatedStudents->lastItem(),
            'has_more' => $paginatedStudents->hasMorePages(),
            'prev_page_url' => $paginatedStudents->previousPageUrl(),
            'next_page_url' => $paginatedStudents->nextPageUrl(),
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Student ledger retrieved successfully',
            'data' => [
                'institute' => $institute === null ? null : [
                    'id' => $institute->id,
                    'name' => $institute->name,
                ],
                'session' => $session === null ? null : [
                    'id' => $session->id,
                    'name' => $session->name,
                ],
                'class' => $classData,
                'section' => $sectionData,
                'student' => $studentData,
                'summary' => [
                    'total_students' => $totalStudentsCount,
                    'total_vouchers' => $overallTotalVouchers,
                    'total_amount' => round($overallTotalAmount, 2),
                    'total_paid' => round($overallTotalPaid, 2),
                    'total_due' => round($overallTotalDue, 2),
                ],
                'students' => $studentList,
                'pagination' => $pagination,
            ],
        ]);
    }

    // =====================================================================
    // API 5B: Fetch Student Fee Vouchers List
    // =====================================================================

    public function studentVouchers(Request $request): JsonResponse
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

        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $month = $validated['month'];
        $vouchers = FeeVoucher::query()
            ->where('institute_id', $instituteId)
            ->where('session_id', $sessionId)
            ->where('billing_month', $month)
            ->with(['student.enrollments' => fn ($query) => $query->where('session_id', $sessionId)->with('academicClass'), 'items'])
            ->orderBy('student_id')
            ->get()
            ->map(function (FeeVoucher $voucher) {
                $student = $voucher->student;
                $enrollment = $student?->enrollments?->first();

                return [
                    'voucher_id' => $voucher->id,
                    'student_name' => trim(($student?->first_name ?? '').' '.($student?->last_name ?? '')),
                    'class_name' => $enrollment?->academicClass?->name,
                    'due_date' => $voucher->due_date?->toDateString(),
                    'total_amount' => $voucher->total_amount,
                    'late_fee_amount' => $voucher->late_fee_amount,
                    'total_after_due_date' => round((float) $voucher->total_amount + (float) $voucher->late_fee_amount, 2),
                    'status' => $voucher->status,
                    'items' => $voucher->items->map(fn ($item) => [
                        'description' => $item->description,
                        'amount' => $item->amount,
                        'type' => $item->type,
                    ])->values(),
                ];
            });

        return ResponseService::success($vouchers, 'Fee vouchers retrieved successfully');
    }

    // =====================================================================
    // API 6: Collect Payment
    // =====================================================================

    public function collect(CollectFeePaymentRequest $request, ?int $voucherId = null): JsonResponse
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
        $amountPaid = (float) $validated['paid_amount'];
        $waiveLateFee = (bool) ($validated['waive_late_fee'] ?? false);
        $collectedByUserId = $request->user()->id;
        $paymentDate = now()->toDateString();

        $result = DB::transaction(function () use ($instituteId, $sessionId, $validated, $amountPaid, $waiveLateFee, $collectedByUserId, $paymentDate) {
            $voucher = FeeVoucher::query()
                ->where('institute_id', $instituteId)
                ->where('session_id', $sessionId)
                ->with('items')
                ->lockForUpdate()
                ->find($validated['voucher_id']);

            if ($voucher === null) {
                return null;
            }

            $lateFeeApplies = now()->toDateString() > $voucher->due_date->toDateString()
                && ! $voucher->late_fee_waived
                && (float) $voucher->paid_amount < (float) $voucher->total_amount;
            $lateFeeDue = $lateFeeApplies && ! $waiveLateFee ? (float) $voucher->late_fee_amount : 0.0;
            $requiredAmount = (float) $voucher->total_amount + $lateFeeDue;
            $balanceDue = max(0, $requiredAmount - (float) $voucher->paid_amount);

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
                'payment_date' => $paymentDate,
                'payment_method' => $validated['payment_method'],
                'late_fee_waived' => $waiveLateFee && $lateFeeApplies,
                'collected_by_user_id' => $collectedByUserId,
            ]);

            $newPaidAmount = (float) $voucher->paid_amount + $amountPaid;

            $voucher->update([
                'paid_amount' => $newPaidAmount,
                'late_fee_waived' => $voucher->late_fee_waived || ($waiveLateFee && $lateFeeApplies),
                'status' => $newPaidAmount >= ($waiveLateFee && $lateFeeApplies ? (float) $voucher->total_amount : $requiredAmount) ? 'paid' : 'partial',
            ]);

            return ['payment' => $payment, 'voucher' => $voucher];
        });

        if ($result === null) {
            return ResponseService::notFound('Fee voucher not found');
        }

        if (isset($result['error'])) {
            return ResponseService::error('Validation failed', 422, ['paid_amount' => [$result['error']]]);
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
    // API 11: Fee Records (filtered list — no pagination)
    // =====================================================================

    public function records(FeeRecordsRequest $request): JsonResponse
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $validated = $request->validated();

        $error = $this->recordsScopeError($instituteId, $validated);

        if ($error !== null) {
            return $error;
        }

        $sessionId = $validated['session_id'] ?? $this->activeSessionId($instituteId);
        $status = ($validated['status'] ?? null) === 'partially_paid' ? 'partial' : ($validated['status'] ?? null);
        $search = trim((string) ($validated['search'] ?? '')) !== '' ? $validated['search'] : null;

        $query = FeeVoucher::query()
            ->where('institute_id', $instituteId)
            ->with([
                'session:id,name',
                'student:id,institute_id,first_name,last_name,guardian_name,guardian_phone',
                'student.enrollments' => fn ($enrollmentQuery) => $enrollmentQuery
                    ->when($sessionId !== null, fn ($enrollmentQuery) => $enrollmentQuery->where('session_id', $sessionId))
                    ->with(['academicClass:id,name', 'section:id,name']),
                'items',
                'payments' => fn ($paymentQuery) => $paymentQuery->with('collectedBy:id,name'),
            ])
            ->when($sessionId !== null, fn ($query) => $query->where('session_id', $sessionId))
            ->when($validated['student_id'] ?? null, fn ($query, $studentId) => $query->where('student_id', $studentId))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($validated['billing_month'] ?? null, fn ($query, $billingMonth) => $query->where('billing_month', $billingMonth))
            ->when(($validated['class_id'] ?? null) !== null || ($validated['section_id'] ?? null) !== null, fn ($query) => $query->whereHas('student.enrollments', function ($enrollmentQuery) use ($validated, $sessionId) {
                $enrollmentQuery->when($sessionId !== null, fn ($enrollmentQuery) => $enrollmentQuery->where('session_id', $sessionId));

                if (($validated['class_id'] ?? null) !== null) {
                    $enrollmentQuery->where('class_id', $validated['class_id']);
                }

                if (($validated['section_id'] ?? null) !== null) {
                    $enrollmentQuery->where('section_id', $validated['section_id']);
                }
            }))
            ->when($search !== null, fn ($query) => $query->whereHas('student', function ($studentQuery) use ($search, $sessionId) {
                $studentQuery->where(function ($studentQuery) use ($search, $sessionId) {
                    $studentQuery->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhereHas('enrollments', function ($enrollmentQuery) use ($search, $sessionId) {
                            $enrollmentQuery->when($sessionId !== null, fn ($enrollmentQuery) => $enrollmentQuery->where('session_id', $sessionId))
                                ->where('roll_number', 'like', '%'.$search.'%');
                        });
                });
            }));

        if (array_key_exists('due_date_from', $validated) || array_key_exists('due_date_to', $validated)) {
            if (($validated['due_date_from'] ?? null) !== null) {
                $query->whereDate('due_date', '>=', $validated['due_date_from']);
            }

            if (($validated['due_date_to'] ?? null) !== null) {
                $query->whereDate('due_date', '<=', $validated['due_date_to']);
            }
        }

        $summary = (clone $query)
            ->selectRaw('
                COUNT(*) as total_vouchers,
                COALESCE(SUM(total_amount), 0) as total_amount,
                COALESCE(SUM(paid_amount), 0) as total_paid,
                COALESCE(SUM(total_amount - paid_amount), 0) as total_due
            ')
            ->first();

        $statusCounts = (clone $query)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get()
            ->pluck('total', 'status');

        $records = $query
            ->orderByDesc('billing_month')
            ->orderByDesc('id')
            ->get();

        $records->transform(function (FeeVoucher $voucher) {
            $student = $voucher->student;
            $enrollment = $student?->enrollments?->first();

            return [
                'voucher_id' => $voucher->id,
                'batch_id' => $voucher->batch_id,
                'billing_month' => $voucher->billing_month,
                'due_date' => $voucher->due_date?->toDateString(),
                'total_amount' => round((float) $voucher->total_amount, 2),
                'paid_amount' => round((float) $voucher->paid_amount, 2),
                'balance_due' => round($voucher->balance_due, 2),
                'status' => $voucher->status,
                'session' => $voucher->session ? [
                    'id' => $voucher->session->id,
                    'name' => $voucher->session->name,
                ] : null,
                'student' => $student ? [
                    'id' => $student->id,
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'full_name' => trim(($student->first_name ?? '').' '.($student->last_name ?? '')),
                    'roll_number' => $enrollment?->roll_number,
                    'class' => $enrollment?->academicClass ? [
                        'id' => $enrollment->academicClass->id,
                        'name' => $enrollment->academicClass->name,
                    ] : null,
                    'section' => $enrollment?->section ? [
                        'id' => $enrollment->section->id,
                        'name' => $enrollment->section->name,
                    ] : null,
                    'guardian_name' => $student->guardian_name,
                    'guardian_phone' => $student->guardian_phone,
                ] : null,
                'items' => $voucher->items
                    ->groupBy('description')
                    ->map(fn ($items, $description) => [
                        'description' => $description,
                        'amount' => round((float) $items->sum('amount'), 2),
                    ])
                    ->values(),
                'payments' => $voucher->payments->map(fn (FeePayment $payment) => [
                    'id' => $payment->id,
                    'amount_paid' => round((float) $payment->amount_paid, 2),
                    'payment_date' => $payment->payment_date?->toDateString(),
                    'payment_method' => $payment->payment_method,
                    'collected_by' => $payment->collectedBy ? [
                        'id' => $payment->collectedBy->id,
                        'name' => $payment->collectedBy->name,
                    ] : null,
                ])->values(),
            ];
        });

        return ResponseService::success([
            'institute' => ['id' => $instituteId],
            'session' => $sessionId === null ? null : AcademicSession::query()->find($sessionId, ['id', 'name']),
            'filters' => [
                'session_id' => $sessionId,
                'class_id' => $validated['class_id'] ?? null,
                'section_id' => $validated['section_id'] ?? null,
                'student_id' => $validated['student_id'] ?? null,
                'status' => $validated['status'] ?? null,
                'billing_month' => $validated['billing_month'] ?? null,
                'due_date_from' => $validated['due_date_from'] ?? null,
                'due_date_to' => $validated['due_date_to'] ?? null,
                'search' => $search,
            ],
            'summary' => [
                'total_vouchers' => (int) ($summary->total_vouchers ?? 0),
                'total_amount' => round((float) ($summary->total_amount ?? 0), 2),
                'total_paid' => round((float) ($summary->total_paid ?? 0), 2),
                'total_due' => round((float) $records->sum(fn (FeeVoucher $voucher) => $voucher->balance_due), 2),
                'status_counts' => [
                    'unpaid_count' => (int) $statusCounts->get('unpaid', 0),
                    'partial_count' => (int) $statusCounts->get('partial', 0),
                    'paid_count' => (int) $statusCounts->get('paid', 0),
                    'overdue_count' => (int) $statusCounts->get('overdue', 0),
                    'cancelled_count' => (int) $statusCounts->get('cancelled', 0),
                    'carried_forward_count' => (int) $statusCounts->get('carried_forward', 0),
                ],
            ],
            'records' => $records->values(),
        ], 'Fee records retrieved successfully');
    }

    private function recordsScopeError(int $instituteId, array $validated): ?JsonResponse
    {
        if (isset($validated['session_id']) && ! AcademicSession::query()->whereKey($validated['session_id'])->where('institute_id', $instituteId)->exists()) {
            return ResponseService::error('Validation failed', 422, ['session_id' => ['The selected session does not belong to the active institute.']]);
        }

        if (isset($validated['class_id']) && ! AcademicClass::query()->whereKey($validated['class_id'])->where('institute_id', $instituteId)->exists()) {
            return ResponseService::error('Validation failed', 422, ['class_id' => ['The selected class does not belong to the active institute.']]);
        }

        if (isset($validated['section_id'])) {
            $section = AcademicSection::query()->find($validated['section_id']);

            if ($section === null || ($validated['class_id'] ?? null) !== null && $section->class_id !== (int) $validated['class_id']) {
                return ResponseService::error('Validation failed', 422, ['section_id' => ['The selected section does not belong to the selected class.']]);
            }

            if (($validated['class_id'] ?? null) === null && ! AcademicClass::query()->whereKey($section->class_id)->where('institute_id', $instituteId)->exists()) {
                return ResponseService::error('Validation failed', 422, ['section_id' => ['The selected section does not belong to the active institute.']]);
            }
        }

        if (isset($validated['student_id']) && ! Student::query()->whereKey($validated['student_id'])->where('institute_id', $instituteId)->exists()) {
            return ResponseService::error('Validation failed', 422, ['student_id' => ['The selected student does not belong to the active institute.']]);
        }

        return null;
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
