<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\WaConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WaConfigController extends Controller
{
    /**
     * Show the WhatsApp Fonnte configuration page.
     */
    public function edit(): Response
    {
        $config = WaConfig::firstOrCreate(
            ['id' => 1],
            [
                'monitored_devices' => [],
                'allowed_senders' => [],
                'is_active' => true,
            ]
        );

        // Convert arrays back to comma-separated strings for the form input
        $monitoredDevicesString = implode(', ', $config->monitored_devices ?? []);
        $allowedSendersString = implode(', ', $config->allowed_senders ?? []);

        return Inertia::render('settings/WhatsApp', [
            'config' => [
                'monitored_devices' => $monitoredDevicesString,
                'allowed_senders' => $allowedSendersString,
                'is_active' => $config->is_active,
                'api_token' => $config->api_token,
            ],
            'status' => session('status'),
        ]);
    }

    /**
     * Update the WhatsApp Fonnte configuration.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'monitored_devices' => 'nullable|string',
            'allowed_senders' => 'nullable|string',
            'is_active' => 'required|boolean',
            'api_token' => 'nullable|string',
        ]);

        // Process monitored devices (comma-separated to array)
        $devicesInput = $request->input('monitored_devices') ?? '';
        $monitoredDevices = array_values(array_filter(array_map('trim', explode(',', $devicesInput))));

        // Process allowed senders (comma-separated to array)
        $sendersInput = $request->input('allowed_senders') ?? '';
        $allowedSenders = array_values(array_filter(array_map('trim', explode(',', $sendersInput))));

        $config = WaConfig::firstOrCreate(['id' => 1]);
        $config->update([
            'monitored_devices' => $monitoredDevices,
            'allowed_senders' => $allowedSenders,
            'is_active' => $request->input('is_active'),
            'api_token' => $request->input('api_token'),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('WhatsApp notification settings updated successfully.'),
        ]);

        return to_route('settings.whatsapp.edit');
    }
}
