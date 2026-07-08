<?php

namespace App\Http\Controllers;

use App\Models\WaNotification;
use Illuminate\Http\Request;

class WaNotificationController extends Controller
{
    /**
     * Get all unread WhatsApp notifications.
     */
    public function unread()
    {
        $notifications = WaNotification::where('is_read', false)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($notifications);
    }

    /**
     * Mark specified WhatsApp notifications as read.
     */
    public function markRead(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:wa_notifications,id',
        ]);

        $ids = $request->input('ids');

        WaNotification::whereIn('id', $ids)->update([
            'is_read' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notifications marked as read.',
        ]);
    }
}
