<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\Borrowing;
use App\Models\Campus;
use App\Models\DamageReport;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\Location;
use App\Models\MaintenanceLog;
use App\Models\Reservation;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Campus-based access for admins (lab staff already have their own checks).
 *
 * Each campus has its own Admin, who sees and manages that campus only.
 * Carmen is the MAIN campus: its admin additionally
 *  - creates/manages the Admin and Lab Staff accounts of the other campuses,
 *  - approves faculty sign-ups of campuses that have no admin yet,
 *  - owns the system-wide setup (campuses, categories, settings, locations,
 *    the Lab Inventory masterfile) and the shared equipment catalog.
 *
 * How a record belongs to a campus (there's no campus column on most):
 *  stock → equipment_inventory.campus_id; borrowing → its items' stock;
 *  damage report → its stock item / borrowing; maintenance → campus_id
 *  (older logs: where the equipment is stocked); activity → instructor;
 *  user → users.campus_id; reservation → the reserving user.
 */
class CampusAccess
{
    public static function mainCampusId(): ?int
    {
        // Looked up once per request.
        return once(fn () => Campus::where('campus_name', 'like', '%Carmen%')->orderBy('id')->value('id')
            ?? Campus::orderBy('id')->value('id'));
    }

    /** The campus an admin works for (an admin with no campus counts as the main campus's). */
    public static function adminCampusId(User $admin): ?int
    {
        return $admin->campus_id ?? self::mainCampusId();
    }

    public static function isMainAdmin(User $user): bool
    {
        return $user->role === 'admin' && self::adminCampusId($user) === self::mainCampusId();
    }

    /** The campus's active admin, if it has one. */
    public static function adminOf(?int $campusId): ?User
    {
        if (!$campusId) {
            return null;
        }

        return User::where('role', 'admin')->where('status', 'active')
            ->where(fn ($q) => $q->where('campus_id', (int) $campusId)
                ->when($campusId === self::mainCampusId(), fn ($q) => $q->orWhereNull('campus_id')))
            ->first();
    }

    /**
     * Borrowing is open at the main campus, and at another campus once it
     * has its own admin to approve requests.
     */
    public static function borrowingOpenAt(?int $campusId): bool
    {
        return $campusId !== null && ($campusId === self::mainCampusId() || self::adminOf($campusId) !== null);
    }

    /** Who handles admin work for a campus: its admin, else the main campus's admin. */
    public static function adminsFor(?int $campusId)
    {
        $admin = self::adminOf($campusId) ?? self::adminOf(self::mainCampusId());

        return collect($admin ? [$admin] : []);
    }

    /** Campuses (other than main) that have no active admin yet. */
    public static function campusesWithoutAdmin(): array
    {
        return Campus::pluck('id')
            ->reject(fn ($id) => $id === self::mainCampusId() || self::adminOf($id))
            ->values()->all();
    }

    /**
     * Each campus keeps all its equipment in one room, its "Lab Storage
     * Room" — created if a campus doesn't have one yet.
     */
    public static function storageRoomId(int $campusId): int
    {
        $existing = Location::where('campus_id', (int) $campusId)->where('location_name', 'like', 'Lab Storage Room%')->orderBy('id')->value('id');
        if ($existing) {
            return $existing;
        }

        $campusName = (string) Campus::whereKey($campusId)->value('campus_name');
        $short = trim(preg_split('/[–—-]/u', $campusName)[1] ?? $campusName);

        return Location::create(['campus_id' => $campusId, 'location_name' => "Lab Storage Room – {$short}"])->id;
    }

    // ── Query scopes ────────────────────────────────────────────────────

    public static function inventory(Builder|Relation $q, ?int $campusId): Builder|Relation
    {
        return $q->where('campus_id', (int) $campusId);
    }

    public static function borrowings(Builder|Relation $q, ?int $campusId): Builder|Relation
    {
        // By where its items are stocked (a borrowing with no items, which
        // shouldn't happen, falls back to the borrower's campus).
        return $q->where(fn ($q) => $q->whereHas('borrowingEquipment.inventory', fn ($i) => $i->where('campus_id', (int) $campusId))
            ->orWhere(fn ($q) => $q->whereDoesntHave('borrowingEquipment')
                ->whereHas('user', fn ($u) => $u->withTrashed()->where('campus_id', (int) $campusId))));
    }

    public static function damageReports(Builder|Relation $q, ?int $campusId): Builder|Relation
    {
        return $q->where(fn ($q) => $q
            ->whereHas('inventory', fn ($i) => $i->where('campus_id', (int) $campusId))
            ->orWhere(fn ($q) => $q->whereNull('equipment_inventory_id')
                ->whereHas('borrowing.borrowingEquipment.inventory', fn ($i) => $i->where('campus_id', (int) $campusId))));
    }

    public static function maintenance(Builder|Relation $q, ?int $campusId): Builder|Relation
    {
        return $q->where(fn ($q) => $q->where('campus_id', (int) $campusId)
            ->orWhere(fn ($q) => $q->whereNull('campus_id')
                ->whereHas('equipment.inventory', fn ($i) => $i->where('campus_id', (int) $campusId))));
    }

    public static function activities(Builder|Relation $q, ?int $campusId): Builder|Relation
    {
        return $q->whereHas('instructor', fn ($u) => $u->withTrashed()->where('campus_id', (int) $campusId));
    }

    /** Equipment stocked at the campus (the main admin also sees unstocked catalog entries). */
    public static function equipment(Builder|Relation $q, ?int $campusId, bool $main = false): Builder|Relation
    {
        return $q->where(fn ($q) => $q->whereHas('inventory', fn ($i) => $i->where('campus_id', (int) $campusId))
            ->when($main, fn ($q) => $q->orWhereDoesntHave('inventory')));
    }

    /**
     * Users an admin may see and manage: their own campus's users. The main
     * admin also sees other campuses' Admin/Lab Staff accounts (to set them
     * up), pending faculty of campuses without an admin, and sign-ups that
     * haven't picked a campus yet.
     */
    public static function users(Builder|Relation $q, User $admin): Builder|Relation
    {
        $campusId = self::adminCampusId($admin);
        $main = self::isMainAdmin($admin);
        $noAdmin = $main ? self::campusesWithoutAdmin() : [];

        return $q->where(fn ($q) => $q->where('campus_id', (int) $campusId)
            ->when($main, fn ($q) => $q
                ->orWhereNull('campus_id')
                ->orWhereIn('role', ['admin', 'lab_staff'])
                ->orWhere(fn ($q) => $q->where('role', 'faculty')->where('status', 'pending')->whereIn('campus_id', $noAdmin))));
    }

    public static function auditLogs(Builder|Relation $q, User $admin): Builder|Relation
    {
        return $q->whereHas('user', fn ($u) => $u->withTrashed()->where('campus_id', self::adminCampusId($admin))
            ->when(self::isMainAdmin($admin), fn ($u) => $u->orWhereNull('campus_id')));
    }

    // ── Single-record check (used by the admin route guard) ─────────────

    public static function adminCanAccess(User $admin, mixed $model): bool
    {
        if (!$model instanceof Model) {
            return true;
        }

        $campusId = self::adminCampusId($admin);
        $main = self::isMainAdmin($admin);
        $in = fn (Builder $q) => $q->whereKey($model->getKey())->exists();

        return match (true) {
            $model instanceof Borrowing => $in(self::borrowings(Borrowing::query(), $campusId)),
            $model instanceof DamageReport => $in(self::damageReports(DamageReport::query(), $campusId)),
            $model instanceof EquipmentInventory => (int) $model->campus_id === $campusId,
            $model instanceof Equipment => $in(self::equipment(Equipment::withTrashed(), $campusId, $main)),
            $model instanceof MaintenanceLog => $in(self::maintenance(MaintenanceLog::query(), $campusId)),
            $model instanceof Activity => $in(self::activities(Activity::query(), $campusId)),
            $model instanceof User => $in(self::users(User::withTrashed(), $admin)),
            $model instanceof Reservation => self::users(User::withTrashed(), $admin)->whereKey($model->user_id)->exists(),
            $model instanceof UserNotification => $model->user_id === $admin->id
                || self::users(User::withTrashed(), $admin)->whereKey($model->user_id)->exists(),
            $model instanceof StockAdjustment => (int) $model->inventory?->campus_id === $campusId,
            // System-wide setup belongs to the main campus.
            $model instanceof Campus, $model instanceof Location => $main,
            default => $main,
        };
    }
}
