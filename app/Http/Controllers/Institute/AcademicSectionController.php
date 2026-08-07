<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institute\StoreAcademicSectionRequest;
use App\Http\Requests\Institute\UpdateAcademicSectionRequest;
use App\Http\Resources\Institute\AcademicClassResource;
use App\Http\Resources\Institute\AcademicSectionResource;
use App\Models\AcademicClass;
use App\Models\AcademicSection;
use App\Models\InstituteUser;
use App\Services\ResponseService;
use Illuminate\Http\Request;
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

        $section = AcademicSection::create([
            ...$validated,
            'code' => $this->generateCode($academicClass->id, $validated['name']),
        ]);

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

        $academicClass = $academicSection->academicClass()
            ->where('institute_id', $this->activeInstituteId($request))
            ->first();

        if ($academicClass === null) {
            return ResponseService::notFound('Class not found');
        }

        return ResponseService::success(
            new AcademicClassResource($academicClass),
            'Class retrieved successfully'
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
