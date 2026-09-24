<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institute\EditCurrentInstituteRequest;
use App\Http\Requests\Institute\StoreInstituteRequest;
use App\Http\Requests\Institute\UpdateInstituteRequest;
use App\Http\Resources\Institute\InstituteResource;
use App\Models\Institute;
use App\Models\InstituteSubscription;
use App\Models\InstituteUser;
use App\Models\Plan;
use App\Models\User;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class InstituteController extends Controller
{
    /**
     * Return the authenticated user's currently active institute.
     */
    public function currentInstitute(Request $request)
    {
        $instituteId = InstituteUser::query()
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->value('institute_id');

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $institute = Institute::query()
            ->whereKey($instituteId)
            ->with('subscription')
            ->firstOrFail();

        return ResponseService::success(
            new InstituteResource($institute),
            'Current institute fetched successfully'
        );
    }

    /**
     * Display a listing of the authenticated user's institutes only.
     */
    public function index(Request $request)
    {
        $institutes = Institute::query()
            ->whereHas('instituteUsers', fn ($query) => $query->where('user_id', $request->user()->id))
            ->latest()
            ->paginate();

        return ResponseService::success(
            InstituteResource::collection($institutes),
            'Institutes fetched successfully'
        );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInstituteRequest $request)
    {
        $user = Auth::user();

        $institute = DB::transaction(function () use ($request, $user) {

            $data = $request->validated();
            $data = $this->handleFileUploads($data);

            $institute = Institute::create($data);

            $user->update([
                'is_institute' => true,
            ]);

            InstituteUser::query()
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            InstituteUser::create([
                'institute_id' => $institute->id,
                'user_id' => $user->id,
                'is_owner' => true,
                'is_active' => true,
            ]);

            foreach (['Admin', 'Teacher', 'Student'] as $roleName) {
                Role::query()->create([
                    'institute_id' => $institute->id,
                    'name' => $roleName,
                    'guard_name' => 'sanctum',
                ]);
            }

            $this->assignTrialSubscription($institute);

            return $institute;
        });

        return ResponseService::success(
            new InstituteResource($institute),
            'Institute created successfully'
        );
    }

    /**
     * Auto-assign the trial subscription to a newly created institute so the
     * owner can start using the app immediately.
     */
    private function assignTrialSubscription(Institute $institute): void
    {
        $plan = Plan::query()
            ->where('is_active', true)
            ->whereRaw('LOWER(TRIM(name)) = ?', ['trial'])
            ->first();

        if (! $plan || InstituteSubscription::query()->where('institute_id', $institute->id)->exists()) {
            return;
        }

        $startsAt = now();

        InstituteSubscription::create([
            'institute_id' => $institute->id,
            'plan_id' => $plan->id,
            'status' => 'trialing',
            'blocked' => false,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addDays($plan->trial_days),
            'approved_at' => null,
        ]);
    }

    /**
     * Set one of the authenticated user's institutes as active.
     */
    public function activate(Request $request, Institute $institute)
    {
        $userId = $request->user()->id;

        $membership = InstituteUser::query()
            ->where('user_id', $userId)
            ->where('institute_id', $institute->id)
            ->first();

        if (! $membership) {
            return ResponseService::notFound('Institute not found');
        }

        DB::transaction(function () use ($userId, $membership) {
            InstituteUser::query()
                ->where('user_id', $userId)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $membership->update(['is_active' => true]);
        });

        return ResponseService::success(
            new InstituteResource($institute),
            'Active institute changed successfully'
        );
    }

    /**
     * Display the specified resource (members only).
     */
    public function show(Request $request, Institute $institute)
    {
        if (! $this->isInstituteMember($request, $institute)) {
            return ResponseService::notFound('Institute not found');
        }

        return ResponseService::success(
            new InstituteResource($institute),
            'Institute fetched successfully'
        );
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource (owner only).
     */
    public function update(UpdateInstituteRequest $request, Institute $institute)
    {
        if (! $this->isInstituteOwner($request, $institute)) {
            return ResponseService::error('Only the institute owner can update the institute', 403);
        }

        $data = $request->validated();
        $data = $this->handleFileUploads($data, $institute);

        $institute->update($data);

        return ResponseService::success(
            new InstituteResource($institute->fresh()),
            'Institute updated successfully'
        );
    }

    /**
     * Edit the currently active institute for the authenticated user (owner only).
     */
    public function editCurrentInstitute(EditCurrentInstituteRequest $request)
    {
        $user = $request->user();

        $instituteId = InstituteUser::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->value('institute_id');

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $institute = Institute::findOrFail($instituteId);

        if (! $this->isInstituteOwner($request, $institute)) {
            return ResponseService::error('Only the institute owner can update the institute', 403);
        }

        $data = $request->validated();
        $data = $this->handleFileUploads($data, $institute);

        $institute->update($data);

        return ResponseService::success(
            new InstituteResource($institute->fresh()),
            'Institute updated successfully'
        );
    }

    /**
     * Handle file uploads for logo and favicon.
     */
    protected function handleFileUploads(array $data, ?Institute $institute = null): array
    {
        foreach (['logo', 'favicon'] as $field) {
            if (isset($data[$field]) && $data[$field] instanceof UploadedFile) {
                // Delete old file if updating
                if ($institute && $institute->{$field}) {
                    Storage::disk('public')->delete($institute->{$field});
                }

                $data[$field] = $data[$field]->store('institutes', 'public');
            }
        }

        return $data;
    }

    /**
     * Remove the specified resource from storage (owner only).
     */
    public function destroy(Request $request, Institute $institute)
    {
        if (! $this->isInstituteOwner($request, $institute)) {
            return ResponseService::error('Only the institute owner can delete the institute', 403);
        }

        $institute->delete();

        return ResponseService::success(
            null,
            'Institute deleted successfully'
        );
    }

    private function isInstituteMember(Request $request, Institute $institute): bool
    {
        return InstituteUser::query()
            ->where('user_id', $request->user()->id)
            ->where('institute_id', $institute->id)
            ->exists();
    }

    private function isInstituteOwner(Request $request, Institute $institute): bool
    {
        return InstituteUser::query()
            ->where('user_id', $request->user()->id)
            ->where('institute_id', $institute->id)
            ->where('is_owner', true)
            ->exists();
    }
}
