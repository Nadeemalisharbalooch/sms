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
use App\Models\AcademicSection;
use App\Models\AcademicSession;
use App\Models\FeeCategory;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\FeeVoucher;
use App\Models\Institute;
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

                // A student can have a class fee and an individual assignment
                // for the same category. Store one line per fee name so a
                // voucher never contains duplicate-looking items.
                $lineItems = $lineItems
                    ->groupBy('fee_name')
                    ->map(fn ($items, $feeName) => [
                        'fee_name' => $feeName,
                        'amount' => round((float) $items->sum('amount'), 2),
                    ])
                    ->values();

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
            'status' => ['nullable', 'string', 'in:unpaid,paid,partially_paid,partial,overdue,cancelled'],
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

        $sessionId = $request->filled('session_id')
            ? (int) $request->input('session_id')
            : $this->activeSessionId($instituteId);

        if ($sessionId === null && ! $request->boolean('all_sessions')) {
            return ResponseService::error('Validation failed', 422, [
                'session_id' => ['No active academic session exists for the active institute.'],
            ]);
        }

        $validated = $request->validate([
            'student_id' => ['required', 'integer'],
            'session_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'in:unpaid,paid,partially_paid,overdue,cancelled'],
            'billing_month' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $student = Student::query()
            ->where('institute_id', $instituteId)
            ->whereKey($validated['student_id'])
            ->first();

        if ($student === null) {
            return ResponseService::error('Student not found', 404, [
                'student_id' => ['The selected student does not belong to the active institute.'],
            ]);
        }

        $vouchers = FeeVoucher::query()
            ->where('institute_id', $instituteId)
            ->where('student_id', $student->id)
            ->when(! $request->boolean('all_sessions') && $sessionId !== null, fn ($query) => $query->where('session_id', $sessionId))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $validated['status']))
            ->when($request->filled('billing_month'), fn ($query) => $query->where('billing_month', $validated['billing_month']))
            ->with('items')
            ->orderByDesc('billing_month')
            ->orderByDesc('id')
            ->get();

        $studentData = $student->load([
            'enrollments.academicClass',
            'enrollments.section',
            'enrollments.session',
        ]);
        $enrollment = $studentData->enrollments->first();

        $feesSummary = [
            'total_vouchers' => $vouchers->count(),
            'total_amount' => round((float) $vouchers->sum('total_amount'), 2),
            'total_paid' => round((float) $vouchers->sum('paid_amount'), 2),
            'total_due' => round((float) $vouchers->sum('balance_due'), 2),
            'paid_vouchers_count' => $vouchers->where('status', 'paid')->count(),
            'unpaid_vouchers_count' => $vouchers->where('status', 'unpaid')->count(),
            'partially_paid_vouchers_count' => $vouchers->where('status', 'partially_paid')->count(),
            'overdue_vouchers_count' => $vouchers->where('status', 'overdue')->count(),
        ];

        return ResponseService::success([
            'student' => [
                'id' => $student->id,
                'name' => trim($student->first_name.' '.$student->last_name),
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'roll_number' => $enrollment?->roll_number,
                'class' => $enrollment?->academicClass?->name ?? 'N/A',
                'section' => $enrollment?->section?->name ?? 'N/A',
                'session' => $enrollment?->session?->name ?? 'N/A',
            ],
            'fees_summary' => $feesSummary,
            'vouchers' => FeeVoucherResource::collection($vouchers),
        ], 'Student fee vouchers retrieved successfully');
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
