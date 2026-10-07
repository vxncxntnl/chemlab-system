<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\CampusController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\EquipmentController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\BorrowingController as AdminBorrowingController;
use App\Http\Controllers\Admin\DamageReportController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\MasterfileController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\ReservationController;
use App\Http\Controllers\Admin\MaintenanceController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\LabInventoryController;
use App\Http\Controllers\Admin\StockAdjustmentController;
use App\Http\Controllers\Admin\ActivityController as AdminActivityController;
use App\Http\Controllers\Faculty\ActivityController as FacultyActivityController;
use App\Http\Controllers\Student\ActivityController as StudentActivityController;
use App\Http\Controllers\Lab\DashboardController as LabDashboardController;
use App\Http\Controllers\Lab\BorrowingController as LabBorrowingController;
use App\Http\Controllers\Lab\DamageReportController as LabDamageReportController;
use App\Http\Controllers\Lab\EquipmentController as LabEquipmentController;
use App\Http\Controllers\Lab\LabInventoryController as LabLabInventoryController;
use App\Http\Controllers\Lab\MaintenanceController as LabMaintenanceController;
use App\Http\Controllers\Faculty\DashboardController as FacultyDashboardController;
use App\Http\Controllers\Faculty\BorrowingController as FacultyBorrowingController;
use App\Http\Controllers\Faculty\StudentController as FacultyStudentController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Student\BorrowingController as StudentBorrowingController;
use App\Http\Controllers\Api\NotificationController as ApiNotificationController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Campus;

// Daily overdue reminders, triggered by an external scheduler (no cron on the host)
Route::get('/cron/overdue-reminders/{token}', [\App\Http\Controllers\CronController::class, 'overdueReminders'])
    ->middleware('throttle:10,1')->name('cron.overdue-reminders');

// Google OAuth routes
Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

// Equipment QR scan — public entry point shared by faculty in class group chats
Route::get('/qr/{token}', [\App\Http\Controllers\QrScanController::class, 'show'])->name('qr.scan');

// Complete profile (for new Google sign-in users)
//
// Requires 'verified' too, not just 'auth': Google sign-ins are already
// marked verified at creation (OAuth itself proves ownership), so this
// changes nothing for them. It matters for the password-registration path —
// without it, someone who registered using another person's real email
// could still fill in course/campus/role here before ever hitting a
// verification wall, even though they can never click the link (it goes to
// the real owner's inbox). Requiring verification first closes that window
// completely: no profile data can be set until ownership is proven.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/complete-profile', [\App\Http\Controllers\Auth\CompleteProfileController::class, 'show'])->name('profile.complete');
    Route::post('/complete-profile', [\App\Http\Controllers\Auth\CompleteProfileController::class, 'store'])->name('profile.complete.store');
});

// Homepage redirects directly to login
Route::get('/', function () {
    return redirect()->route('login');
});

// Redirect to role-based dashboard
Route::get('/dashboard', function () {
    $user = auth()->user();
    return match($user->role) {
        'admin' => redirect()->route('admin.dashboard'),
        'lab_staff' => redirect()->route('lab.dashboard'),
        'faculty' => redirect()->route('faculty.dashboard'),
        'student' => redirect()->route('student.dashboard'),
        default => redirect()->route('login'),
    };
})->middleware(['auth', 'verified'])->name('dashboard');

// Universal borrowing redirect — used by notification bell links
Route::get('/borrowings/{id}', function ($id) {
    $user = auth()->user();
    return match($user->role) {
        'faculty'   => redirect("/faculty/borrowings/{$id}"),
        'lab_staff' => redirect("/lab/borrowings/{$id}"),
        'admin'     => redirect("/admin/borrowings/{$id}"),
        default     => redirect("/student/borrowings/{$id}"),
    };
})->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Lab Staff Routes
Route::middleware(['auth', 'role:lab_staff', 'throttle:300,1'])->prefix('lab')->name('lab.')->group(function () {
    Route::get('/dashboard', [LabDashboardController::class, 'index'])->name('dashboard');
    Route::get('/equipment', [LabEquipmentController::class, 'index'])->name('equipment');

    // Borrowings within assigned campus — lab staff release and process returns
    // only; approving/rejecting whether a request should be granted is Admin's call.
    Route::get('/borrowings', [LabBorrowingController::class, 'index'])->name('borrowings.index');
    Route::get('/borrowings/create', [LabBorrowingController::class, 'create'])->name('borrowings.create');
    Route::post('/borrowings', [LabBorrowingController::class, 'store'])->name('borrowings.store');
    Route::get('/borrowings/{borrowing}', [LabBorrowingController::class, 'show'])->name('borrowings.show');
    Route::post('/borrowings/{borrowing}/release', [LabBorrowingController::class, 'release'])->name('borrowings.release');
    Route::post('/borrowings/{borrowing}/return', [LabBorrowingController::class, 'return'])->name('borrowings.return');
    Route::post('/borrowings/{borrowing}/damage', [LabBorrowingController::class, 'recordDamage'])->name('borrowings.record-damage');
    Route::post('/borrowings/{borrowing}/no-damage', [LabBorrowingController::class, 'noDamage'])->name('borrowings.no-damage');

    // Activity history — this lab staff member's own actions
    Route::get('/history', [LabBorrowingController::class, 'history'])->name('history');

    // Damage reports within assigned campus
    Route::get('/damage-reports', [LabDamageReportController::class, 'index'])->name('damage-reports.index');
    Route::get('/damage-reports/{damageReport}', [LabDamageReportController::class, 'show'])->name('damage-reports.show');
    Route::get('/damage-reports/{damageReport}/print', [LabDamageReportController::class, 'print'])->name('damage-reports.print');
    Route::get('/damage-reports/{damageReport}/photo', [LabDamageReportController::class, 'photo'])->name('damage-reports.photo');
    Route::post('/damage-reports/{damageReport}/assess', [LabDamageReportController::class, 'assess'])->name('damage-reports.assess');
    Route::post('/damage-reports/{damageReport}/resolve', [LabDamageReportController::class, 'resolve'])->name('damage-reports.resolve');

    // Maintenance logs – create and view only
    Route::get('/maintenance', [LabMaintenanceController::class, 'index'])->name('maintenance.index');
    Route::get('/maintenance/create', [LabMaintenanceController::class, 'create'])->name('maintenance.create');
    Route::post('/maintenance', [LabMaintenanceController::class, 'store'])->name('maintenance.store');
    Route::get('/maintenance/{maintenance}', [LabMaintenanceController::class, 'show'])->name('maintenance.show');

    // Lab Inventory
    Route::get('/lab-inventory', [LabLabInventoryController::class, 'index'])->name('lab-inventory.index');
    Route::put('/lab-inventory/{labInventory}', [LabLabInventoryController::class, 'update'])->name('lab-inventory.update');
});

// Admin Routes
Route::middleware(['auth', 'role:admin', 'throttle:300,1'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Masterfile
    Route::get('/masterfile', [MasterfileController::class, 'index'])->name('masterfile.index');

    // Users
    Route::resource('users', UserController::class);
    Route::post('/users/{user}/approve', [UserController::class, 'approve'])->name('users.approve');
    Route::post('/users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');

    // Campuses
    Route::resource('campuses', CampusController::class);

    // Locations
    Route::resource('locations', LocationController::class);

    // Categories
    Route::resource('categories', CategoryController::class);

    // Equipment
    Route::get('/equipment-report', [EquipmentController::class, 'report'])->name('equipment.report');
    Route::resource('equipment', EquipmentController::class);

    // Inventory
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::put('/inventory/{inventory}', [InventoryController::class, 'update'])->name('inventory.update');
    Route::post('/inventory/{inventory}/adjust', [StockAdjustmentController::class, 'store'])->name('inventory.adjust');

    // Borrowings
    Route::get('/borrowings', [AdminBorrowingController::class, 'index'])->name('borrowings.index');
    // create/store registered before the {borrowing} wildcard below, or
    // "create" would be parsed as a borrowing ID instead of reaching here.
    Route::get('/borrowings/create', [AdminBorrowingController::class, 'create'])->name('borrowings.create');
    Route::post('/borrowings', [AdminBorrowingController::class, 'store'])->name('borrowings.store');
    Route::get('/borrowings/{borrowing}', [AdminBorrowingController::class, 'show'])->name('borrowings.show');
    Route::post('/borrowings/{borrowing}/approve', [AdminBorrowingController::class, 'approve'])->name('borrowings.approve');
    Route::post('/borrowings/{borrowing}/reject', [AdminBorrowingController::class, 'reject'])->name('borrowings.reject');
    Route::post('/borrowings/{borrowing}/release', [AdminBorrowingController::class, 'release'])->name('borrowings.release');
    Route::post('/borrowings/{borrowing}/return', [AdminBorrowingController::class, 'return'])->name('borrowings.return');
    Route::post('/borrowings/{borrowing}/damage', [AdminBorrowingController::class, 'recordDamage'])->name('borrowings.record-damage');
    Route::post('/borrowings/{borrowing}/no-damage', [AdminBorrowingController::class, 'noDamage'])->name('borrowings.no-damage');

    // Damage Reports
    Route::get('/damage-reports', [DamageReportController::class, 'index'])->name('damage-reports.index');
    Route::get('/damage-reports/{damageReport}', [DamageReportController::class, 'show'])->name('damage-reports.show');
    Route::get('/damage-reports/{damageReport}/print', [DamageReportController::class, 'print'])->name('damage-reports.print');
    Route::get('/damage-reports/{damageReport}/photo', [DamageReportController::class, 'photo'])->name('damage-reports.photo');
    Route::post('/damage-reports/{damageReport}/assess', [DamageReportController::class, 'assess'])->name('damage-reports.assess');
    Route::post('/damage-reports/{damageReport}/resolve', [DamageReportController::class, 'resolve'])->name('damage-reports.resolve');

    // Reports
    Route::get('/reports/low-stock', [ReportController::class, 'lowStock'])->name('reports.low-stock');
    Route::get('/reports/most-borrowed', [ReportController::class, 'mostBorrowed'])->name('reports.most-borrowed');
    Route::get('/reports/monthly', [ReportController::class, 'monthlyReport'])->name('reports.monthly');
    Route::get('/reports/active-borrowers', [ReportController::class, 'activeBorrowers'])->name('reports.active-borrowers');
    Route::get('/reports/unreturned-items', [ReportController::class, 'unreturnedItems'])->name('reports.unreturned-items');
    Route::get('/reports/audit-logs', [ReportController::class, 'auditLogs'])->name('reports.audit-logs');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/create', [NotificationController::class, 'create'])->name('notifications.create');
    Route::post('/notifications', [NotificationController::class, 'store'])->name('notifications.store');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    // Maintenance
    Route::resource('maintenance', MaintenanceController::class);

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/test-email', [SettingsController::class, 'sendTestEmail'])->middleware('throttle:5,1')->name('settings.test-email');

    // Reservations
    Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
    Route::get('/reservations/{reservation}', [ReservationController::class, 'show'])->name('reservations.show');
    Route::post('/reservations/{reservation}/approve', [ReservationController::class, 'approve'])->name('reservations.approve');
    Route::post('/reservations/{reservation}/reject', [ReservationController::class, 'reject'])->name('reservations.reject');
    Route::delete('/reservations/{reservation}', [ReservationController::class, 'destroy'])->name('reservations.destroy');

    // Lab Inventory
    Route::get('/lab-inventory/dashboard', [LabInventoryController::class, 'dashboard'])->name('lab-inventory.dashboard');
    Route::get('/lab-inventory/{labInventory}/transfer', [LabInventoryController::class, 'transfer'])->name('lab-inventory.transfer');
    Route::post('/lab-inventory/{labInventory}/transfer', [LabInventoryController::class, 'transfer'])->name('lab-inventory.transfer.store');
    Route::resource('lab-inventory', LabInventoryController::class);

    // Activities — Admin can view/edit/delete but not create; only Faculty
    // originates a new lab activity.
    Route::post('/activities/{activity}/cancel', [AdminActivityController::class, 'cancel'])->name('activities.cancel');
    Route::resource('activities', AdminActivityController::class)->except(['create', 'store']);
});

// Faculty Routes
Route::middleware(['auth', 'role:faculty', 'throttle:300,1'])->prefix('faculty')->name('faculty.')->group(function () {
    Route::get('/dashboard', [FacultyDashboardController::class, 'index'])->name('dashboard');
    Route::resource('activities', FacultyActivityController::class);
    Route::post('/activities/{activity}/cancel', [FacultyActivityController::class, 'cancel'])->name('activities.cancel');
    Route::get('/equipment', [FacultyBorrowingController::class, 'equipment'])->name('equipment');
    Route::get('/borrowings', [FacultyBorrowingController::class, 'index'])->name('borrowings.index');
    Route::get('/borrowings/create', [FacultyBorrowingController::class, 'create'])->name('borrowings.create');
    Route::post('/borrowings', [FacultyBorrowingController::class, 'store'])->middleware('throttle:5,1,faculty-borrow-store')->name('borrowings.store');
    Route::get('/borrowings/{borrowing}', [FacultyBorrowingController::class, 'show'])->name('borrowings.show');
    Route::post('/borrowings/{borrowing}/cancel', [FacultyBorrowingController::class, 'cancel'])->name('borrowings.cancel');
    Route::post('/borrowings/{borrowing}/approve', [FacultyBorrowingController::class, 'approve'])->name('borrowings.approve');
    Route::post('/borrowings/{borrowing}/reject', [FacultyBorrowingController::class, 'reject'])->name('borrowings.reject');
    Route::post('/borrowings/{borrowing}/report-damage', [FacultyBorrowingController::class, 'reportDamage'])->name('borrowings.report-damage');
    Route::get('/history', [FacultyBorrowingController::class, 'history'])->name('history');
    // Faculty can add student accounts (the student sets their own password).
    Route::get('/students', [FacultyStudentController::class, 'index'])->name('students.index');
    Route::get('/students/create', [FacultyStudentController::class, 'create'])->name('students.create');
    Route::post('/students', [FacultyStudentController::class, 'store'])->middleware('throttle:20,1')->name('students.store');
});

// Student Routes
Route::middleware(['auth', 'role:student', 'profile.complete', 'throttle:300,1'])->prefix('student')->name('student.')->group(function () {
    Route::get('/dashboard', [StudentDashboardController::class, 'index'])->name('dashboard');
    Route::get('/activities', [StudentActivityController::class, 'index'])->name('activities.index');
    Route::get('/activities/{activity}', [StudentActivityController::class, 'show'])->name('activities.show');
    Route::get('/equipment', [StudentBorrowingController::class, 'equipment'])->name('equipment');
    Route::get('/borrowings', [StudentBorrowingController::class, 'index'])->name('borrowings.index');
    Route::get('/borrowings/create', [StudentBorrowingController::class, 'create'])->name('borrowings.create');
    Route::post('/borrowings', [StudentBorrowingController::class, 'store'])->middleware('throttle:5,1,student-borrow-store')->name('borrowings.store');
    Route::get('/borrowings/{borrowing}', [StudentBorrowingController::class, 'show'])->name('borrowings.show');
    Route::post('/borrowings/{borrowing}/cancel', [StudentBorrowingController::class, 'cancel'])->name('borrowings.cancel');
    Route::post('/borrowings/{borrowing}/report-damage', [StudentBorrowingController::class, 'reportDamage'])->middleware('throttle:10,1')->name('borrowings.report-damage');
    Route::get('/history', [StudentBorrowingController::class, 'history'])->name('history');
});

// Notifications API (all authenticated roles)
Route::middleware(['auth', 'throttle:60,1'])->prefix('api')->name('api.')->group(function () {
    Route::get('/notifications', [ApiNotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [ApiNotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [ApiNotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
});

require __DIR__.'/auth.php';