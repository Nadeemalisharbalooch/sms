<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\PartnerClick;
use App\Models\PartnerCommission;
use App\Models\PartnerProfile;
use App\Models\PartnerWithdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PartnerController extends Controller
{
    private function referralLink(string $code): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/ref/'.rawurlencode($code);
    }

    private function profile(Request $request): PartnerProfile
    {
        $profile = $request->user()->partnerProfile;
        abort_unless($profile && $profile->status === 'active', 403, 'An active partner profile is required.');
        return $profile;
    }

    public function setupStatus(Request $request)
    {
        $user = $request->user();
        $profile = $user->partnerProfile;

        return response()->json(['status' => 'success', 'data' => [
            'user' => $user->only(['id', 'name', 'email', 'is_institute', 'is_partner']),
            'partner_profile' => $profile ? [
                'id' => $profile->id,
                'company_name' => $profile->company_name,
                'phone' => $profile->phone,
                'website_url' => $profile->website_url,
                'promotion_methods' => $profile->promotion_methods,
                'tax_id_or_cnic' => $profile->tax_id_or_cnic,
                'referral_code' => $profile->referral_code,
                'referral_link' => $this->referralLink($profile->referral_code),
                'status' => $profile->status,
                'commission_rate' => (float) $profile->commission_rate,
                'terms_agreed_at' => $profile->terms_agreed_at,
            ] : null,
        ]]);
    }

    public function setup(Request $request)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'promotion_methods' => ['required', 'array', 'min:1'],
            'promotion_methods.*' => ['string', 'max:50'],
            'tax_id_or_cnic' => ['required', 'string', 'max:50'],
            'agreed_to_terms' => ['accepted'],
        ]);
        abort_if($request->user()->is_admin, 403, 'Admin accounts cannot register as partners.');
        abort_if($request->user()->partnerProfile()->exists(), 409, 'Partner profile already exists.');

        $profile = DB::transaction(function () use ($request, $data) {
            do { $code = Str::upper(Str::substr(Str::slug(Str::before($data['company_name'], ' '), ''), 0, 20)).random_int(1000, 9999); }
            while (PartnerProfile::where('referral_code', $code)->exists());

            $request->user()->update(['is_partner' => true, 'phone' => $data['phone']]);
            return PartnerProfile::create([
                'user_id' => $request->user()->id,
                'company_name' => $data['company_name'], 'phone' => $data['phone'],
                'website_url' => $data['website_url'] ?? null, 'promotion_methods' => $data['promotion_methods'],
                'tax_id_or_cnic' => $data['tax_id_or_cnic'], 'referral_code' => $code,
                'status' => 'inactive', 'commission_rate' => 10, 'terms_agreed_at' => now(),
            ]);
        });

        return response()->json(['status' => 'success', 'message' => 'Partner profile setup successfully.', 'data' => [
            'user' => $request->user()->only(['id', 'name', 'email', 'is_institute', 'is_partner']),
            'partner_profile' => ['id' => $profile->id, 'referral_code' => $profile->referral_code, 'referral_link' => $this->referralLink($profile->referral_code), 'status' => $profile->status, 'commission_rate' => (float) $profile->commission_rate],
        ]], 201);
    }

    public function updateSetup(Request $request)
    {
        $profile = $request->user()->partnerProfile;
        abort_unless($profile, 404, 'Partner profile not found.');

        $data = $request->validate([
            'company_name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:30'],
            'website_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'promotion_methods' => ['sometimes', 'required', 'array', 'min:1'],
            'promotion_methods.*' => ['string', 'max:50'],
            'tax_id_or_cnic' => ['sometimes', 'required', 'string', 'max:50'],
        ]);

        DB::transaction(function () use ($request, $profile, $data) {
            $profile->update($data);
            if (array_key_exists('phone', $data)) {
                $request->user()->update(['phone' => $data['phone']]);
            }
        });

        $profile->refresh();

        return response()->json(['status' => 'success', 'message' => 'Partner profile updated successfully.', 'data' => [
            'user' => $request->user()->only(['id', 'name', 'email', 'is_institute', 'is_partner']),
            'partner_profile' => [
                'id' => $profile->id,
                'company_name' => $profile->company_name,
                'phone' => $profile->phone,
                'website_url' => $profile->website_url,
                'promotion_methods' => $profile->promotion_methods,
                'tax_id_or_cnic' => $profile->tax_id_or_cnic,
                'referral_code' => $profile->referral_code,
                'referral_link' => $this->referralLink($profile->referral_code),
                'status' => $profile->status,
                'commission_rate' => (float) $profile->commission_rate,
                'terms_agreed_at' => $profile->terms_agreed_at,
            ],
        ]]);
    }

    public function track(Request $request)
    {
        $data = $request->validate(['ref_code' => ['required', 'string', 'max:32']]);
        $profile = PartnerProfile::where('referral_code', $data['ref_code'])->where('status', 'active')->firstOrFail();
        $ipHash = $request->ip() ? hash_hmac('sha256', $request->ip(), (string) config('app.key')) : null;
        $alreadyCounted = $ipHash && PartnerClick::where('partner_profile_id', $profile->id)->where('ip_hash', $ipHash)->whereDate('created_at', today())->exists();
        if (! $alreadyCounted) PartnerClick::create(['partner_profile_id' => $profile->id, 'ip_hash' => $ipHash]);

        return response()->json(['status' => 'success', 'message' => 'Click tracked successfully', 'data' => ['tracking_cookie_value' => $profile->referral_code, 'expires_in_days' => 30]])
            ->cookie('partner_ref', $profile->referral_code, 60 * 24 * 30, '/', null, $request->isSecure(), true, false, 'Lax');
    }

    public function dashboard(Request $request)
    {
        $profile = $this->profile($request);
        $commissions = $profile->commissions();
        $pending = (float) (clone $commissions)->where('status', 'pending')->where('clears_at', '>', now())->sum('amount');
        $earned = (float) (clone $commissions)->sum('amount');
        $withdrawn = (float) $profile->withdrawals()->where('status', 'paid')->sum('amount');
        $reserved = (float) $profile->withdrawals()->whereIn('status', ['pending', 'paid'])->sum('amount');
        $available = max(0, (float) (clone $commissions)->where(function ($query) {
            $query->where('status', 'available')->orWhere(fn ($pending) => $pending->where('status', 'pending')->where('clears_at', '<=', now()));
        })->sum('amount') - $reserved);
        $signups = \App\Models\Institute::where('referral_id', $profile->referral_code);

        return response()->json(['status' => 'success', 'data' => [
            'referral_link' => $this->referralLink($profile->referral_code), 'referral_code' => $profile->referral_code,
            'has_payout_details' => (bool) $profile->payout_method && (bool) $profile->payout_details,
            'metrics' => ['total_clicks' => PartnerClick::where('partner_profile_id', $profile->id)->count(), 'total_signups' => (clone $signups)->count(), 'active_subscriptions' => (clone $signups)->whereHas('subscription', fn ($q) => $q->where('status', 'active'))->count()],
            'wallet' => ['pending_clearance' => $pending, 'available_balance' => $available, 'total_withdrawn' => $withdrawn],
        ]]);
    }

    public function payoutSettings(Request $request)
    {
        $profile = $this->profile($request);
        if ($request->isMethod('get')) {
            return response()->json(['status' => 'success', 'data' => [
                'has_payout_details' => (bool) $profile->payout_method && (bool) $profile->payout_details,
                'payout_method' => $profile->payout_method,
                'payout_details' => $profile->payout_details,
            ]]);
        }

        $data = $request->validate(['payout_method' => ['required', 'in:bank_transfer'], 'payout_details' => ['required', 'array'], 'payout_details.bank_name' => ['required', 'string', 'max:100'], 'payout_details.account_title' => ['required', 'string', 'max:150'], 'payout_details.iban_or_account' => ['required', 'string', 'max:60']]);
        $profile->update($data);
        return response()->json(['status' => 'success', 'message' => 'Payout settings updated successfully.']);
    }

    public function withdrawals(Request $request)
    {
        $profile = $this->profile($request);
        if ($request->isMethod('post')) {
            $amount = (float) $request->validate(['amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2']])['amount'];
            $withdrawal = DB::transaction(function () use ($profile, $amount) {
                $locked = PartnerProfile::whereKey($profile->id)->lockForUpdate()->firstOrFail();
                if (! $locked->payout_method || ! $locked->payout_details) throw ValidationException::withMessages(['payout' => 'Please add your bank details before requesting a withdrawal.']);
                $available = (float) $locked->commissions()->where(function ($query) {
                    $query->where('status', 'available')->orWhere(fn ($pending) => $pending->where('status', 'pending')->where('clears_at', '<=', now()));
                })->sum('amount') - (float) $locked->withdrawals()->whereIn('status', ['pending', 'paid'])->sum('amount');
                if ($amount > max(0, $available)) throw ValidationException::withMessages(['amount' => 'Withdrawal amount exceeds your available balance.']);
                return $locked->withdrawals()->create(['amount' => $amount, 'status' => 'pending']);
            });
            return response()->json(['status' => 'success', 'message' => 'Withdrawal request submitted for Admin approval.', 'data' => $withdrawal], 201);
        }
        $withdrawals = $profile->withdrawals()->latest()->paginate(15);

        return response()->json(['status' => 'success', 'data' => [
            'withdrawals' => $withdrawals->items(),
            'pagination' => [
                'current_page' => $withdrawals->currentPage(),
                'total_pages' => $withdrawals->lastPage(),
                'total_records' => $withdrawals->total(),
            ],
        ]]);
    }

    public function referrals(Request $request)
    {
        $profile = $this->profile($request);
        $institutes = \App\Models\Institute::where('referral_id', $profile->referral_code)->with(['subscription.plan'])->latest()->paginate(15);
        $institutes->getCollection()->transform(fn ($institute) => ['id' => $institute->id, 'institute_name' => $institute->name, 'signup_date' => $institute->created_at, 'status' => $institute->subscription?->status ?? 'trialing', 'plan_name' => $institute->subscription?->plan?->name ?? 'Free Trial', 'commission_earned' => (float) $profile->commissions()->where('institute_id', $institute->id)->sum('amount')]);
        return response()->json(['status' => 'success', 'data' => ['referrals' => $institutes->items(), 'pagination' => ['current_page' => $institutes->currentPage(), 'total_pages' => $institutes->lastPage(), 'total_records' => $institutes->total()]]]);
    }
}
