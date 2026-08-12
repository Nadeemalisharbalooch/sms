<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institute\StoreStudentRequest;
use App\Http\Requests\Institute\UpdateStudentRequest;
use App\Http\Resources\Institute\StudentResource;
use App\Models\AcademicClass;
use App\Models\AcademicSection;
use App\Models\AcademicSession;
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
