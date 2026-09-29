<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\Location;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LocationController extends Controller
{
    public function index(Request $request)
    {
        $query = Location::with('campus');

        if ($request->campus_id) {
            $query->where('campus_id', $request->campus_id);
        }

        $locations = $query->paginate(10);
        $campuses = Campus::all();

        return Inertia::render('Admin/Locations/Index', [
            'locations' => $locations,
            'campuses' => $campuses,
            'filters' => $request->only(['campus_id']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Locations/Create', [
            'campuses' => Campus::all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'campus_id' => 'required|exists:campuses,id',
            'location_name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $location = Location::create($validated);

        AuditLog::log(auth()->id(), 'Created Location', 'locations', $location->id);

        return redirect()->route('admin.locations.index')
            ->with('success', 'Location created successfully.');
    }

    public function edit(Location $location)
    {
        return Inertia::render('Admin/Locations/Edit', [
            'location' => $location,
            'campuses' => Campus::all(),
        ]);
    }

    public function update(Request $request, Location $location)
    {
        $validated = $request->validate([
            'campus_id' => 'required|exists:campuses,id',
            'location_name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $location->update($validated);

        AuditLog::log(auth()->id(), 'Updated Location', 'locations', $location->id);

        return redirect()->route('admin.locations.index')
            ->with('success', 'Location updated successfully.');
    }

    public function destroy(Location $location)
    {
        AuditLog::log(auth()->id(), 'Deleted Location', 'locations', $location->id);

        $location->delete();

        return redirect()->route('admin.locations.index')
            ->with('success', 'Location deleted successfully.');
    }
}
