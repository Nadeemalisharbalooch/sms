<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Institute;
use App\Models\InstituteSubscription;
use App\Models\InstituteUser;
use App\Models\Plan;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
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
                ->with(['owner:users.id,users.name,users.email,users.phone', 'subscription.plan', 'subscription.invoice'])
                ->latest()
                ->get(['id', 'public_id', 'name', 'email', 'phone', 'address', 'logo', 'favicon', 'attendance_mode', 'is_active']),
            'plans' => Plan::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'price', 'billing_interval', 'trial_days']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $this->storeUploadedImages($request, $validated);

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

    public function update(Request $request, Institute $institute): RedirectResponse
    {
        $validated = $this->validated($request);
        $owner = $institute->owner;
        $userValidated = $this->validatedUser($request, $owner?->id);
        $this->storeUploadedImages($request, $validated);

        // Do not overwrite existing image paths when no replacement was uploaded.
        if (! $request->hasFile('logo')) {
            unset($validated['logo']);
        }

        if (! $request->hasFile('favicon')) {
            unset($validated['favicon']);
        }

        DB::transaction(function () use ($institute, $validated, $owner, $userValidated): void {
            $institute->update($validated);

            if ($owner) {
                $ownerData = [
                    'name' => $userValidated['user_name'],
                    'email' => $userValidated['user_email'],
                    'phone' => $userValidated['user_phone'],
                ];

                if (! empty($userValidated['user_password'])) {
                    $ownerData['password'] = Hash::make($userValidated['user_password']);
                }

                $owner->update($ownerData);
            }

        });

        return to_route('institute.index')->with('success', 'Institute updated successfully.');
    }

    public function destroy(Institute $institute): RedirectResponse
    {
        $institute->delete();

        return to_route('institute.index')->with('success', 'Institute deleted successfully.');
    }

    public function verifyInvoice(Request $request, SubscriptionInvoice $invoice): RedirectResponse
    {
        if ($invoice->status !== 'verification_pending') {
            return back()->with('error', 'Only submitted manual payments can be verified.');
        }

        DB::transaction(function () use ($invoice, $request): void {
            $invoice->update([
                'status' => 'paid',
                'paid_at' => now(),
                'verified_by_user_id' => $request->user()->id,
            ]);

            $subscription = InstituteSubscription::query()
                ->where('institute_id', $invoice->institute_id)
                ->latest()
                ->lockForUpdate()
                ->first() ?? new InstituteSubscription(['institute_id' => $invoice->institute_id]);
            $now = now();
            $endsAt = $subscription->ends_at && $subscription->ends_at->isFuture()
                ? $subscription->ends_at->copy()
                : $now->copy();
            $subscription->fill([
                'plan_id' => $invoice->plan_id,
                'status' => 'active',
                'blocked' => false,
                'starts_at' => $subscription->starts_at ?? $now,
                'ends_at' => $invoice->billing_interval === 'yearly'
                    ? $endsAt->addYear()
                    : $endsAt->addMonth(),
                'approved_at' => $now,
            ])->save();
        });

        return back()->with('success', 'Payment verified and subscription activated successfully.');
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

    private function storeUploadedImages(Request $request, array &$validated): void
    {
        if ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('logos', 'public');
        }

        if ($request->hasFile('favicon')) {
            $validated['favicon'] = $request->file('favicon')->store('favicons', 'public');
        }
    }

    private function validatedUser(Request $request, ?int $ownerId): array
    {
        return $request->validate([
            'user_name' => ['required', 'string', 'max:255'],
            'user_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ownerId)],
            'user_phone' => ['nullable', 'string', 'max:50'],
            'user_password' => ['nullable', 'string', 'min:8'],
        ]);
    }

}
