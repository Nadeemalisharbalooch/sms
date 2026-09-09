<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminInstituteController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Institutes/Index', [
            'user' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ],
            'institutes' => Institute::query()
                ->latest()
                ->get(['public_id', 'name', 'email', 'phone', 'address', 'logo', 'favicon', 'attendance_mode', 'is_active']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Handle logo upload
        if ($request->hasFile('logo')) {
            $request->merge(['logo' => $request->file('logo')->store('logos', 'public')]);
        }

        // Handle favicon upload
        if ($request->hasFile('favicon')) {
            $request->merge(['favicon' => $request->file('favicon')->store('favicons', 'public')]);
        }

        $validated = $this->validated($request);

        // Create institute
        $institute = Institute::create($validated);

        // Create associated user for this institute
        $password = $request->input('user_password', 'password');
        $user = User::create([
            'name' => $request->input('user_name', $institute->name . ' Admin'),
            'email' => $request->input('user_email', $institute->email),
            'password' => Hash::make($password),
            'phone' => $request->input('user_phone', $institute->phone),
            'is_admin' => false,
            'is_institute' => true,
            'is_active' => true,
        ]);

        // Link user to institute
        InstituteUser::create([
            'institute_id' => $institute->id,
            'user_id' => $user->id,
            'is_owner' => true,
            'is_active' => true,
        ]);

        return to_route('institute.index')->with('success', 'Institute and user created successfully.');
    }

    public function destroy(Institute $institute): RedirectResponse
    {
        $institute->delete();

        return to_route('institute.index')->with('success', 'Institute deleted successfully.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'logo' => ['nullable', 'file', 'image', 'max:2048'],
            'favicon' => ['nullable', 'file', 'image', 'max:2048'],
            'attendance_mode' => ['required', 'in:class,subject'],
        ]);
    }
}