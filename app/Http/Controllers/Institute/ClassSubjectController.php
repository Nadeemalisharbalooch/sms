<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institute\SyncClassSubjectsRequest;
use App\Http\Resources\Institute\ClassSubjectResource;
use App\Models\AcademicClass;
use App\Models\ClassSubject;
use App\Models\InstituteUser;
use App\Models\Subject;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class ClassSubjectController extends Controller
{
    public function index(Request $request, string $academicClass)
    {
        $academicClass = $this->activeClass($request, $academicClass);

        if ($academicClass === null) {
            return ResponseService::notFound('Class not found');
        }

        $classSubjects = ClassSubject::query()
            ->with('subject')
            ->where('class_id', $academicClass->id)
            ->orderBy('id')
            ->get()
            ->unique('subject_id')
            ->values();

        return ResponseService::success(
            ClassSubjectResource::collection($classSubjects),
            'Class subjects retrieved successfully'
        );
    }

    public function destroy(Request $request, string $academicClass, string $subject)
    {
        $academicClass = $this->activeClass($request, $academicClass);

        if ($academicClass === null) {
            return ResponseService::notFound('Class not found');
        }

        $deleted = ClassSubject::query()
            ->where('class_id', $academicClass->id)
            ->where('subject_id', $subject)
            ->delete();

        if ($deleted === 0) {
            return ResponseService::notFound('Subject not found in this class');
        }

        return ResponseService::success(null, 'Subject removed from class successfully');
    }

    public function sync(SyncClassSubjectsRequest $request, string $academicClass)
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $academicClass = $this->activeClass($request, $academicClass);

        if ($academicClass === null) {
            return ResponseService::notFound('Class not found');
        }

        $subjectIds = $request->validated('subject_ids');
        $subjectsCount = Subject::query()
            ->where('institute_id', $instituteId)
            ->whereIn('id', $subjectIds)
            ->count();

        if ($subjectsCount !== count($subjectIds)) {
            return ResponseService::error('Validation failed', 422, [
                'subject_ids' => ['Every subject must belong to the active institute.'],
            ]);
        }

        $requestedSectionId = $request->validated('section_id');

        if ($requestedSectionId !== null) {
            // A specific section was requested: verify it belongs to this class,
            // then sync subjects for that section only.
            $sectionBelongsToClass = $academicClass->sections()
                ->whereKey($requestedSectionId)
                ->exists();

            if (! $sectionBelongsToClass) {
                return ResponseService::error('Validation failed', 422, [
                    'section_id' => ['The selected section does not belong to this class.'],
                ]);
            }

            $this->syncForTarget($academicClass->id, $requestedSectionId, $subjectIds);
        } else {
            $sections = $academicClass->sections()->pluck('id');

            if ($sections->isNotEmpty()) {
                // No section requested and class HAS sections: apply subjects to every section
                foreach ($sections as $sectionId) {
                    $this->syncForTarget($academicClass->id, $sectionId, $subjectIds);
                }

                // Remove any class-level (section_id = null) records for this class
                ClassSubject::query()
                    ->where('class_id', $academicClass->id)
                    ->whereNull('section_id')
                    ->delete();
            } else {
                // Class has NO sections: apply subjects directly to the class (section_id = null)
                $this->syncForTarget($academicClass->id, null, $subjectIds);
            }
        }

        $classSubjects = ClassSubject::query()
            ->with('subject')
            ->where('class_id', $academicClass->id)
            ->orderBy('id')
            ->get()
            ->unique('subject_id')
            ->values();

        return ResponseService::success(
            ClassSubjectResource::collection($classSubjects),
            'Class subjects assigned successfully'
        );
    }

    /**
     * Sync subject IDs for a specific target (section or class-level).
     */
    private function syncForTarget(int $classId, ?int $sectionId, array $subjectIds): void
    {
        $targetQuery = ClassSubject::query()
            ->where('class_id', $classId)
            ->when($sectionId === null, fn ($query) => $query->whereNull('section_id'), fn ($query) => $query->where('section_id', $sectionId));

        // Empty array = remove all subjects for this target
        if (empty($subjectIds)) {
            $targetQuery->delete();

            return;
        }

        // Delete records for this target that are no longer in the array
        $targetQuery->whereNotIn('subject_id', $subjectIds)->delete();

        // Get existing subject IDs for this target
        $existingSubjectIds = ClassSubject::query()
            ->where('class_id', $classId)
            ->when($sectionId === null, fn ($query) => $query->whereNull('section_id'), fn ($query) => $query->where('section_id', $sectionId))
            ->pluck('subject_id')
            ->all();

        // Insert new subject IDs that don't exist yet
        $newSubjectIds = array_diff($subjectIds, $existingSubjectIds);

        foreach ($newSubjectIds as $subjectId) {
            ClassSubject::create([
                'class_id' => $classId,
                'section_id' => $sectionId,
                'subject_id' => $subjectId,
            ]);
        }
    }

    private function activeInstituteId(Request $request): ?int
    {
        $instituteId = InstituteUser::query()
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->value('institute_id');

        return $instituteId === null ? null : (int) $instituteId;
    }

    private function activeClass(Request $request, string $classId): ?AcademicClass
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return null;
        }

        return AcademicClass::query()
            ->whereKey($classId)
            ->where('institute_id', $instituteId)
            ->first();
    }
}