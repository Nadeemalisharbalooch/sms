<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionInvoice;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionInvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:pending,payment_submitted,paid,cancelled'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $invoices = SubscriptionInvoice::query()
            ->with([
                'institute:id,public_id,name,email',
                'plan:id,name,billing_interval',
                'subscription:id,institute_id,plan_id,status,starts_at,ends_at',
            ])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('invoice_number', 'like', "%{$search}%")
                        ->orWhere('payment_reference', 'like', "%{$search}%")
                        ->orWhereHas('institute', fn ($instituteQuery) => $instituteQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('SubscriptionInvoices/Index', [
            'user' => $request->user()->only(['id', 'name', 'email']),
            'invoices' => $invoices,
            'filters' => $filters,
        ]);
    }
}
