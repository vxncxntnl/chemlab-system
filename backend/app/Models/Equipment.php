<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;

/**
 * Soft-deleted rather than hard-deleted: an equipment record is referenced
 * by borrowing/damage/maintenance history that must stay readable (and
 * reachable via ->withTrashed() on those relationships) even after the
 * physical item is retired from the catalog. Deleting it now just hides it
 * from normal listings and blocks new activity against it, instead of
 * silently destroying every past record that ever mentioned it.
 */
class Equipment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'equipment';

    protected $fillable = [
        'equipment_code',
        'name',
        'description',
        'category',
        'category_id',
        'serial_number',
        'image_path',
        'condition',
        'is_available',
        'tracks_units',
        'course_restriction',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'tracks_units' => 'boolean',
        ];
    }

    public function categoryRelation(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /** Each physical unit with its serial number (serial-tracked items only). */
    public function units(): HasMany
    {
        return $this->hasMany(EquipmentUnit::class)->orderBy('unit_code');
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(EquipmentInventory::class);
    }

    public function borrowingEquipment(): HasMany
    {
        return $this->hasMany(BorrowingEquipment::class);
    }

    public function damageReports(): HasMany
    {
        return $this->hasMany(DamageReport::class);
    }

    public function borrowings(): BelongsToMany
    {
        return $this->belongsToMany(Borrowing::class, 'borrowing_equipment')
            ->withPivot('quantity', 'condition_on_borrow', 'condition_on_return')
            ->withTimestamps();
    }

    public function getTotalAvailableAttribute(): int
    {
        return $this->inventory->sum('available_quantity');
    }

    protected static function booted(): void
    {
        static::creating(function (self $equipment) {
            if (empty($equipment->equipment_code)) {
                $equipment->equipment_code = self::nextCode();
            }
        });
    }

    private static function nextCode(): string
    {
        $nextId = (static::max('id') ?? 0) + 1;
        return 'EQP-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);
    }

    /**
     * `max('id') + 1` is a read-then-write with no lock between the two —
     * two admins creating equipment at nearly the same moment can both read
     * the same max(id), producing the same equipment_code for two different
     * rows. The `equipment_code` column already has a DB-level unique
     * constraint (the real safety net: it's what actually stops the
     * collision from being persisted), so this wraps the insert in a
     * bounded retry loop that catches exactly that constraint violation and
     * regenerates a fresh code — the loser of the race gets a new id-based
     * code and a second attempt instead of a hard failure.
     */
    public static function createWithUniqueCode(array $attributes, int $maxAttempts = 5): self
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return static::create($attributes);
            } catch (QueryException $e) {
                $isCodeCollision = str_contains(strtolower($e->getMessage()), 'equipment_code')
                    || str_contains(strtolower($e->getMessage()), 'unique constraint');

                if (!$isCodeCollision || $attempt === $maxAttempts) {
                    throw $e;
                }
                // Retry — the next attempt recomputes max(id) fresh, past
                // the row that just won the race.
            }
        }

        throw new \RuntimeException('Failed to generate a unique equipment code after ' . $maxAttempts . ' attempts.');
    }
}
