<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institute\StoreSubjectRequest;
use App\Http\Requests\Institute\UpdateSubjectRequest;
use App\Http\Resources\Institute\SubjectResource;
use App\Models\InstituteUser;
use App\Models\Subject;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function index(Request $request)
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $subjects = Subject::query()
            ->where('institute_id', $instituteId)
            ->orderBy('name')
            ->get();

        return ResponseService::success(
            SubjectResource::collection($subjects),
            'Subjects retrieved successfully'
        );
    }

    public function store(StoreSubjectRequest $request)
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $validated = $request->validated();

        if ($this->subjectExists($instituteId, $validated['name'], $validated['code'])) {
            return ResponseService::error(
                'Validation failed',
                422,
                $this->duplicateErrors($instituteId, $validated['name'], $validated['code'])
            );
        }

        $subject = Subject::create([
            ...$validated,
            'institute_id' => $instituteId,
        ]);

        return ResponseService::success(
            new SubjectResource($subject),
            'Subject created successfully',
            201
        );
    }

    public function show(Request $request, Subject $subject)
    {
        if (! $this->belongsToActiveInstitute($request, $subject)) {
            return ResponseService::notFound('Subject not found');
        }

        return ResponseService::success(
            new SubjectResource($subject),
            'Subject retrieved successfully'
        );
    }

    public function update(UpdateSubjectRequest $request, Subject $subject)
    {
        if (! $this->belongsToActiveInstitute($request, $subject)) {
            return ResponseService::notFound('Subject not found');
        }

        $subject->update($request->validated());

        return ResponseService::success(
            new SubjectResource($subject->fresh()),
            'Subject updated successfully'
        );
    }

    public function destroy(Request $request, Subject $subject)
    {
        if (! $this->belongsToActiveInstitute($request, $subject)) {
            return ResponseService::notFound('Subject not found');
        }

        $subject->delete();

        return ResponseService::success(null, 'Subject deleted successfully');
    }

    private function activeInstituteId(Request $request): ?int
    {
        $instituteId = InstituteUser::query()
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->value('institute_id');

        return $instituteId === null ? null : (int) $instituteId;
    }

    private function belongsToActiveInstitute(Request $request, Subject $subject): bool
    {
        return $subject->institute_id === $this->activeInstituteId($request);
    }

    private function subjectExists(int $instituteId, string $name, string $code): bool
    {
        return Subject::query()
            ->where('institute_id', $instituteId)
            ->where(fn ($query) => $query->where('name', $name)->orWhere('code', $code))
            ->exists();
    }

    private function duplicateErrors(int $instituteId, string $name, string $code): array
    {
        $query = Subject::query()->where('institute_id', $instituteId);
        $errors = [];

        if ((clone $query)->where('name', $name)->exists()) {
            $errors['name'] = ['The subject name has already been taken.'];
        }

        if ($query->where('code', $code)->exists()) {
            $errors['code'] = ['The subject code has already been taken.'];
        }

        return $errors;
    }
}
