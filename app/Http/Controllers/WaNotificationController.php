<?php

namespace App\Http\Controllers;

use App\Models\WaNotification;
use Illuminate\Http\Request;

class WaNotificationController extends Controller
{
    /**
     * Render the today's messages chat page.
     */
    public function index()
    {
        return \Inertia\Inertia::render('PesanHariIni');
    }

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

    /**
     * Get all conversations from today grouped by sender.
     */
    public function todayConversations()
    {
        $today = \Carbon\Carbon::today();
        $messages = WaNotification::whereDate('created_at', $today)
            ->orderBy('created_at', 'asc')
            ->get();

        $conversations = [];
        foreach ($messages as $msg) {
            $phone = $msg->sender;
            
            if (!isset($conversations[$phone])) {
                $conversations[$phone] = [
                    'phone' => $phone,
                    'name' => $msg->name ?? $phone,
                    'last_message' => $msg->message,
                    'last_message_time' => $msg->created_at->toIso8601String(),
                    'unread_count' => 0,
                    'messages' => [],
                ];
            }
            
            $conversations[$phone]['messages'][] = [
                'id' => $msg->id,
                'message' => $msg->message,
                'is_outgoing' => (bool)$msg->is_outgoing,
                'time' => $msg->created_at->toIso8601String(),
            ];
            
            $conversations[$phone]['last_message'] = $msg->message;
            $conversations[$phone]['last_message_time'] = $msg->created_at->toIso8601String();
            
            if (!$msg->is_read && !$msg->is_outgoing) {
                $conversations[$phone]['unread_count']++;
            }
        }

        return response()->json(array_values($conversations));
    }

    /**
     * Send outbound reply message via Fonnte gateway.
     */
    public function sendReply(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string',
        ]);

        $phone = $request->input('phone');
        $message = $request->input('message');

        $config = \App\Models\WaConfig::first();
        if (!$config || !$config->api_token || !$config->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'WhatsApp configuration is not active or token is missing.',
            ], 400);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)
                ->connectTimeout(5)
                ->withHeaders([
                    'Authorization' => $config->api_token,
                ])
                ->post('https://api.fonnte.com/send', [
                    'target' => $phone,
                    'message' => $message,
                ]);

            if ($response->successful()) {
                // Find existing name for this sender to reuse
                $name = WaNotification::where('sender', $phone)
                    ->whereNotNull('name')
                    ->orderBy('created_at', 'desc')
                    ->value('name');

                $monitoredDevice = $config->monitored_devices[0] ?? '';

                // Create a record in wa_notifications for this outgoing message
                $newMsg = WaNotification::create([
                    'device' => $monitoredDevice,
                    'sender' => $phone,
                    'name' => $name,
                    'message' => $message,
                    'is_read' => true,
                    'is_outgoing' => true,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Reply sent and recorded.',
                    'data' => [
                        'id' => $newMsg->id,
                        'message' => $newMsg->message,
                        'is_outgoing' => true,
                        'time' => $newMsg->created_at->toIso8601String(),
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Fonnte API responded with error: ' . $response->body(),
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to connect to Fonnte: ' . $e->getMessage(),
            ], 500);
        }
    }
}
