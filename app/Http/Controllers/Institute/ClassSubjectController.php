<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institute\SyncClassSubjectsRequest;
use App\Http\Resources\Institute\ClassSubjectResource;
use App\Models\AcademicClass;
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

        return ResponseService::success(
            ClassSubjectResource::collection($academicClass->subjects()->orderBy('name')->get()),
            'Class subjects retrieved successfully'
        );
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

        $academicClass->subjects()->sync($subjectIds);

        return ResponseService::success(
            ClassSubjectResource::collection($academicClass->subjects()->orderBy('name')->get()),
            'Class subjects assigned successfully'
        );
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
