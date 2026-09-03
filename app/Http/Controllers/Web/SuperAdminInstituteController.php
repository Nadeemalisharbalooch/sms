<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Institute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                ->get(['public_id', 'name', 'email', 'phone', 'address', 'attendance_mode']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Institute::create($this->validated($request));

        return to_route('institutes.index')->with('success', 'Institute created successfully.');
    }

    public function update(Request $request, Institute $institute): RedirectResponse
    {
        $institute->update($this->validated($request));

        return to_route('institutes.index')->with('success', 'Institute updated successfully.');
    }

    public function destroy(Institute $institute): RedirectResponse
    {
        $institute->delete();

        return to_route('institutes.index')->with('success', 'Institute deleted successfully.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'attendance_mode' => ['required', 'in:class,subject'],
        ]);
    }
}
