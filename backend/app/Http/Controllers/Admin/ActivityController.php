<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\ActivityService;
use App\Support\CampusAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        // Activities of this admin's campus (by the instructor's campus).
        $query = CampusAccess::activities(Activity::query(), CampusAccess::adminCampusId(auth()->user()))->with(['instructor', 'students'])
            ->withCount(['borrowings as requests_count' => fn ($q) => $q->withTrashed()]);

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

        if ($activity->status === 'cancelled') {
            return redirect()->route('admin.activities.show', $activity)
                ->with('error', 'This activity was cancelled and can no longer be edited.');
        }

        $activity->load('students');
        $campusId    = CampusAccess::adminCampusId(auth()->user());
        $instructors = User::where('role', 'faculty')->where('status', 'active')->where('campus_id', $campusId)->orderBy('name')->get();
        $students    = User::where('role', 'student')->where('campus_id', $campusId)->orderBy('name')->get();

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

        if ($activity->status === 'cancelled') {
            return redirect()->route('admin.activities.show', $activity)
                ->with('error', 'This activity was cancelled and can no longer be edited.');
        }

        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            // Own campus only — never trust IDs from the form.
            'instructor_id'=> ['required', Rule::exists('users', 'id')->where('role', 'faculty')->where('campus_id', CampusAccess::adminCampusId(auth()->user()))],
            'department'   => 'required|string|max:255',
            'activity_date'=> 'required|date',
            'return_date'  => 'required|date|after_or_equal:activity_date',
            'status'       => 'required|in:upcoming,ongoing,completed,cancelled,expired',
            'description'  => 'nullable|string|max:1000',
            'student_ids'  => 'nullable|array',
            'student_ids.*'=> [Rule::exists('users', 'id')->where('campus_id', CampusAccess::adminCampusId(auth()->user()))],
        ], [
            'return_date.after_or_equal' => 'The return date must be on or after the borrow date.',
        ]);

        $activity->update($validated);
        $activity->students()->sync($validated['student_ids'] ?? []);

        AuditLog::log(auth()->id(), 'Updated Activity', 'activities', $activity->id);

        return redirect()->route('admin.activities.index')
            ->with('success', 'Activity updated successfully.');
    }

    public function destroy(Activity $activity, ActivityService $activities)
    {
        // Same rule as faculty: deleting would unlink (not cancel) its
        // requests, so an activity with requests is cancelled instead.
        if ($activities->hasAnyRequests($activity)) {
            return back()->with('error', 'This activity already has borrowing requests, so it can\'t be deleted. Use Cancel Activity instead — it keeps the history and cancels requests that haven\'t been released.');
        }

        AuditLog::log(auth()->id(), 'Deleted Activity', 'activities', $activity->id);

        $activity->delete();

        return redirect()->route('admin.activities.index')
            ->with('success', 'Activity deleted.');
    }

    public function cancel(Activity $activity, ActivityService $activities)
    {
        if (in_array($activity->status, ['cancelled', 'completed', 'expired'], true)) {
            return back()->with('error', "This activity is already {$activity->status}.");
        }

        try {
            $count = $activities->cancel($activity, auth()->user());
        } catch (\App\Exceptions\InvalidBorrowingStateException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.activities.show', $activity)
            ->with('success', $count
                ? "Activity cancelled. {$count} borrowing request(s) were cancelled and the students were notified."
                : 'Activity cancelled.');
    }
}
