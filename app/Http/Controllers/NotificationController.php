<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = 9;
        $user = auth()->user();
        $notifications = $user->notifications()
            ->when(
                $request->cursor,
                fn ($query) => $query->where('id', '<', $request->cursor)
            )
            ->limit($limit + 1)
            ->orderBy('created_at', 'desc')
            ->get();

        $hasMore = $notifications->count() > $limit;
        $notifications = $notifications->take($limit);
        $nextCursor = $hasMore ? $notifications->last()->id : null;

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $user->unreadNotifications->count(),
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore
        ]);
    }

    public function markAsRead(string $id): JsonResponse
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['message' => 'Notification marked as read']);
    }

    public function markAllAsRead(): JsonResponse
    {
        auth()->user()->unreadNotifications->markAsRead();
        
        return response()->json(['message' => 'All notifications marked as read']);
    }

    public function destroy(string $id): JsonResponse
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->delete();

        return response()->json(['message' => 'Notification deleted']);
    }
}
