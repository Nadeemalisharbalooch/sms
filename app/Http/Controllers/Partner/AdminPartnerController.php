<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\PartnerProfile;
use App\Models\PartnerWithdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminPartnerController extends Controller
{
    public function index(Request $request)
    {
        $partners = PartnerProfile::with('user:id,name,email')->withCount(['commissions', 'withdrawals'])->latest()->get()->map(function ($partner) {
            return array_merge($partner->toArray(), [
                'pending_clearance' => (float) $partner->commissions()->where('status', 'pending')->where('clears_at', '>', now())->sum('amount'),
                'available_balance' => max(0, (float) $partner->commissions()->where(fn ($query) => $query->where('status', 'available')->orWhere(fn ($pending) => $pending->where('status', 'pending')->where('clears_at', '<=', now())))->sum('amount') - (float) $partner->withdrawals()->whereIn('status', ['pending', 'paid'])->sum('amount')),
                'clicks' => \App\Models\PartnerClick::where('partner_profile_id', $partner->id)->count(),
                'signups' => \App\Models\Institute::where('referral_id', $partner->referral_code)->count(),
            ]);
        });
        $withdrawals = PartnerWithdrawal::with('partner.user:id,name,email')->latest()->get();
        if ($request->expectsJson()) return response()->json(['data' => ['partners' => $partners, 'withdrawals' => $withdrawals]]);
        return Inertia::render('Partners/Index', ['user' => $request->user()->only(['id', 'name', 'email']), 'partners' => $partners, 'withdrawals' => $withdrawals]);
    }

    public function update(Request $request, PartnerProfile $partner)
    {
        $data = $request->validate(['status' => ['sometimes', 'in:active,suspended'], 'commission_rate' => ['sometimes', 'numeric', 'min:0', 'max:100']]);
        $partner->update($data);
        return $request->expectsJson() ? response()->json(['status' => 'success', 'data' => $partner->fresh()]) : back()->with('success', 'Partner updated successfully.');
    }

    public function processWithdrawal(Request $request, PartnerWithdrawal $withdrawal)
    {
        $data = $request->validate(['status' => ['required', 'in:paid,rejected'], 'transaction_reference' => ['nullable', 'string', 'max:255']]);
        $updated = DB::transaction(function () use ($request, $withdrawal, $data) {
            $locked = PartnerWithdrawal::whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') return false;
            $locked->update(['status' => $data['status'], 'transaction_reference' => $data['transaction_reference'] ?? null, 'processed_by_user_id' => $request->user()->id, 'processed_at' => now()]);
            return true;
        });
        if (! $updated) return $request->expectsJson() ? response()->json(['message' => 'Withdrawal is already processed.'], 409) : back()->with('error', 'Withdrawal is already processed.');
        return $request->expectsJson() ? response()->json(['status' => 'success', 'data' => $withdrawal->fresh()]) : back()->with('success', 'Withdrawal processed successfully.');
    }
}
