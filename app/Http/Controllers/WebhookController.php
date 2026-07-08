<?php

namespace App\Http\Controllers;

use App\Models\WaConfig;
use App\Models\WaNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle incoming webhook from Fonnte.
     */
    public function handleFonnte(Request $request)
    {
        // Fonnte webhook payload fields:
        // device: receiving device number
        // sender: sender's whatsapp number
        // name: sender's profile name
        // message: message text
        
        $device = $request->input('device');
        $sender = $request->input('sender');
        $name = $request->input('name') ?? 'WhatsApp User';
        $message = $request->input('message');

        if (!$device || !$sender) {
            return response()->json([
                'success' => false,
                'message' => 'Missing device or sender parameter.',
            ], 400);
        }

        // Get WhatsApp Config
        $config = WaConfig::first();

        if (!$config || !$config->is_active) {
            return response()->json([
                'success' => true,
                'message' => 'WhatsApp configuration is not found or inactive.',
            ]);
        }

        $monitoredDevices = $config->monitored_devices ?? [];
        $allowedSenders = $config->allowed_senders ?? [];

        // Check if device matches
        $deviceMatched = false;
        foreach ($monitoredDevices as $monitoredDevice) {
            if ($this->phoneNumbersMatch($monitoredDevice, $device)) {
                $deviceMatched = true;
                break;
            }
        }

        // Check if sender matches
        $senderMatched = false;
        foreach ($allowedSenders as $allowedSender) {
            if ($this->phoneNumbersMatch($allowedSender, $sender)) {
                $senderMatched = true;
                break;
            }
        }

        if ($deviceMatched && $senderMatched) {
            // Save notification to database
            WaNotification::create([
                'device' => $device,
                'sender' => $sender,
                'name' => $name,
                'message' => $message,
                'is_read' => false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification recorded.',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Message ignored (does not match device or sender filters).',
        ]);
    }

    /**
     * Helper to match phone numbers robustly (compares the last 9 digits).
     */
    private function phoneNumbersMatch(string $num1, string $num2): bool
    {
        $clean1 = preg_replace('/\D/', '', $num1);
        $clean2 = preg_replace('/\D/', '', $num2);

        if (strlen($clean1) < 9 || strlen($clean2) < 9) {
            return $clean1 === $clean2;
        }

        return substr($clean1, -9) === substr($clean2, -9);
    }
}
