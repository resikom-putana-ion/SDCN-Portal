<?php

namespace App\Http\Controllers;

use App\Services\SchoolNotificationFeed;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function feed(Request $request, SchoolNotificationFeed $feed)
    {
        $notifications = $feed->cachedForUser($request->user());

        return response()->json([
            'notifications' => $notifications,
            'count' => $notifications->count(),
            'checked_at' => now()->toIso8601String(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
