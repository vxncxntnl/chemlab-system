<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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

    // The QR secret is only handed out where it's needed (the instructor's
    // and admin's activity page), never in general activity listings.
    protected $hidden = ['qr_token'];

    protected function casts(): array
    {
        return [
            'activity_date' => 'date:Y-m-d',
            'return_date' => 'date:Y-m-d',
        ];
    }

    protected static function booted(): void
    {
        // One QR code per activity: every activity gets its own random token.
        static::creating(function (Activity $activity) {
            $activity->qr_token ??= Str::random(40);
        });
    }

    /** The link encoded in the activity's single QR code. */
    public function qrScanUrl(): string
    {
        return url("/qr/{$this->qr_token}");
    }

    /** Inventory items the instructor selected for this activity. */
    public function selectedInventoryIds(): array
    {
        return $this->equipmentQrCodes()->pluck('equipment_inventory_id')->map(fn ($id) => (int) $id)->all();
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

    /**
     * Students can still scan its QR and borrow for it: not cancelled or
     * expired, and its return date (when set) hasn't passed yet. A student
     * who scans late — after the borrow date but before the return date —
     * is still allowed (see borrowDateForNewRequest()).
     */
    public function isOpenForRequests(): bool
    {
        if ($this->status === 'cancelled' || $this->isExpired()) {
            return false;
        }

        return !$this->return_date || !Carbon::parse($this->return_date)->lt(Carbon::today());
    }

    /**
     * The borrow date for a request made now: the activity's date, or today
     * for a student who scanned late (a request can't start in the past).
     */
    public function borrowDateForNewRequest(): string
    {
        return Carbon::parse($this->activity_date)->max(Carbon::today())->toDateString();
    }

    /**
     * Why the QR code / activity no longer takes requests.
     */
    public function closedMessage(): string
    {
        return match (true) {
            $this->status === 'cancelled' => 'This activity was cancelled by your instructor, so its QR code no longer works.',
            $this->return_date && Carbon::parse($this->return_date)->lt(Carbon::today()) => "The activity \"{$this->title}\" is already over (its return date has passed), so you can't borrow for it anymore. Please ask your instructor.",
            default => 'This activity has expired — please ask your instructor for a new QR code.',
        };
    }
}
