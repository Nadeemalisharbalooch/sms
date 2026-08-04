<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institute\StoreSectionTeacherRequest;
use App\Http\Requests\Institute\UpdateSectionTeacherRequest;
use App\Http\Resources\Institute\SectionTeacherResource;
use App\Models\AcademicSection;
use App\Models\InstituteUser;
use App\Models\SectionTeacher;
use App\Models\User;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class SectionTeacherController extends Controller
{
    public function index(Request $request)
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $assignments = SectionTeacher::query()
            ->with(['section', 'teacher'])
            ->whereHas('section', fn ($query) => $query
                ->whereHas('academicClass', fn ($classQuery) => $classQuery->where('institute_id', $instituteId)))
            ->when($request->integer('section_id'), fn ($query, int $sectionId) => $query->where('section_id', $sectionId))
            ->when($request->integer('teacher_id'), fn ($query, int $teacherId) => $query->where('teacher_id', $teacherId))
            ->latest()
            ->paginate();

        return ResponseService::success(
            SectionTeacherResource::collection($assignments),
            'Section teacher assignments retrieved successfully'
        );
    }

    public function store(StoreSectionTeacherRequest $request)
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

        if (SectionTeacher::query()->where($validated)->exists()) {
            return ResponseService::error('Validation failed', 422, [
                'teacher_id' => ['This teacher is already assigned to this section.'],
            ]);
        }

        $assignment = SectionTeacher::create($validated)->load(['section', 'teacher']);

        return ResponseService::success(
            new SectionTeacherResource($assignment),
            'Teacher assigned to section successfully',
            201
        );
    }

    public function show(Request $request, SectionTeacher $sectionTeacher)
    {
        if (! $this->belongsToActiveInstitute($request, $sectionTeacher)) {
            return ResponseService::notFound('Section teacher assignment not found');
        }

        return ResponseService::success(
            new SectionTeacherResource($sectionTeacher->load(['section', 'teacher'])),
            'Section teacher assignment retrieved successfully'
        );
    }

    public function update(UpdateSectionTeacherRequest $request, SectionTeacher $sectionTeacher)
    {
        if (! $this->belongsToActiveInstitute($request, $sectionTeacher)) {
            return ResponseService::notFound('Section teacher assignment not found');
        }

        $instituteId = $this->activeInstituteId($request);
        $validated = $request->validated();
        $error = $this->assignmentError($validated, $instituteId);

        if ($error !== null) {
            return $error;
        }

        if (SectionTeacher::query()->where($validated)->where('id', '!=', $sectionTeacher->id)->exists()) {
            return ResponseService::error('Validation failed', 422, [
                'teacher_id' => ['This teacher is already assigned to this section.'],
            ]);
        }

        $sectionTeacher->update($validated);

        return ResponseService::success(
            new SectionTeacherResource($sectionTeacher->fresh()->load(['section', 'teacher'])),
            'Section teacher assignment updated successfully'
        );
    }

    public function destroy(Request $request, SectionTeacher $sectionTeacher)
    {
        if (! $this->belongsToActiveInstitute($request, $sectionTeacher)) {
            return ResponseService::notFound('Section teacher assignment not found');
        }

        $sectionTeacher->delete();

        return ResponseService::success(null, 'Teacher removed from section successfully');
    }

    private function assignmentError(array $validated, int $instituteId): ?\Illuminate\Http\JsonResponse
    {
        if (! AcademicSection::query()
            ->whereKey($validated['section_id'])
            ->whereHas('academicClass', fn ($query) => $query->where('institute_id', $instituteId))
            ->exists()) {
            return ResponseService::error('Validation failed', 422, [
                'section_id' => ['The selected section must belong to the active institute.'],
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

    private function belongsToActiveInstitute(Request $request, SectionTeacher $sectionTeacher): bool
    {
        $instituteId = $this->activeInstituteId($request);

        return $instituteId !== null
            && $sectionTeacher->section()->whereHas(
                'academicClass',
                fn ($query) => $query->where('institute_id', $instituteId)
            )->exists();
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
