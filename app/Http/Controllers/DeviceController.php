
<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    /**
     * Register a new device.
     */
    public function register(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'device_name' => 'required|string|max:255',
            'device_type' => 'required|string|max:255',
        ]);

        $device = Device::create([
            'user_id'      => $request->user_id,
            'device_name'  => $request->device_name,
            'device_type'  => $request->device_type,
            'status'       => 'offline',
            'last_sync_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Device registered successfully.',
            'device'  => $device,
        ], 201);
    }

    /**
     * Display all registered devices.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'devices' => Device::all(),
        ]);
    }

    /**
     * Display a single device.
     */
    public function show($id)
    {
        $device = Device::find($id);

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'device' => $device,
        ]);
    }

    /**
     * Update a device's status.
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:online,offline',
        ]);

        $device = Device::find($id);

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found.',
            ], 404);
        }

        $device->update([
            'status' => $request->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Device status updated successfully.',
            'device' => $device,
        ]);
    }
}