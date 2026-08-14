<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institute\PromoteClassRequest;
use App\Http\Requests\Institute\PromoteStudentRequest;
use App\Http\Requests\Institute\StoreStudentRequest;
use App\Http\Requests\Institute\UpdateStudentEnrollmentRequest;
use App\Http\Requests\Institute\UpdateStudentRequest;
use App\Http\Resources\Institute\StudentResource;
use App\Models\AcademicClass;
use App\Models\AcademicSection;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $request->validate([
            'session_id' => ['nullable', 'integer'],
            'class_id' => ['nullable', 'integer'],
            'section_id' => ['nullable', 'integer'],
        ]);

        $students = Student::query()
            ->where('institute_id', $instituteId)
            ->when(
                $request->filled('session_id') || $request->filled('class_id') || $request->filled('section_id'),
                function ($query) use ($request) {
                    $query->whereHas('enrollments', function ($enrollmentQuery) use ($request) {
                        $enrollmentQuery
                            ->when($request->filled('session_id'), fn ($q) => $q->where('session_id', $request->integer('session_id')))
                            ->when($request->filled('class_id'), fn ($q) => $q->where('class_id', $request->integer('class_id')))
                            ->when($request->filled('section_id'), fn ($q) => $q->where('section_id', $request->integer('section_id')));
                    });
                }
            )
            ->with(['enrollments' => function ($query) use ($request) {
                $query
                    ->when($request->filled('session_id'), fn ($q) => $q->where('session_id', $request->integer('session_id')))
                    ->when($request->filled('class_id'), fn ($q) => $q->where('class_id', $request->integer('class_id')))
                    ->when($request->filled('section_id'), fn ($q) => $q->where('section_id', $request->integer('section_id')))
                    ->latest('id');
            }])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate();

        return ResponseService::success(StudentResource::collection($students), 'Students retrieved successfully');
    }

    public function store(StoreStudentRequest $request)
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $validated = $request->validated();
        $error = $this->validateEnrollmentScope($instituteId, $validated);

        if ($error !== null) {
            return $error;
        }

        $error = $this->validateUniqueStudentProfile($instituteId, $validated);

        if ($error !== null) {
            return $error;
        }

        $student = DB::transaction(function () use ($validated, $instituteId) {
            $student = Student::create([
                ...collect($validated)->except(['session_id', 'class_id', 'section_id', 'roll_number'])->all(),
                'institute_id' => $instituteId,
                'admission_date' => $validated['admission_date'] ?? now()->toDateString(),
            ]);

            $student->enrollments()->create([
                'session_id' => $validated['session_id'],
                'class_id' => $validated['class_id'],
                'section_id' => $validated['section_id'] ?? null,
                'roll_number' => $validated['roll_number'] ?? null,
            ]);

            return $student;
        });

        return ResponseService::success(
            new StudentResource($student->load('enrollments')),
            'Student admitted successfully',
            201
        );
    }

    public function show(Request $request, Student $student)
    {
        if (! $this->belongsToActiveInstitute($request, $student)) {
            return ResponseService::notFound('Student not found');
        }

        return ResponseService::success(
            new StudentResource($student->load(['enrollments' => fn ($query) => $query->latest('id')])),
            'Student retrieved successfully'
        );
    }

    public function update(UpdateStudentRequest $request, Student $student)
    {
        if (! $this->belongsToActiveInstitute($request, $student)) {
            return ResponseService::notFound('Student not found');
        }

        $validated = $request->validated();
        $rollNumber = $validated['roll_number'] ?? null;

        $profile = [
            'first_name' => $validated['first_name'] ?? $student->first_name,
            'dob' => $validated['dob'] ?? $student->dob->toDateString(),
            'guardian_name' => $validated['guardian_name'] ?? $student->guardian_name,
            'roll_number' => $request->has('roll_number')
                ? $rollNumber
                : $student->enrollments()->latest('id')->value('roll_number'),
        ];

        $error = $this->validateUniqueStudentProfile($student->institute_id, $profile, $student->id);

        if ($error !== null) {
            return $error;
        }

        unset($validated['roll_number']);

        DB::transaction(function () use ($student, $validated, $request, $rollNumber) {
            $student->update($validated);

            if ($request->has('roll_number')) {
                $student->enrollments()->latest('id')->first()?->update(['roll_number' => $rollNumber]);
            }
        });

        return ResponseService::success(
            new StudentResource($student->fresh()->load(['enrollments' => fn ($query) => $query->latest('id')])),
            'Student updated successfully'
        );
    }

    public function updateEnrollment(UpdateStudentEnrollmentRequest $request, Student $student)
    {
        if (! $this->belongsToActiveInstitute($request, $student)) {
            return ResponseService::notFound('Student not found');
        }

        $sessionId = AcademicSession::query()
            ->where('institute_id', $student->institute_id)
            ->where('is_active', true)
            ->value('id');

        if ($sessionId === null) {
            return ResponseService::error('Validation failed', 422, [
                'session_id' => ['No active academic session exists for the active institute.'],
            ]);
        }

        $validated = [
            ...$request->validated(),
            'session_id' => (int) $sessionId,
        ];

        $error = $this->validateEnrollmentScope($student->institute_id, $validated);

        if ($error !== null) {
            return $error;
        }

        DB::transaction(function () use ($student, $validated) {
            // The student/session unique index and this lookup ensure there can only be one current-session enrollment.
            $student->enrollments()->updateOrCreate(
                ['session_id' => $validated['session_id']],
                [
                    'class_id' => $validated['class_id'],
                    'section_id' => $validated['section_id'] ?? null,
                    'roll_number' => $validated['roll_number'] ?? null,
                ]
            );
        });

        return ResponseService::success(
            new StudentResource($student->fresh()->load(['enrollments' => fn ($query) => $query->latest('id')])),
            'Student enrollment updated successfully'
        );
    }

    public function promote(PromoteStudentRequest $request, Student $student)
    {
        if (! $this->belongsToActiveInstitute($request, $student)) {
            return ResponseService::notFound('Student not found');
        }

        $currentSessionId = $this->activeSessionId($student->institute_id);
        $validated = $request->validated();
        $error = $this->validatePromotionScope($student->institute_id, $currentSessionId, $validated);

        if ($error !== null) {
            return $error;
        }

        $currentEnrollment = $student->enrollments()->where('session_id', $currentSessionId)->first();

        if ($currentEnrollment === null) {
            return ResponseService::error('Validation failed', 422, [
                'student' => ['The student is not enrolled in the current academic session.'],
            ]);
        }

        if ($student->enrollments()->where('session_id', $validated['target_session_id'])->exists()) {
            return $this->targetEnrollmentExistsError();
        }

        DB::transaction(function () use ($student, $currentEnrollment, $validated) {
            $currentEnrollment->update(['result_status' => $validated['status']]);
            $student->enrollments()->create([
                'session_id' => $validated['target_session_id'],
                'class_id' => $validated['target_class_id'],
                'section_id' => $validated['target_section_id'] ?? null,
                'roll_number' => $validated['roll_number'] ?? null,
            ]);
        });

        return ResponseService::success(
            new StudentResource($student->fresh()->load(['enrollments' => fn ($query) => $query->where('session_id', $validated['target_session_id'])])),
            'Student promoted successfully'
        );
    }

    public function promoteClass(PromoteClassRequest $request)
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $validated = $request->validated();
        $errors = $this->validateBulkPromotionScope($instituteId, $validated);

        if ($errors !== []) {
            return ResponseService::error('Validation failed', 422, $errors);
        }

        DB::transaction(function () use ($validated) {
            foreach ($validated['promotions'] as $promotion) {
                Enrollment::query()
                    ->where('student_id', $promotion['student_id'])
                    ->where('session_id', $validated['from_session_id'])
                    ->update(['result_status' => $promotion['promotion_status']]);

                if (in_array($promotion['promotion_status'], ['promoted', 'retained'], true)) {
                    Enrollment::query()->updateOrCreate(
                        [
                            'student_id' => $promotion['student_id'],
                            'session_id' => $validated['to_session_id'],
                        ],
                        [
                            'class_id' => $promotion['class_id'],
                            'section_id' => $promotion['section_id'] ?? null,
                            'roll_number' => $promotion['roll_number'] ?? null,
                        ]
                    );
                }
            }
        });

        return ResponseService::success([
            'processed_count' => count($validated['promotions']),
            'promoted_count' => collect($validated['promotions'])->where('promotion_status', 'promoted')->count(),
            'retained_count' => collect($validated['promotions'])->where('promotion_status', 'retained')->count(),
            'graduated_count' => collect($validated['promotions'])->where('promotion_status', 'graduated')->count(),
            'left_count' => collect($validated['promotions'])->where('promotion_status', 'left')->count(),
        ], 'Student promotions processed successfully');
    }

    public function destroy(Request $request, Student $student)
    {
        if (! $this->belongsToActiveInstitute($request, $student)) {
            return ResponseService::notFound('Student not found');
        }

        $student->delete();

        return ResponseService::success(null, 'Student deleted successfully');
    }

    private function validateEnrollmentScope(int $instituteId, array $validated): ?JsonResponse
    {
        if (! AcademicSession::query()->whereKey($validated['session_id'])->where('institute_id', $instituteId)->exists()) {
            return ResponseService::error('Validation failed', 422, ['session_id' => ['The selected session does not belong to the active institute.']]);
        }

        if (! AcademicClass::query()->whereKey($validated['class_id'])->where('institute_id', $instituteId)->exists()) {
            return ResponseService::error('Validation failed', 422, ['class_id' => ['The selected class does not belong to the active institute.']]);
        }

        if (($validated['section_id'] ?? null) !== null && ! AcademicSection::query()
            ->whereKey($validated['section_id'])
            ->where('class_id', $validated['class_id'])
            ->exists()) {
            return ResponseService::error('Validation failed', 422, ['section_id' => ['The selected section does not belong to the selected class.']]);
        }

        return null;
    }

    private function validatePromotionScope(int $instituteId, ?int $currentSessionId, array $validated): ?JsonResponse
    {
        if ($currentSessionId === null) {
            return ResponseService::error('Validation failed', 422, [
                'session_id' => ['No active academic session exists for the active institute.'],
            ]);
        }

        if ((int) $validated['target_session_id'] === $currentSessionId) {
            return ResponseService::error('Validation failed', 422, [
                'target_session_id' => ['The promotion session must be different from the current academic session.'],
            ]);
        }

        return $this->validateEnrollmentScope($instituteId, [
            'session_id' => $validated['target_session_id'],
            'class_id' => $validated['class_id'] ?? $validated['target_class_id'],
            'section_id' => $validated['section_id'] ?? $validated['target_section_id'] ?? null,
        ]);
    }

    private function targetEnrollmentExistsError(): JsonResponse
    {
        return ResponseService::error('Validation failed', 422, [
            'target_session_id' => ['One or more selected students are already enrolled in the target academic session.'],
        ]);
    }

    private function validateBulkPromotionScope(int $instituteId, array $validated): array
    {
        $errors = [];

        $sessionCount = AcademicSession::query()
            ->where('institute_id', $instituteId)
            ->whereIn('id', [$validated['from_session_id'], $validated['to_session_id']])
            ->count();

        if ($sessionCount !== 2) {
            $errors['session_id'] = ['Both sessions must belong to the active institute.'];
        }

        $studentIds = collect($validated['promotions'])->pluck('student_id');
        $enrolledStudentIds = Enrollment::query()
            ->where('session_id', $validated['from_session_id'])
            ->whereIn('student_id', $studentIds)
            ->pluck('student_id');
        $instituteStudentIds = Student::query()
            ->where('institute_id', $instituteId)
            ->whereIn('id', $studentIds)
            ->pluck('id');

        foreach ($validated['promotions'] as $index => $promotion) {
            if (! $instituteStudentIds->contains($promotion['student_id'])) {
                $errors["promotions.$index.student_id"][] = 'The selected student does not belong to the active institute.';
            }

            if (! $enrolledStudentIds->contains($promotion['student_id'])) {
                $errors["promotions.$index.student_id"][] = 'The student is not enrolled in the from session.';
            }

            $needsEnrollment = in_array($promotion['promotion_status'], ['promoted', 'retained'], true);

            if ($needsEnrollment && $promotion['class_id'] === null) {
                $errors["promotions.$index.class_id"][] = 'A class is required for promoted or retained students.';

                continue;
            }

            if (! $needsEnrollment) {
                continue;
            }

            $classBelongsToInstitute = AcademicClass::query()
                ->whereKey($promotion['class_id'])
                ->where('institute_id', $instituteId)
                ->exists();

            if (! $classBelongsToInstitute) {
                $errors["promotions.$index.class_id"][] = 'The selected class does not belong to the active institute.';
            }

            if (($promotion['section_id'] ?? null) !== null && ! AcademicSection::query()
                ->whereKey($promotion['section_id'])
                ->where('class_id', $promotion['class_id'])
                ->exists()) {
                $errors["promotions.$index.section_id"][] = 'The selected section does not belong to the selected class.';
            }
        }

        return $errors;
    }

    private function activeSessionId(int $instituteId): ?int
    {
        $sessionId = AcademicSession::query()
            ->where('institute_id', $instituteId)
            ->where('is_active', true)
            ->value('id');

        return $sessionId === null ? null : (int) $sessionId;
    }

    private function validateUniqueStudentProfile(int $instituteId, array $profile, ?int $ignoreStudentId = null): ?JsonResponse
    {
        $rollNumber = $profile['roll_number'] ?? null;

        $duplicateExists = Student::query()
            ->where('institute_id', $instituteId)
            ->where('first_name', $profile['first_name'])
            ->whereDate('dob', $profile['dob'])
            ->where('guardian_name', $profile['guardian_name'])
            ->when($ignoreStudentId !== null, fn ($query) => $query->whereKeyNot($ignoreStudentId))
            ->whereHas('enrollments', function ($query) use ($rollNumber) {
                $rollNumber === null
                    ? $query->whereNull('roll_number')
                    : $query->where('roll_number', $rollNumber);
            })
            ->exists();

        if (! $duplicateExists) {
            return null;
        }

        return ResponseService::error('Validation failed', 422, [
            'first_name' => ['A student with the same first name, date of birth, guardian name, and roll number already exists.'],
        ]);
    }

    private function activeInstituteId(Request $request): ?int
    {
        $instituteId = InstituteUser::query()->where('user_id', $request->user()->id)->where('is_active', true)->value('institute_id');

        return $instituteId === null ? null : (int) $instituteId;
    }

    private function belongsToActiveInstitute(Request $request, Student $student): bool
    {
        return $student->institute_id === $this->activeInstituteId($request);
    }
}
