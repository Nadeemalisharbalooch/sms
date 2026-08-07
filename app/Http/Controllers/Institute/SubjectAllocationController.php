<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institute\StoreSubjectAllocationRequest;
use App\Http\Resources\Institute\AcademicClassResource;
use App\Http\Resources\Institute\SubjectAllocationResource;
use App\Models\AcademicClass;
use App\Models\AcademicSection;
use App\Models\AcademicSession;
use App\Models\InstituteUser;
use App\Models\Subject;
use App\Models\SubjectAllocation;
use App\Models\User;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class SubjectAllocationController extends Controller
{
    public function index(Request $request)
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $allocations = SubjectAllocation::query()
            ->with(['session', 'academicClass', 'section', 'subject', 'teacher'])
            ->whereHas('academicClass', fn ($query) => $query->where('institute_id', $instituteId))
            ->when($request->integer('session_id'), fn ($query, int $sessionId) => $query->where('session_id', $sessionId))
            ->when($request->integer('class_id'), fn ($query, int $classId) => $query->where('class_id', $classId))
            ->when($request->integer('section_id'), fn ($query, int $sectionId) => $query->where('section_id', $sectionId))
            ->when($request->integer('subject_id'), fn ($query, int $subjectId) => $query->where('subject_id', $subjectId))
            ->latest()
            ->paginate();

        return ResponseService::success(
            SubjectAllocationResource::collection($allocations),
            'Subject allocations retrieved successfully'
        );
    }

    public function store(StoreSubjectAllocationRequest $request)
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $validated = $request->validated();
        $error = $this->allocationError($validated, $instituteId);

        if ($error !== null) {
            return $error;
        }

        $created = [];

        foreach ($validated['allocations'] as $allocation) {
            $record = SubjectAllocation::updateOrCreate(
                [
                    'session_id' => $validated['session_id'],
                    'class_id' => $validated['class_id'],
                    'section_id' => $validated['section_id'],
                    'subject_id' => $allocation['subject_id'],
                ],
                [
                    'teacher_user_id' => $allocation['teacher_id'],
                ]
            );

            $created[] = $record;
        }

        $created = \Illuminate\Database\Eloquent\Collection::make($created)
            ->load(['session', 'academicClass', 'section', 'subject', 'teacher']);

        return ResponseService::success(
            SubjectAllocationResource::collection($created),
            'Subject teachers assigned successfully',
            201
        );
    }

    public function show(Request $request, SubjectAllocation $subjectAllocation)
    {
        if (! $this->belongsToActiveInstitute($request, $subjectAllocation)) {
            return ResponseService::notFound('Subject allocation not found');
        }

        return ResponseService::success(
            new SubjectAllocationResource($subjectAllocation->load(['session', 'academicClass', 'section', 'subject', 'teacher'])),
            'Subject allocation retrieved successfully'
        );
    }

    public function classBySection(Request $request, int $sectionId)
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $class = AcademicClass::query()
            ->where('institute_id', $instituteId)
            ->whereHas('sections', fn ($query) => $query->whereKey($sectionId))
            ->first();

        if ($class === null) {
            return ResponseService::notFound('Class not found for the given section');
        }

        return ResponseService::success(
            new AcademicClassResource($class),
            'Class retrieved successfully'
        );
    }

    public function destroy(Request $request, SubjectAllocation $subjectAllocation)
    {
        if (! $this->belongsToActiveInstitute($request, $subjectAllocation)) {
            return ResponseService::notFound('Subject allocation not found');
        }

        $subjectAllocation->delete();

        return ResponseService::success(null, 'Subject allocation removed successfully');
    }

    private function allocationError(array $validated, int $instituteId): ?\Illuminate\Http\JsonResponse
    {
        // Validate session belongs to the active institute
        if (! AcademicSession::query()
            ->whereKey($validated['session_id'])
            ->where('institute_id', $instituteId)
            ->exists()) {
            return ResponseService::error('Validation failed', 422, [
                'session_id' => ['The selected session must belong to the active institute.'],
            ]);
        }

        // Validate class belongs to the active institute
        if (! AcademicClass::query()
            ->whereKey($validated['class_id'])
            ->where('institute_id', $instituteId)
            ->exists()) {
            return ResponseService::error('Validation failed', 422, [
                'class_id' => ['The selected class must belong to the active institute.'],
            ]);
        }

        // Validate section (if provided) belongs to the class
        if ($validated['section_id'] !== null) {
            if (! AcademicSection::query()
                ->whereKey($validated['section_id'])
                ->where('class_id', $validated['class_id'])
                ->exists()) {
                return ResponseService::error('Validation failed', 422, [
                    'section_id' => ['The selected section must belong to the selected class.'],
                ]);
            }
        }

        // Validate each subject belongs to the active institute
        $subjectIds = array_column($validated['allocations'], 'subject_id');
        $subjectsCount = Subject::query()
            ->where('institute_id', $instituteId)
            ->whereIn('id', $subjectIds)
            ->count();

        if ($subjectsCount !== count($subjectIds)) {
            return ResponseService::error('Validation failed', 422, [
                'allocations' => ['Every subject must belong to the active institute.'],
            ]);
        }

        // Validate each teacher exists and has the correct role
        $teacherIds = array_column($validated['allocations'], 'teacher_id');

        foreach ($teacherIds as $teacherId) {
            if (! $this->isInstituteTeacher($teacherId, $instituteId)) {
                return ResponseService::error('Validation failed', 422, [
                    'allocations' => ['Every teacher must be an active Teacher in the active institute.'],
                ]);
            }
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

    private function belongsToActiveInstitute(Request $request, SubjectAllocation $subjectAllocation): bool
    {
        $instituteId = $this->activeInstituteId($request);

        return $instituteId !== null
            && $subjectAllocation->academicClass()->where('institute_id', $instituteId)->exists();
    }

    private function isInstituteTeacher(int $teacherId, int $instituteId): bool
    {
        return User::query()
            ->whereKey($teacherId)
            ->whereHas('instituteUsers', fn ($query) => $query
                ->where('institute_id', $instituteId)
                ->where('is_active', true))
            ->whereHas('roles', fn ($query) => $query
                ->where('roles.institute_id', $instituteId)
                ->where('roles.name', 'Teacher'))
            ->exists();
    }
}