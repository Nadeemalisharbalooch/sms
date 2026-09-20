<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * List the authenticated user's dashboard notifications.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', 'in:read,unread'],
            'priority' => ['nullable', 'in:high,standard'],
            'category' => ['nullable', 'string', 'max:255'],
        ]);

        $query = $request->user()->notifications();

        if (($validated['status'] ?? null) === 'read') {
            $query->whereNotNull('read_at');
        } elseif (($validated['status'] ?? null) === 'unread') {
            $query->whereNull('read_at');
        }

        if (($validated['priority'] ?? null) !== null) {
            $query->where('data->priority', $validated['priority']);
        }

        if (($validated['category'] ?? null) !== null) {
            $query->where('data->category', $validated['category']);
        }

        $notifications = $query->latest()->paginate($request->integer('per_page', 20));

        return ResponseService::success([
            'notifications' => collect($notifications->items())->map(fn ($notification) => $this->payload($notification)),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
            'meta' => [
                'unread_count' => $request->user()->unreadNotifications()->count(),
            ],
        ], 'Notifications retrieved successfully');
    }

    /**
     * Number of unread notifications (lightweight badge endpoint).
     */
    public function unreadCount(Request $request)
    {
        return ResponseService::success([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ], 'Unread count retrieved successfully');
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->whereKey($id)->first();

        if ($notification === null) {
            return ResponseService::notFound('Notification not found');
        }

        $notification->markAsRead();

        return ResponseService::success($this->payload($notification->fresh()), 'Notification marked as read successfully');
    }

    /**
     * Mark all unread notifications as read.
     */
    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return ResponseService::success([
            'unread_count' => 0,
        ], 'All notifications marked as read successfully');
    }

    /**
     * Delete a notification from the tray.
     */
    public function destroy(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->whereKey($id)->first();

        if ($notification === null) {
            return ResponseService::notFound('Notification not found');
        }

        $notification->delete();

        return ResponseService::success(null, 'Notification removed successfully');
    }

    private function payload($notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];

        return [
            'id' => $notification->id,
            'type' => $data['type'] ?? class_basename($notification->type),
            'title' => $data['title'] ?? 'Notification',
            'body' => $data['body'] ?? '',
            'priority' => $data['priority'] ?? 'standard',
            'category' => $data['category'] ?? 'general',
            'action_text' => $data['action_text'] ?? null,
            'action_url' => $data['action_url'] ?? null,
            'data' => $data['data'] ?? [],
            'read_at' => $notification->read_at,
            'created_at' => $notification->created_at,
            'created_at_diff' => $notification->created_at?->diffForHumans(),
        ];
    }
}