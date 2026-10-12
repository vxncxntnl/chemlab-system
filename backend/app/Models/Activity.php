<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'class_label',
        'activity_date',
        'start_time',
        'end_time',
        'qr_opens_at',
        'qr_closes_at',
        'qr_live',
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
            'qr_opens_at' => 'datetime',
            'qr_closes_at' => 'datetime',
            'qr_live' => 'boolean',
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

    /**
     * Everyone on the activity's list. Each entry has a status: 'approved'
     * (may borrow), 'pending' (scanned the QR, waiting for the instructor)
     * or 'rejected' (the instructor removed them).
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'activity_students', 'activity_id', 'student_id')
            ->withPivot(['status', 'approved_by', 'approved_at'])
            ->withTimestamps();
    }

    /** Students the instructor approved — the only ones who can borrow for it. */
    public function approvedStudents(): BelongsToMany
    {
        return $this->students()->wherePivot('status', 'approved');
    }

    public function isApprovedStudent(int $userId): bool
    {
        return $this->approvedStudents()->where('activity_students.student_id', $userId)->exists();
    }

    /**
     * "Remember my students": the same instructor already approved this
     * student in another activity with the same class label (e.g. "Chem 101
     * – BSIT4-05"). Without a class label nothing is remembered, so an
     * instructor's other sections are never let in automatically.
     */
    public function wasApprovedBeforeForSameClass(int $userId): bool
    {
        $label = mb_strtolower(trim((string) $this->class_label));
        if ($label === '') {
            return false;
        }

        return \Illuminate\Support\Facades\DB::table('activity_students')
            ->join('activities', 'activities.id', '=', 'activity_students.activity_id')
            ->where('activity_students.student_id', $userId)
            ->where('activity_students.status', 'approved')
            ->where('activities.instructor_id', $this->instructor_id)
            ->where('activities.id', '!=', $this->id)
            ->whereRaw('LOWER(TRIM(activities.class_label)) = ?', [$label])
            ->exists();
    }

    // ── QR time window and Live mode ────────────────────────────────────

    /** Scans are accepted from this many minutes before the start time. */
    public const QR_OPENS_EARLY_MINUTES = 15;

    /** Live (classroom) mode: the code on screen changes this often. */
    public const LIVE_CODE_SECONDS = 60;

    /** Sets when the QR works from the activity's date and start/end time. */
    public function setQrWindowFromTimes(): void
    {
        if (!$this->start_time || !$this->end_time || !$this->activity_date) {
            return;
        }

        $date = Carbon::parse($this->activity_date)->toDateString();
        $this->qr_opens_at = Carbon::parse("{$date} {$this->start_time}")->subMinutes(self::QR_OPENS_EARLY_MINUTES);
        $this->qr_closes_at = Carbon::parse("{$date} {$this->end_time}");
    }

    /**
     * Why a scan right now isn't accepted (null = it is). Activities made
     * before the time window existed have no limit.
     */
    public function qrWindowMessage(): ?string
    {
        if ($this->qr_opens_at && now()->lt($this->qr_opens_at)) {
            return "This QR code opens at {$this->qr_opens_at->format('M j, g:i A')}. Please scan it again then.";
        }
        if ($this->qr_closes_at && now()->gt($this->qr_closes_at)) {
            return 'This QR code has expired (the activity time is over). Ask your instructor for a new one.';
        }

        return null;
    }

    /** The code shown in the Live QR for a given minute (0 = now, -1 = the previous one). */
    public function liveCode(int $offset = 0): string
    {
        $window = intdiv(now()->getTimestamp(), self::LIVE_CODE_SECONDS) + $offset;

        return substr(hash_hmac('sha256', $this->qr_token . '|' . $window, (string) config('app.key')), 0, 12);
    }

    /** A Live QR code is accepted for about two minutes (the current and previous one). */
    public function acceptsLiveCode(?string $code): bool
    {
        return $code !== null && (hash_equals($this->liveCode(), $code) || hash_equals($this->liveCode(-1), $code));
    }

    /** The link in the Live QR right now. */
    public function liveQrUrl(): string
    {
        return $this->qrScanUrl() . '?c=' . $this->liveCode();
    }

    /** A new QR token: every earlier QR code / screenshot stops working. */
    public function regenerateQrToken(): void
    {
        $this->qr_token = Str::random(40);
        $this->save();
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
     * Activities still saved as 'upcoming'/'ongoing' that have ended (same
     * rule as hasEnded()), so the nightly job can save them as expired.
     */
    public function scopeExpired($query)
    {
        return $query->whereIn('status', ['upcoming', 'ongoing'])
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->whereNotNull('return_date')->whereDate('return_date', '<', Carbon::today()))
                ->orWhere(fn ($q) => $q->whereNull('return_date')->whereDate('activity_date', '<', Carbon::yesterday())));
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

    /**
     * An activity ends once its return date has passed (the day after it).
     * Older activities without a return date end two days after their date.
     */
    public function hasEnded(): bool
    {
        return $this->return_date
            ? Carbon::parse($this->return_date)->lt(Carbon::today())
            : Carbon::parse($this->activity_date)->lt(Carbon::yesterday());
    }

    /**
     * An upcoming/ongoing activity that has ended reads as 'expired'
     * everywhere right away — not only after the nightly job saves it.
     */
    protected function status(): Attribute
    {
        return Attribute::get(fn (?string $value) => in_array($value, ['upcoming', 'ongoing'], true) && $this->hasEnded()
            ? 'expired'
            : $value);
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired';
    }

    /**
     * Students can still scan its QR and borrow for it: upcoming or ongoing
     * (so not ended, cancelled or completed). A student who scans late —
     * after the borrow date but before the return date has passed — is
     * still allowed (see borrowDateForNewRequest()).
     */
    public function isOpenForRequests(): bool
    {
        return in_array($this->status, ['upcoming', 'ongoing'], true);
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
            $this->status === 'completed' => "The activity \"{$this->title}\" is already completed, so you can't borrow for it anymore. Please ask your instructor.",
            $this->return_date && Carbon::parse($this->return_date)->lt(Carbon::today()) => "The activity \"{$this->title}\" is already over (its return date has passed), so you can't borrow for it anymore. Please ask your instructor.",
            default => 'This activity has expired — please ask your instructor for a new QR code.',
        };
    }
}
