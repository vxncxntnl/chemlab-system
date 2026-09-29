<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CampusController extends Controller
{
    public function index()
    {
        $campuses = Campus::withCount('locations')->paginate(10);

        return Inertia::render('Admin/Campuses/Index', [
            'campuses' => $campuses,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Campuses/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'campus_name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'contact_info' => 'nullable|string|max:255',
        ]);

        $campus = Campus::create($validated);

        AuditLog::log(auth()->id(), 'Created Campus', 'campuses', $campus->id);

        return redirect()->route('admin.campuses.index')
            ->with('success', 'Campus created successfully.');
    }

    public function edit(Campus $campus)
    {
        return Inertia::render('Admin/Campuses/Edit', [
            'campus' => $campus,
        ]);
    }

    public function update(Request $request, Campus $campus)
    {
        $validated = $request->validate([
            'campus_name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'contact_info' => 'nullable|string|max:255',
        ]);

        $campus->update($validated);

        AuditLog::log(auth()->id(), 'Updated Campus', 'campuses', $campus->id);

        return redirect()->route('admin.campuses.index')
            ->with('success', 'Campus updated successfully.');
    }

    public function destroy(Campus $campus)
    {
        AuditLog::log(auth()->id(), 'Deleted Campus', 'campuses', $campus->id);

        $campus->delete();

        return redirect()->route('admin.campuses.index')
            ->with('success', 'Campus deleted successfully.');
    }
}
