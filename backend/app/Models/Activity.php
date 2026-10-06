<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Activity extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'instructor_id',
        'department',
        'activity_date',
        'return_date',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'activity_date' => 'date:Y-m-d',
            'return_date' => 'date:Y-m-d',
        ];
    }

    // Past activity records should still show who taught them even if that
    // instructor's account is later soft-deleted.
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id')->withTrashed();
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'activity_students', 'activity_id', 'student_id')
            ->withTimestamps();
    }

    public function borrowings(): HasMany
    {
        return $this->hasMany(Borrowing::class);
    }

    public function equipmentQrCodes(): HasMany
    {
        return $this->hasMany(ActivityEquipmentQr::class, 'activity_id');
    }

    /**
     * Activities that are still 'upcoming'/'ongoing' but whose date is more
     * than a day in the past — i.e. never marked completed/cancelled and stale.
     */
    public function scopeExpired($query)
    {
        return $query->whereIn('status', ['upcoming', 'ongoing'])
            ->whereDate('activity_date', '<', Carbon::yesterday());
    }

    /**
     * An activity is for students of its department's course (e.g. a
     * Nursing activity is for Nursing students). Faculty departments and
     * student courses come from the same program list, so they're compared
     * directly. An activity with no department set allows any course.
     */
    public function allowsCourse(?string $course): bool
    {
        $department = trim((string) $this->department);

        return $department === ''
            || strcasecmp($department, trim((string) $course)) === 0;
    }

    public function isExpired(): bool
    {
        if ($this->status === 'expired') {
            return true;
        }

        if (!in_array($this->status, ['upcoming', 'ongoing'])) {
            return false;
        }

        return Carbon::parse($this->activity_date)->lt(Carbon::yesterday());
    }
}
