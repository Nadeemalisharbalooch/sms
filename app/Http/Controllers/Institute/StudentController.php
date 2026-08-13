<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institute\StoreStudentRequest;
use App\Http\Requests\Institute\PromoteClassRequest;
use App\Http\Requests\Institute\PromoteStudentRequest;
use App\Http\Requests\Institute\UpdateStudentRequest;
use App\Http\Requests\Institute\UpdateStudentEnrollmentRequest;
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

        $currentSessionId = $this->activeSessionId($instituteId);
        $validated = $request->validated();
        $error = $this->validatePromotionScope($instituteId, $currentSessionId, [
            ...$validated,
            'class_id' => $validated['target_class_id'],
            'section_id' => $validated['target_section_id'] ?? null,
        ]);

        if ($error !== null) {
            return $error;
        }

        if (! AcademicClass::query()->whereKey($validated['source_class_id'])->where('institute_id', $instituteId)->exists()) {
            return ResponseService::error('Validation failed', 422, ['source_class_id' => ['The selected class does not belong to the active institute.']]);
        }

        if (($validated['source_section_id'] ?? null) !== null && ! AcademicSection::query()
            ->whereKey($validated['source_section_id'])
            ->where('class_id', $validated['source_class_id'])
            ->exists()) {
            return ResponseService::error('Validation failed', 422, ['source_section_id' => ['The selected section does not belong to the selected source class.']]);
        }

        $enrollments = Enrollment::query()
            ->where('session_id', $currentSessionId)
            ->where('class_id', $validated['source_class_id'])
            ->when(isset($validated['source_section_id']), fn ($query) => $query->where('section_id', $validated['source_section_id']))
            ->get();

        if ($enrollments->isEmpty()) {
            return ResponseService::error('Validation failed', 422, ['source_class_id' => ['No students are enrolled in the selected current-session class.']]);
        }

        $studentIds = $enrollments->pluck('student_id');
        if (Enrollment::query()->where('session_id', $validated['target_session_id'])->whereIn('student_id', $studentIds)->exists()) {
            return $this->targetEnrollmentExistsError();
        }

        DB::transaction(function () use ($enrollments, $validated) {
            foreach ($enrollments as $enrollment) {
                $enrollment->update(['result_status' => $validated['status']]);
                Enrollment::create([
                    'student_id' => $enrollment->student_id,
                    'session_id' => $validated['target_session_id'],
                    'class_id' => $validated['target_class_id'],
                    'section_id' => $validated['target_section_id'] ?? null,
                    'roll_number' => null,
                ]);
            }
        });

        return ResponseService::success(['promoted_count' => $enrollments->count()], 'Class promoted successfully');
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
