<?php

namespace App\Http\Middleware;

use App\Models\Equipment;
use App\Support\CampusAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs on every admin route. Each campus admin may only open or change
 * records of their own campus — checked here for every record in the URL
 * (borrowing, damage report, equipment, user, ...), so a changed ID in the
 * address bar or a hand-made request gets a 403. Lists and reports are
 * filtered in the controllers themselves (see CampusAccess).
 */
class AdminCampusGuard
{
    /** System-wide setup: main campus (Carmen) admin only. */
    private const MAIN_ONLY = [
        'admin.masterfile.*',
        'admin.campuses.*',
        'admin.categories.*',
        'admin.locations.*',
        'admin.settings.*',
        'admin.lab-inventory.*',
    ];

    /** Changing the shared catalog entry itself (affects every campus stocking it). */
    private const CATALOG_CHANGES = [
        'admin.equipment.edit',
        'admin.equipment.update',
        'admin.equipment.destroy',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user();

        if (!$admin || $admin->role !== 'admin') {
            return $next($request);
        }

        if ($request->routeIs(...self::MAIN_ONLY)) {
            abort_unless(CampusAccess::isMainAdmin($admin), 403, 'Only the main campus admin can manage this.');
        }

        foreach ($request->route()?->parameters() ?? [] as $model) {
            abort_unless(CampusAccess::adminCanAccess($admin, $model), 403, 'This record belongs to another campus.');
        }

        $equipment = $request->route('equipment');
        if ($equipment instanceof Equipment && $request->routeIs(...self::CATALOG_CHANGES) && !CampusAccess::isMainAdmin($admin)) {
            $stockedElsewhere = $equipment->inventory()->where('campus_id', '!=', CampusAccess::adminCampusId($admin))->exists();
            abort_if($stockedElsewhere, 403, 'This equipment is also stocked at another campus — only the main campus admin can change it.');
        }

        return $next($request);
    }
}
