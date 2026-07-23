<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institute\StoreAcademicClassRequest;
use App\Http\Requests\Institute\UpdateAcademicClassRequest;
use App\Http\Resources\Institute\AcademicClassResource;
use App\Models\AcademicClass;
use App\Models\InstituteUser;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AcademicClassController extends Controller
{
    public function index(Request $request)
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $classes = AcademicClass::query()
            ->where('institute_id', $instituteId)
            ->orderBy('display_order')
            ->orderBy('name')
            ->paginate();

        return ResponseService::success(
            AcademicClassResource::collection($classes),
            'Classes retrieved successfully'
        );
    }

    public function store(StoreAcademicClassRequest $request)
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $validated = $request->validated();

        if ($this->classExists($instituteId, $validated['name'])) {
            return ResponseService::error(
                'Validation failed',
                422,
                ['name' => ['The class name has already been taken.']]
            );
        }

        $class = AcademicClass::create([
            ...$validated,
            'institute_id' => $instituteId,
            'code' => $this->generateCode($instituteId, $validated['name']),
        ]);

        return ResponseService::success(
            new AcademicClassResource($class),
            'Class created successfully',
            201
        );
    }

    public function show(Request $request, AcademicClass $academicClass)
    {
        if (! $this->belongsToActiveInstitute($request, $academicClass)) {
            return ResponseService::notFound('Class not found');
        }

        return ResponseService::success(
            new AcademicClassResource($academicClass),
            'Class retrieved successfully'
        );
    }

    public function update(UpdateAcademicClassRequest $request, AcademicClass $academicClass)
    {
        if (! $this->belongsToActiveInstitute($request, $academicClass)) {
            return ResponseService::notFound('Class not found');
        }

        $validated = $request->validated();

        $academicClass->update([
            ...$validated,
            'code' => $this->generateCode($academicClass->institute_id, $validated['name'], $academicClass),
        ]);

        return ResponseService::success(
            new AcademicClassResource($academicClass->fresh()),
            'Class updated successfully'
        );
    }

    public function destroy(Request $request, AcademicClass $academicClass)
    {
        if (! $this->belongsToActiveInstitute($request, $academicClass)) {
            return ResponseService::notFound('Class not found');
        }

        $academicClass->delete();

        return ResponseService::success(null, 'Class deleted successfully');
    }

    private function activeInstituteId(Request $request): ?int
    {
        return InstituteUser::query()
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->value('institute_id');
    }

    private function belongsToActiveInstitute(Request $request, AcademicClass $academicClass): bool
    {
        return $academicClass->institute_id === $this->activeInstituteId($request);
    }

    private function classExists(int $instituteId, string $name): bool
    {
        return AcademicClass::query()
            ->where('institute_id', $instituteId)
            ->where('name', $name)
            ->exists();
    }

    private function generateCode(int $instituteId, string $name, ?AcademicClass $except = null): string
    {
        $baseCode = Str::upper(Str::slug($name)) ?: 'CLASS';
        $baseCode = Str::substr($baseCode, 0, 44);
        $code = $baseCode;
        $suffix = 2;

        while (AcademicClass::query()
            ->where('institute_id', $instituteId)
            ->where('code', $code)
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->exists()) {
            $code = Str::substr($baseCode, 0, 50 - strlen((string) $suffix) - 1).'-'.$suffix;
            $suffix++;
        }

        return $code;
    }
}
