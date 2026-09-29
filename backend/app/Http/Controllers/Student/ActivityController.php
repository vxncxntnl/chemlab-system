<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use Inertia\Inertia;

class ActivityController extends Controller
{
    public function index()
    {
        $activities = Activity::with(['instructor'])
            ->whereHas('students', function ($q) {
                $q->where('activity_students.student_id', auth()->id());
            })
            ->orderBy('activity_date', 'desc')
            ->get();

        return Inertia::render('Student/Activities/Index', [
            'activities' => $activities,
        ]);
    }

    public function show(Activity $activity)
    {
        $isEnrolled = $activity->students()->where('activity_students.student_id', auth()->id())->exists();

        if (!$isEnrolled) {
            abort(403);
        }

        $activity->load([
            'instructor',
            'students',
            'borrowings' => function ($q) {
                $q->where('user_id', auth()->id())
                  ->with(['borrowingEquipment.equipment']);
            },
        ]);

        return Inertia::render('Student/Activities/Show', [
            'activity' => $activity,
        ]);
    }
}
