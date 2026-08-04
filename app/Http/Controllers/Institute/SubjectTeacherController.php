<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institute\StoreSubjectTeacherRequest;
use App\Http\Requests\Institute\UpdateSubjectTeacherRequest;
use App\Http\Resources\Institute\SubjectTeacherResource;
use App\Models\InstituteUser;
use App\Models\Subject;
use App\Models\SubjectTeacher;
use App\Models\User;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class SubjectTeacherController extends Controller
{
    public function index(Request $request)
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $assignments = SubjectTeacher::query()
            ->with(['subject', 'teacher'])
            ->whereHas('subject', fn ($query) => $query->where('institute_id', $instituteId))
            ->when($request->integer('subject_id'), fn ($query, int $subjectId) => $query->where('subject_id', $subjectId))
            ->when($request->integer('teacher_id'), fn ($query, int $teacherId) => $query->where('teacher_id', $teacherId))
            ->latest()
            ->paginate();

        return ResponseService::success(
            SubjectTeacherResource::collection($assignments),
            'Subject teacher assignments retrieved successfully'
        );
    }

    public function store(StoreSubjectTeacherRequest $request)
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $validated = $request->validated();
        $error = $this->assignmentError($validated, $instituteId);

        if ($error !== null) {
            return $error;
        }

        if (SubjectTeacher::query()->where($validated)->exists()) {
            return ResponseService::error('Validation failed', 422, [
                'teacher_id' => ['This teacher is already assigned to this subject.'],
            ]);
        }

        $assignment = SubjectTeacher::create($validated)->load(['subject', 'teacher']);

        return ResponseService::success(
            new SubjectTeacherResource($assignment),
            'Teacher assigned to subject successfully',
            201
        );
    }

    public function show(Request $request, SubjectTeacher $subjectTeacher)
    {
        if (! $this->belongsToActiveInstitute($request, $subjectTeacher)) {
            return ResponseService::notFound('Subject teacher assignment not found');
        }

        return ResponseService::success(
            new SubjectTeacherResource($subjectTeacher->load(['subject', 'teacher'])),
            'Subject teacher assignment retrieved successfully'
        );
    }

    public function update(UpdateSubjectTeacherRequest $request, SubjectTeacher $subjectTeacher)
    {
        if (! $this->belongsToActiveInstitute($request, $subjectTeacher)) {
            return ResponseService::notFound('Subject teacher assignment not found');
        }

        $instituteId = $this->activeInstituteId($request);
        $validated = $request->validated();
        $error = $this->assignmentError($validated, $instituteId);

        if ($error !== null) {
            return $error;
        }

        if (SubjectTeacher::query()->where($validated)->where('id', '!=', $subjectTeacher->id)->exists()) {
            return ResponseService::error('Validation failed', 422, [
                'teacher_id' => ['This teacher is already assigned to this subject.'],
            ]);
        }

        $subjectTeacher->update($validated);

        return ResponseService::success(
            new SubjectTeacherResource($subjectTeacher->fresh()->load(['subject', 'teacher'])),
            'Subject teacher assignment updated successfully'
        );
    }

    public function destroy(Request $request, SubjectTeacher $subjectTeacher)
    {
        if (! $this->belongsToActiveInstitute($request, $subjectTeacher)) {
            return ResponseService::notFound('Subject teacher assignment not found');
        }

        $subjectTeacher->delete();

        return ResponseService::success(null, 'Teacher removed from subject successfully');
    }

    private function assignmentError(array $validated, int $instituteId): ?\Illuminate\Http\JsonResponse
    {
        if (! Subject::query()->whereKey($validated['subject_id'])->where('institute_id', $instituteId)->exists()) {
            return ResponseService::error('Validation failed', 422, [
                'subject_id' => ['The selected subject must belong to the active institute.'],
            ]);
        }

        if (! $this->isInstituteTeacher($validated['teacher_id'], $instituteId)) {
            return ResponseService::error('Validation failed', 422, [
                'teacher_id' => ['The selected user must be an active Teacher in the active institute.'],
            ]);
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

    private function belongsToActiveInstitute(Request $request, SubjectTeacher $subjectTeacher): bool
    {
        $instituteId = $this->activeInstituteId($request);

        return $instituteId !== null
            && $subjectTeacher->subject()->where('institute_id', $instituteId)->exists();
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
