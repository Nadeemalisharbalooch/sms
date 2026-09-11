<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Plans/Index', [
            'user' => $request->user()->only(['id', 'name', 'email']),
            'plans' => Plan::query()->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Plan::create($this->validated($request));

        return to_route('plans.index')->with('success', 'Plan created successfully.');
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $plan->update($this->validated($request, $plan));

        return to_route('plans.index')->with('success', 'Plan updated successfully.');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        if ($plan->subscriptions()->exists()) {
            return to_route('plans.index')->with('error', 'A plan with subscriptions cannot be deleted. Deactivate it instead.');
        }

        $plan->delete();

        return to_route('plans.index')->with('success', 'Plan deleted successfully.');
    }

    private function validated(Request $request, ?Plan $plan = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:plans,name'.($plan ? ','.$plan->id : '')],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0'],
            'billing_interval' => ['required', 'in:monthly,yearly'],
            'trial_days' => ['required', 'integer', 'min:0'],
            'student_limit' => ['nullable', 'integer', 'min:1'],
            'teacher_limit' => ['nullable', 'integer', 'min:1'],
            'class_limit' => ['nullable', 'integer', 'min:1'],
            'features' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
