<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $query = Activity::with(['instructor', 'students']);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', "%{$request->search}%")
                  ->orWhere('department', 'like', "%{$request->search}%");
            });
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $activities = $query->orderBy('activity_date', 'desc')->paginate(15);

        return Inertia::render('Admin/Activities/Index', [
            'activities' => $activities,
            'filters'    => $request->only(['search', 'status']),
        ]);
    }

    public function show(Activity $activity)
    {
        $activity->load(['instructor', 'students', 'borrowings.user', 'borrowings.borrowingEquipment.equipment']);

        return Inertia::render('Admin/Activities/Show', [
            'activity' => $activity,
        ]);
    }

    public function edit(Activity $activity)
    {
        if ($activity->isExpired()) {
            return redirect()->route('admin.activities.show', $activity)
                ->with('error', 'This activity has expired and can no longer be edited.');
        }

        $activity->load('students');
        $instructors = User::where('role', 'faculty')->where('status', 'active')->orderBy('name')->get();
        $students    = User::where('role', 'student')->orderBy('name')->get();

        return Inertia::render('Admin/Activities/Edit', [
            'activity'    => $activity,
            'instructors' => $instructors,
            'students'    => $students,
        ]);
    }

    public function update(Request $request, Activity $activity)
    {
        if ($activity->isExpired()) {
            return redirect()->route('admin.activities.show', $activity)
                ->with('error', 'This activity has expired and can no longer be edited.');
        }

        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'instructor_id'=> 'required|exists:users,id',
            'department'   => 'required|string|max:255',
            'activity_date'=> 'required|date',
            'return_date'  => 'required|date|after_or_equal:activity_date',
            'status'       => 'required|in:upcoming,ongoing,completed,cancelled,expired',
            'description'  => 'nullable|string|max:1000',
            'student_ids'  => 'nullable|array',
            'student_ids.*'=> 'exists:users,id',
        ], [
            'return_date.after_or_equal' => 'The return date must be on or after the borrow date.',
        ]);

        $activity->update($validated);
        $activity->students()->sync($validated['student_ids'] ?? []);

        AuditLog::log(auth()->id(), 'Updated Activity', 'activities', $activity->id);

        return redirect()->route('admin.activities.index')
            ->with('success', 'Activity updated successfully.');
    }

    public function destroy(Activity $activity)
    {
        AuditLog::log(auth()->id(), 'Deleted Activity', 'activities', $activity->id);

        $activity->delete();

        return redirect()->route('admin.activities.index')
            ->with('success', 'Activity deleted.');
    }
}
