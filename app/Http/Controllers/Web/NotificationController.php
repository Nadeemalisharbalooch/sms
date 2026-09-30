<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:read,unread'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $notifications = $request->user()->notifications()
            ->when(
                ($filters['status'] ?? null) === 'unread',
                fn ($query) => $query->whereNull('read_at')
            )
            ->when(
                ($filters['status'] ?? null) === 'read',
                fn ($query) => $query->whereNotNull('read_at')
            )
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where('data', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Notifications/Index', [
            'user' => $request->user()->only(['id', 'name', 'email']),
            'notifications' => $notifications,
            'filters' => $filters,
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * Lightweight JSON feed of the most recent notifications.
     *
     * Used by the admin shell as a fallback whenever the Reverb socket is not
     * connected, so the tray keeps updating without a page reload.
     */
    public function feed(Request $request)
    {
        $limit = max(1, min(50, $request->integer('limit', 15)));

        $notifications = $request->user()->notifications()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (DatabaseNotification $notification) => [
                'id' => $notification->id,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
                'data' => $notification->data,
            ]);

        return response()->json(['notifications' => $notifications]);
    }

    public function markRead(Request $request, DatabaseNotification $notification)
    {
        if ($notification->notifiable_id !== $request->user()->id
            || $notification->notifiable_type !== $request->user()->getMorphClass()) {
            abort(404);
        }

        $notification->markAsRead();

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }
}