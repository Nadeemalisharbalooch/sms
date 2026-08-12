<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institute\StoreAcademicSectionRequest;
use App\Http\Requests\Institute\UpdateAcademicSectionRequest;
use App\Http\Resources\Institute\AcademicSectionResource;
use App\Http\Resources\Institute\SubjectResource;
use App\Models\AcademicClass;
use App\Models\AcademicSection;
use App\Models\ClassSubject;
use App\Models\InstituteUser;
use App\Models\SubjectAllocation;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AcademicSectionController extends Controller
{
    public function index(Request $request)
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $sections = AcademicSection::query()
            ->whereHas('academicClass', fn ($query) => $query->where('institute_id', $instituteId))
            ->when($request->integer('class_id'), fn ($query, int $classId) => $query->where('class_id', $classId))
            ->orderBy('display_order')
            ->orderBy('name')
            ->paginate();

        return ResponseService::success(
            AcademicSectionResource::collection($sections),
            'Sections retrieved successfully'
        );
    }

    public function store(StoreAcademicSectionRequest $request)
    {
        $academicClass = $this->activeClass($request, $request->integer('class_id'));

        if ($academicClass === null) {
            return ResponseService::notFound('Class not found');
        }

        $validated = $request->validated();

        if ($this->sectionNameExists($academicClass->id, $validated['name'])) {
            return ResponseService::error(
                'Validation failed',
                422,
                ['name' => ['The section name has already been taken for this class.']]
            );
        }

        $section = DB::transaction(function () use ($academicClass, $validated) {
            $section = AcademicSection::create([
                ...$validated,
                'code' => $this->generateCode($academicClass->id, $validated['name']),
            ]);

            $this->copyClassSubjectsToSection($academicClass->id, $section->id);

            return $section;
        });

        return ResponseService::success(
            new AcademicSectionResource($section),
            'Section created successfully',
            201
        );
    }

    public function show(Request $request, AcademicSection $academicSection)
    {
        if (! $this->belongsToActiveInstitute($request, $academicSection)) {
            return ResponseService::notFound('Section not found');
        }

        return ResponseService::success(
            new AcademicSectionResource($academicSection),
            'Section retrieved successfully'
        );
    }

    public function classes(Request $request, AcademicSection $academicSection)
    {
        if (! $this->belongsToActiveInstitute($request, $academicSection)) {
            return ResponseService::notFound('Section not found');
        }

        $subjects = SubjectAllocation::query()
            ->with('subject')
            ->where('class_id', $academicSection->class_id)
            ->where('section_id', $academicSection->id)
            ->when($request->integer('session_id'), fn ($query, int $sessionId) => $query->where('session_id', $sessionId))
            ->get()
            ->unique('subject_id')
            ->map(fn (SubjectAllocation $allocation) => $allocation->subject)
            ->values();

        return ResponseService::success(
            SubjectResource::collection($subjects),
            'Subjects retrieved successfully'
        );
    }

    public function update(UpdateAcademicSectionRequest $request, AcademicSection $academicSection)
    {
        if (! $this->belongsToActiveInstitute($request, $academicSection)) {
            return ResponseService::notFound('Section not found');
        }

        $validated = $request->validated();
        $academicClass = $this->activeClass($request, $validated['class_id']);

        if ($academicClass === null) {
            return ResponseService::notFound('Class not found');
        }

        $academicSection->update([
            ...$validated,
            'code' => $this->generateCode($academicClass->id, $validated['name'], $academicSection),
        ]);

        return ResponseService::success(
            new AcademicSectionResource($academicSection->fresh()),
            'Section updated successfully'
        );
    }

    public function destroy(Request $request, AcademicSection $academicSection)
    {
        if (! $this->belongsToActiveInstitute($request, $academicSection)) {
            return ResponseService::notFound('Section not found');
        }

        $academicSection->delete();

        return ResponseService::success(null, 'Section deleted successfully');
    }

    private function activeInstituteId(Request $request): ?int
    {
        return InstituteUser::query()
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->value('institute_id');
    }

    private function activeClass(Request $request, int $classId): ?AcademicClass
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return null;
        }

        return AcademicClass::query()
            ->where('id', $classId)
            ->where('institute_id', $instituteId)
            ->first();
    }

    private function belongsToActiveInstitute(Request $request, AcademicSection $academicSection): bool
    {
        return $academicSection->academicClass()
            ->where('institute_id', $this->activeInstituteId($request))
            ->exists();
    }

    private function sectionNameExists(int $classId, string $name): bool
    {
        return AcademicSection::query()
            ->where('class_id', $classId)
            ->where('name', $name)
            ->exists();
    }

    /**
     * Give a new section the subjects already assigned to its class.
     *
     * Classes without sections keep their subjects with a null section_id.
     * When the first section is created, those records are moved to it. For
     * subsequent sections, the existing class subject set is copied instead.
     */
    private function copyClassSubjectsToSection(int $classId, int $sectionId): void
    {
        $classLevelSubjects = ClassSubject::query()
            ->where('class_id', $classId)
            ->whereNull('section_id')
            ->lockForUpdate()
            ->pluck('subject_id');

        $subjectIds = $classLevelSubjects->isNotEmpty()
            ? $classLevelSubjects
            : ClassSubject::query()
                ->where('class_id', $classId)
                ->pluck('subject_id')
                ->unique()
                ->values();

        if ($subjectIds->isEmpty()) {
            return;
        }

        ClassSubject::query()->upsert(
            $subjectIds->map(fn (int $subjectId) => [
                'class_id' => $classId,
                'section_id' => $sectionId,
                'subject_id' => $subjectId,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all(),
            ['class_id', 'section_id', 'subject_id'],
            ['updated_at']
        );

        // After the first section is created, assignments live on sections.
        if ($classLevelSubjects->isNotEmpty()) {
            ClassSubject::query()
                ->where('class_id', $classId)
                ->whereNull('section_id')
                ->delete();
        }
    }

    private function generateCode(int $classId, string $name, ?AcademicSection $except = null): string
    {
        $baseCode = Str::upper(Str::slug($name)) ?: 'SECTION';
        $baseCode = Str::substr($baseCode, 0, 44);
        $code = $baseCode;
        $suffix = 2;

        while (AcademicSection::query()
            ->where('class_id', $classId)
            ->where('code', $code)
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->exists()) {
            $code = Str::substr($baseCode, 0, 50 - strlen((string) $suffix) - 1).'-'.$suffix;
            $suffix++;
        }

        return $code;
    }
}
