<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = SystemSetting::all()->groupBy('group');

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable',
        ]);

        foreach ($validated['settings'] as $setting) {
            SystemSetting::where('key', $setting['key'])
                ->update(['value' => $setting['value']]);
        }

        AuditLog::log(auth()->id(), 'Updated System Settings', 'system_settings', null, null, [
            'keys' => collect($validated['settings'])->pluck('key')->all(),
        ]);

        return redirect()->back()->with('success', 'Settings updated successfully.');
    }
}
