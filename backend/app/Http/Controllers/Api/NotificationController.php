<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The authenticated user's own notifications.
 *
 * Every query here starts from `$request->user()->notifications` — the
 * relationship is constrained to the token holder, so one user can never read
 * or modify another's notifications, and cross-group leakage is impossible
 * regardless of what the client sends.
 */
class NotificationController extends Controller
{
    private const MAX_PER_PAGE = 50;

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max((int) $request->integer('per_page', 20), 1), self::MAX_PER_PAGE);

        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return NotificationResource::collection($notifications);
    }

    /** Badge count for the bell icon. */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ['unread_count' => $request->user()->unreadNotifications()->count()],
        ]);
    }

    /**
     * Mark one notification read. Scoped to the caller's own notifications, so
     * another user's id resolves to 404 rather than being marked.
     */
    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $record = $request->user()->notifications()->whereKey($notification)->first();

        if ($record === null) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        $record->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read',
            'data' => ['unread_count' => $request->user()->unreadNotifications()->count()],
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'message' => 'All notifications marked as read',
            'data' => ['unread_count' => 0],
        ]);
    }
}
