<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institute\EditCurrentInstituteRequest;
use App\Http\Requests\Institute\StoreInstituteRequest;
use App\Http\Requests\Institute\UpdateInstituteRequest;
use App\Http\Resources\Institute\InstituteResource;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\User;
use App\Services\ResponseService;
use Illuminate\Http\Request;
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

        $institute = Institute::findOrFail($instituteId);

        return ResponseService::success(
            new InstituteResource($institute),
            'Current institute fetched successfully'
        );
    }

    /**
     * Display a listing of the resource.
     */
  public function index()
{
    $institutes = Institute::latest()->paginate();

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
            'user_id'      => $user->id,
            'is_owner'     => true,
            'is_active'    => true,
        ]);

        foreach (['Admin', 'Teacher', 'Student'] as $roleName) {
            Role::query()->create([
                'institute_id' => $institute->id,
                'name' => $roleName,
                'guard_name' => 'sanctum',
            ]);
        }

        return $institute;
    });

    return ResponseService::success(
        new InstituteResource($institute),
        'Institute created successfully'
    );
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
     * Display the specified resource.
     */
   public function show(Institute $institute)
{
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
     * Update the specified resource in storage.
     */
   public function update(UpdateInstituteRequest $request, Institute $institute)
{
    $data = $request->validated();
    $data = $this->handleFileUploads($data, $institute);

    $institute->update($data);

    return ResponseService::success(
        new InstituteResource($institute->fresh()),
        'Institute updated successfully'
    );
}

    /**
     * Edit the currently active institute for the authenticated user.
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
            if (isset($data[$field]) && $data[$field] instanceof \Illuminate\Http\UploadedFile) {
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
     * Remove the specified resource from storage.
     */
   public function destroy(Institute $institute)
{
    $institute->delete();

    return ResponseService::success(
        null,
        'Institute deleted successfully'
    );
}
}
