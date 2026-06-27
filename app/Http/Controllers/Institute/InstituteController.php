<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
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

class InstituteController extends Controller
{
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

        $institute = Institute::create($request->validated());

        $user->update([
            'is_institute' => true,
        ]);

        InstituteUser::create([
            'institute_id' => $institute->id,
            'user_id'      => $user->id,
            'is_owner'     => true,
            'is_active'    => true,
        ]);

        return $institute;
    });

    return ResponseService::success(
        new InstituteResource($institute),
        'Institute created successfully'
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
    $institute->update($request->validated());

    return ResponseService::success(
        new InstituteResource($institute->fresh()),
        'Institute updated successfully'
    );
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
