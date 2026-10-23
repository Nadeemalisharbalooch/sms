<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Otp\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class LoginController extends Controller
{
    public function show(Request $request)
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/Login', [
            'twoFactorPending' => $request->session()->has('admin_2fa_user_id'),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request, OtpService $otpService)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = $request->user();

            // The web portal is for Super Admins only. Regular institute users
            // must use the mobile/API app.
            if (! $user->is_admin || ! $user->is_active) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'email' => 'This portal is for Super Admins only.',
                ])->onlyInput('email');
            }

            $twoFactorEnabled = DB::table('system_settings')
                ->where('key', 'admin_two_factor_enabled')
                ->value('value') === '1';

            if ($twoFactorEnabled) {
                $remember = $request->boolean('remember');
                Auth::logout();
                $request->session()->regenerate();
                $request->session()->put([
                    'admin_2fa_user_id' => $user->id,
                    'admin_2fa_remember' => $remember,
                ]);

                try {
                    $otpService->sendOtp($user, 'admin_login');
                } catch (\Throwable $exception) {
                    $request->session()->forget(['admin_2fa_user_id', 'admin_2fa_remember']);

                    return back()->withErrors([
                        'email' => 'We could not send the verification code. Please try signing in again.',
                    ])->onlyInput('email');
                }

                return redirect()->route('login.page')->with('status', 'A verification code was sent to your email.');
            }

            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function verifyTwoFactor(Request $request, OtpService $otpService)
    {
        $validated = $request->validate([
            'otp' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
        ]);

        $userId = $request->session()->get('admin_2fa_user_id');
        $user = $userId ? User::query()->whereKey($userId)->where('is_admin', true)->where('is_active', true)->first() : null;

        if (! $user || ! $otpService->verifyOtp($user, $validated['otp'], 'admin_login')) {
            return back()->withErrors(['otp' => 'The verification code is invalid or expired.']);
        }

        $remember = (bool) $request->session()->pull('admin_2fa_remember', false);
        $request->session()->forget('admin_2fa_user_id');
        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function resendTwoFactor(Request $request, OtpService $otpService)
    {
        $userId = $request->session()->get('admin_2fa_user_id');
        $user = $userId ? User::query()->whereKey($userId)->where('is_admin', true)->where('is_active', true)->first() : null;

        if (! $user) {
            return redirect()->route('login.page')->withErrors(['email' => 'Please sign in again.']);
        }

        try {
            $otpService->sendOtp($user, 'admin_login');
        } catch (\Throwable $exception) {
            return back()->withErrors(['otp' => 'We could not send a new verification code. Please try again.']);
        }

        return back()->with('status', 'A new verification code was sent to your email.');
    }
}
