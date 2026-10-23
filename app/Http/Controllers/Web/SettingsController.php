<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Settings', [
            'user' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ],
            'adminTwoFactorEnabled' => DB::table('system_settings')
                ->where('key', 'admin_two_factor_enabled')
                ->value('value') === '1',
        ]);
    }

    public function updateTwoFactor(Request $request)
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        DB::table('system_settings')->updateOrInsert(
            ['key' => 'admin_two_factor_enabled'],
            ['value' => $validated['enabled'] ? '1' : '0', 'updated_at' => now(), 'created_at' => now()],
        );

        return back()->with('success', 'Admin two-factor authentication setting updated.');
    }
}
