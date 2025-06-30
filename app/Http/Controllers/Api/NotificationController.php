<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\NotificationResource;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{

    public function index()
    {
        $notifications = Auth::user()->notifications()->paginate(15);
        return $notifications;
        // return NotificationResource::collection($notifications);
    }


    public function stats()
    {
        return response()->json([
            'success' => true,
            'unread_count' => Auth::user()->unreadNotifications()->count(),
        ]);
    }


    public function markAsRead(DatabaseNotification $notification)
    {

        if (Auth::id() !== $notification->notifiable_id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $notification->markAsRead();

        return response()->json(['success' => true, 'message' => 'Notification marked as read.']);
    }


    public function markAllAsRead()
    {
        Auth::user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true, 'message' => 'All unread notifications marked as read.']);
    }


    public function destroy(DatabaseNotification $notification)
    {

        if (Auth::id() !== $notification->notifiable_id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $notification->delete();

        return response()->json(null, 204);
    }
}
