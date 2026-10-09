<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Institute;
use App\Models\Plan;
use App\Models\SubscriptionInvoice;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard', [
            'user' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ],
            'institutesCount' => Institute::count(),
            'plansCount' => Plan::where('is_active', true)->count(),
            'pendingInvoicesCount' => SubscriptionInvoice::where('status', 'verification_pending')->count(),
            'usersCount' => \App\Models\User::count(),
        ]);
    }
}
