<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institute\ActivateAcademicSessionRequest;
use App\Http\Requests\Institute\StoreAcademicSessionRequest;
use App\Http\Requests\Institute\UpdateAcademicSessionRequest;
use App\Http\Resources\Institute\AcademicSessionResource;
use App\Models\AcademicSession;
use App\Models\InstituteUser;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademicSessionController extends Controller
{
    public function index(Request $request)
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $sessions = AcademicSession::query()
            ->where('institute_id', $instituteId)
            ->latest('start_date')
            ->paginate();

        return ResponseService::success(
            AcademicSessionResource::collection($sessions),
            'Academic sessions retrieved successfully'
        );
    }

    public function store(StoreAcademicSessionRequest $request)
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        if (AcademicSession::query()
            ->where('institute_id', $instituteId)
            ->where('name', $request->validated('name'))
            ->exists()) {
            return ResponseService::error(
                'Validation failed',
                422,
                ['name' => ['The academic session name has already been taken.']]
            );
        }

        $validated = $request->validated();

        $session = DB::transaction(function () use ($validated, $instituteId) {
            $session = AcademicSession::create([
                ...$validated,
                'institute_id' => $instituteId,
            ]);

            if ($session->is_active) {
                $this->deactivateOtherSessions($session);
            }

            return $session;
        });

        return ResponseService::success(
            new AcademicSessionResource($session),
            'Academic session created successfully',
            201
        );
    }

    public function show(Request $request, AcademicSession $academicSession)
    {
        if (! $this->belongsToActiveInstitute($request, $academicSession)) {
            return ResponseService::notFound('Academic session not found');
        }

        return ResponseService::success(
            new AcademicSessionResource($academicSession),
            'Academic session retrieved successfully'
        );
    }

    public function update(UpdateAcademicSessionRequest $request, AcademicSession $academicSession)
    {
        if (! $this->belongsToActiveInstitute($request, $academicSession)) {
            return ResponseService::notFound('Academic session not found');
        }

        $validated = $request->validated();

        DB::transaction(function () use ($academicSession, $validated) {
            $academicSession->update($validated);

            if ($academicSession->is_active) {
                $this->deactivateOtherSessions($academicSession);
            }
        });

        return ResponseService::success(
            new AcademicSessionResource($academicSession->fresh()),
            'Academic session updated successfully'
        );
    }

    public function destroy(Request $request, AcademicSession $academicSession)
    {
        if (! $this->belongsToActiveInstitute($request, $academicSession)) {
            return ResponseService::notFound('Academic session not found');
        }

        $academicSession->delete();

        return ResponseService::success(
            null,
            'Academic session deleted successfully'
        );
    }

    /**
     * Update only the active state of an academic session.
     * A single institute can have only one active session at a time.
     */
    public function activate(ActivateAcademicSessionRequest $request, AcademicSession $academicSession)
    {
        if (! $this->belongsToActiveInstitute($request, $academicSession)) {
            return ResponseService::notFound('Academic session not found');
        }

        DB::transaction(function () use ($request, $academicSession) {
            $isActive = $request->boolean('is_active');

            if ($isActive) {
                $this->deactivateOtherSessions($academicSession);
            }

            $academicSession->update(['is_active' => $isActive]);
        });

        return ResponseService::success(
            new AcademicSessionResource($academicSession->fresh()),
            'Academic session active status updated successfully'
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

    private function deactivateOtherSessions(AcademicSession $academicSession): void
    {
        AcademicSession::query()
            ->where('institute_id', $academicSession->institute_id)
            ->whereKeyNot($academicSession->id)
            ->update(['is_active' => false]);
    }

    private function belongsToActiveInstitute(Request $request, AcademicSession $academicSession): bool
    {
        return $academicSession->institute_id === $this->activeInstituteId($request);
    }
}
