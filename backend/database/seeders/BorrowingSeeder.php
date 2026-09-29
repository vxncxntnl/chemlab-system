<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Borrowing;
use App\Models\BorrowingEquipment;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class BorrowingSeeder extends Seeder
{
    public function run(): void
    {
        $admin    = User::where('role', 'admin')->first();
        $faculty  = User::where('role', 'faculty')->first();          // Dr. Maria Santos
        $faculty2 = User::where('role', 'faculty')->skip(1)->first(); // Prof. Jose Reyes
        $labStaff = User::where('role', 'lab_staff')->first();        // Liza Macaraeg (Carmen)
        $students = User::where('role', 'student')->get();

        // Helper: get inventory for an equipment at Carmen campus
        $inv = fn(string $name) => EquipmentInventory::whereHas('equipment', fn($q) => $q->where('name', 'like', "%{$name}%"))
                                     ->whereHas('campus', fn($q) => $q->where('campus_name', 'like', '%Carmen%'))
                                     ->first();

        // ═════════════════════════════════════════════════════════════════════════
        // SCENARIO A – PENDING (Student just submitted → waiting for Faculty)
        // Demonstrates: PENDING_FACULTY status on Student & Faculty dashboards
        // ═════════════════════════════════════════════════════════════════════════
        $beakerInv = $inv('Beaker');
        $goggleInv = $inv('Safety Goggles');

        $borrowA = Borrowing::create([
            'user_id'     => $students[0]->id, // Juan dela Cruz
            'faculty_id'  => $faculty->id,
            'borrow_date' => Carbon::today()->addDays(1),
            'return_date' => Carbon::today()->addDays(3),
            'purpose'     => 'General Chemistry Lab – Acid-Base Titration Experiment (CHEM 101)',
            'subject'     => 'CHEM 101 – General Chemistry',
            'status'      => 'pending_faculty',
            'daily_fine_rate' => 10.00,
        ]);
        $borrowA->claim_code = 'CHEM-REQ-' . now()->format('Y') . '-' . str_pad($borrowA->id, 5, '0', STR_PAD_LEFT);
        $borrowA->save();

        // 2 Beakers + 2 Safety Goggles
        BorrowingEquipment::create([
            'borrowing_id'      => $borrowA->id,
            'equipment_id'      => $beakerInv->equipment_id,
            'inventory_id'      => $beakerInv->id,
            'quantity'          => 2,
            'condition_on_borrow' => 'good',
        ]);
        BorrowingEquipment::create([
            'borrowing_id'      => $borrowA->id,
            'equipment_id'      => $goggleInv->equipment_id,
            'inventory_id'      => $goggleInv->id,
            'quantity'          => 2,
            'condition_on_borrow' => 'good',
        ]);

        AuditLog::log($students[0]->id, 'Created Borrow Request', 'borrowings', $borrowA->id);

        // ═════════════════════════════════════════════════════════════════════════
        // SCENARIO B – FACULTY APPROVED (pending_admin)
        // Faculty has approved → now waiting for Admin's purchase/approval decision
        // ═════════════════════════════════════════════════════════════════════════
        $tubeInv   = $inv('Test Tube');
        $cylInv    = $inv('Graduated Cylinder');
        $burnerInv = $inv('Bunsen Burner');

        $borrowB = Borrowing::create([
            'user_id'              => $students[1]->id, // Maria Clara Reyes
            'faculty_id'           => $faculty->id,
            'faculty_approved_by'  => $faculty->id,
            'faculty_approved_at'  => Carbon::now()->subHours(2),
            'borrow_date'          => Carbon::today()->addDays(2),
            'return_date'          => Carbon::today()->addDays(4),
            'purpose'              => 'Organic Chemistry Lab – Distillation and Filtration Techniques (CHEM 203)',
            'subject'              => 'CHEM 203 – Organic Chemistry',
            'status'               => 'pending_admin',
            'daily_fine_rate'      => 10.00,
        ]);
        $borrowB->claim_code = 'CHEM-REQ-' . now()->format('Y') . '-' . str_pad($borrowB->id, 5, '0', STR_PAD_LEFT);
        $borrowB->save();

        BorrowingEquipment::create([
            'borrowing_id'      => $borrowB->id,
            'equipment_id'      => $tubeInv->equipment_id,
            'inventory_id'      => $tubeInv->id,
            'quantity'          => 5,
            'condition_on_borrow' => 'good',
        ]);
        BorrowingEquipment::create([
            'borrowing_id'      => $borrowB->id,
            'equipment_id'      => $cylInv->equipment_id,
            'inventory_id'      => $cylInv->id,
            'quantity'          => 2,
            'condition_on_borrow' => 'good',
        ]);
        BorrowingEquipment::create([
            'borrowing_id'      => $borrowB->id,
            'equipment_id'      => $burnerInv->equipment_id,
            'inventory_id'      => $burnerInv->id,
            'quantity'          => 1,
            'condition_on_borrow' => 'good',
        ]);

        AuditLog::log($faculty->id, 'Faculty Approved Borrow Request', 'borrowings', $borrowB->id);

        // ═════════════════════════════════════════════════════════════════════════
        // SCENARIO C – AWAITING ADMIN DECISION (pending_admin)
        // Faculty approved → now waiting for Admin to approve or reject the request
        // ═════════════════════════════════════════════════════════════════════════
        $phMeterInv = $inv('pH Meter');
        $beakerInv2 = EquipmentInventory::whereHas('equipment', fn($q) => $q->where('name', 'like', '%Beaker%'))
            ->whereHas('campus', fn($q) => $q->where('campus_name', 'like', '%Puerto%'))
            ->first();

        $borrowC = Borrowing::create([
            'user_id'            => $students[3]->id, // Anna Patricia Gomez (Puerto)
            'faculty_id'         => $faculty2->id,
            'faculty_approved_by' => $faculty2->id,
            'faculty_approved_at' => Carbon::now()->subDay(),
            'borrow_date'        => Carbon::today()->addDays(1),
            'return_date'        => Carbon::today()->addDays(3),
            'purpose'            => 'Analytical Chemistry Lab – pH Measurement and Buffer Solutions (CHEM 301)',
            'subject'            => 'CHEM 301 – Analytical Chemistry',
            'status'             => 'pending_admin',
            'daily_fine_rate'    => 10.00,
        ]);
        $borrowC->claim_code = 'CHEM-REQ-' . now()->format('Y') . '-' . str_pad($borrowC->id, 5, '0', STR_PAD_LEFT);
        $borrowC->save();

        BorrowingEquipment::create([
            'borrowing_id'      => $borrowC->id,
            'equipment_id'      => $phMeterInv->equipment_id,
            'inventory_id'      => $phMeterInv->id,
            'quantity'          => 1,
            'condition_on_borrow' => 'good',
        ]);
        if ($beakerInv2) {
            BorrowingEquipment::create([
                'borrowing_id'      => $borrowC->id,
                'equipment_id'      => $beakerInv2->equipment_id,
                'inventory_id'      => $beakerInv2->id,
                'quantity'          => 3,
                'condition_on_borrow' => 'good',
            ]);
        }

        AuditLog::log($faculty2->id, 'Faculty Approved Borrow Request', 'borrowings', $borrowC->id);

        // ═════════════════════════════════════════════════════════════════════════
        // SCENARIO D – FULLY APPROVED (ready for release by Lab Staff)
        // Admin approved → Lab Staff sees it as "ready to release"
        // Availability DECREMENTED – shows real-time count change
        // Before: 11/25 beakers → After admin approval → inventory decremented
        // ═════════════════════════════════════════════════════════════════════════
        $beakerInventoryCarmen = EquipmentInventory::whereHas('equipment', fn($q) => $q->where('name', 'like', '%Beaker%'))
            ->whereHas('campus', fn($q) => $q->where('campus_name', 'like', '%Carmen%'))
            ->first();

        $borrowD = Borrowing::create([
            'user_id'            => $students[2]->id, // Carlos Miguel Buenaflor
            'faculty_id'         => $faculty->id,
            'faculty_approved_by' => $faculty->id,
            'faculty_approved_at' => Carbon::now()->subDays(2),
            'admin_approved_by'  => $admin->id,
            'admin_approved_at'  => Carbon::now()->subHours(4),
            'approved_by'        => $admin->id,
            'borrow_date'        => Carbon::today(),
            'return_date'        => Carbon::today()->addDays(2),
            'purpose'            => 'CHEM Lab – Neutralization Reaction and Salt Formation (CHEM 101)',
            'subject'            => 'CHEM 101 – General Chemistry',
            'status'             => 'approved',
            'daily_fine_rate'    => 10.00,
        ]);
        $borrowD->claim_code = 'CHEM-REQ-' . now()->format('Y') . '-' . str_pad($borrowD->id, 5, '0', STR_PAD_LEFT);
        $borrowD->save();

        // Borrow 2 Beakers (Carmen) – decrement availability: 11 → 9
        BorrowingEquipment::create([
            'borrowing_id'      => $borrowD->id,
            'equipment_id'      => $beakerInventoryCarmen->equipment_id,
            'inventory_id'      => $beakerInventoryCarmen->id,
            'quantity'          => 2,
            'condition_on_borrow' => 'good',
        ]);
        // Actually decrement the inventory to reflect admin approval
        $beakerInventoryCarmen->decrement('available_quantity', 2);

        AuditLog::log($admin->id, 'Admin Approved Borrow Request (Final)', 'borrowings', $borrowD->id);

        // ═════════════════════════════════════════════════════════════════════════
        // SCENARIO E – CURRENTLY BORROWED (Released by Lab Staff)
        // Lab Staff released equipment → status = borrowed
        // ═════════════════════════════════════════════════════════════════════════
        $microscopeInv = EquipmentInventory::whereHas('equipment', fn($q) => $q->where('name', 'like', '%Microscope%'))
            ->whereHas('campus', fn($q) => $q->where('campus_name', 'like', '%Carmen%'))
            ->first();

        $borrowE = Borrowing::create([
            'user_id'            => $students[1]->id, // Maria Clara Reyes
            'faculty_id'         => $faculty->id,
            'faculty_approved_by' => $faculty->id,
            'faculty_approved_at' => Carbon::now()->subDays(5),
            'admin_approved_by'  => $admin->id,
            'admin_approved_at'  => Carbon::now()->subDays(4),
            'approved_by'        => $admin->id,
            'borrow_date'        => Carbon::now()->subDays(3),
            'return_date'        => Carbon::today()->addDays(2),
            'purpose'            => 'Microbiology Lab – Microscopic Examination of Prepared Slides (BIO 202)',
            'subject'            => 'BIO 202 – Microbiology',
            'status'             => 'borrowed',
            'daily_fine_rate'    => 10.00,
        ]);
        $borrowE->claim_code = 'CHEM-REQ-' . now()->format('Y') . '-' . str_pad($borrowE->id, 5, '0', STR_PAD_LEFT);
        $borrowE->save();

        BorrowingEquipment::create([
            'borrowing_id'      => $borrowE->id,
            'equipment_id'      => $microscopeInv->equipment_id,
            'inventory_id'      => $microscopeInv->id,
            'quantity'          => 2,
            'condition_on_borrow' => 'good',
        ]);
        $microscopeInv->decrement('available_quantity', 2);

        AuditLog::log($labStaff->id, 'Equipment Released to Borrower', 'borrowings', $borrowE->id);

        // ═════════════════════════════════════════════════════════════════════════
        // SCENARIO F – OVERDUE (past return date, not yet returned)
        // Shows overdue status + fine calculation
        // ═════════════════════════════════════════════════════════════════════════
        $thermometerInv = $inv('Thermometer');

        $borrowF = Borrowing::create([
            'user_id'            => $students[4]->id, // Pedro Alvarez (Iligan)
            'faculty_id'         => $faculty->id,
            'faculty_approved_by' => $faculty->id,
            'faculty_approved_at' => Carbon::now()->subDays(12),
            'admin_approved_by'  => $admin->id,
            'admin_approved_at'  => Carbon::now()->subDays(11),
            'approved_by'        => $admin->id,
            'borrow_date'        => Carbon::now()->subDays(10),
            'return_date'        => Carbon::now()->subDays(3),   // 3 days overdue
            'purpose'            => 'Physical Chemistry Lab – Boiling Point Determination (CHEM 201)',
            'subject'            => 'CHEM 201 – Physical Chemistry',
            'status'             => 'overdue',
            'daily_fine_rate'    => 10.00,
            'days_late'          => 3,
            'total_fine'         => 30.00,
            'overdue_flag'       => true,
        ]);
        $borrowF->claim_code = 'CHEM-REQ-' . now()->format('Y') . '-' . str_pad($borrowF->id, 5, '0', STR_PAD_LEFT);
        $borrowF->save();

        if ($thermometerInv) {
            BorrowingEquipment::create([
                'borrowing_id'      => $borrowF->id,
                'equipment_id'      => $thermometerInv->equipment_id,
                'inventory_id'      => $thermometerInv->id,
                'quantity'          => 2,
                'condition_on_borrow' => 'good',
            ]);
            $thermometerInv->decrement('available_quantity', 2);
        }

        AuditLog::log($admin->id, 'Borrowing Marked Overdue', 'borrowings', $borrowF->id);

        // ═════════════════════════════════════════════════════════════════════════
        // SCENARIO G – COMPLETED / RETURNED
        // Full cycle: student returned, staff processed, status = returned
        // ═════════════════════════════════════════════════════════════════════════
        $petriDishInv = EquipmentInventory::whereHas('equipment', fn($q) => $q->where('name', 'like', '%Petri%'))
            ->whereHas('campus', fn($q) => $q->where('campus_name', 'like', '%Carmen%'))
            ->first();

        $borrowG = Borrowing::create([
            'user_id'            => $students[0]->id, // Juan dela Cruz
            'faculty_id'         => $faculty->id,
            'faculty_approved_by' => $faculty->id,
            'faculty_approved_at' => Carbon::now()->subDays(10),
            'admin_approved_by'  => $admin->id,
            'admin_approved_at'  => Carbon::now()->subDays(9),
            'approved_by'        => $admin->id,
            'borrow_date'        => Carbon::now()->subDays(7),
            'return_date'        => Carbon::now()->subDays(3),
            'actual_return_date' => Carbon::now()->subDays(4), // returned EARLY
            'purpose'            => 'Microbiology Lab – Bacterial Culture and Colony Observation (BIO 101)',
            'subject'            => 'BIO 101 – General Biology',
            'status'             => 'returned',
            'daily_fine_rate'    => 10.00,
            'days_late'          => 0,
            'total_fine'         => 0,
        ]);
        $borrowG->claim_code = 'CHEM-REQ-' . now()->format('Y') . '-' . str_pad($borrowG->id, 5, '0', STR_PAD_LEFT);
        $borrowG->save();

        if ($petriDishInv) {
            BorrowingEquipment::create([
                'borrowing_id'        => $borrowG->id,
                'equipment_id'        => $petriDishInv->equipment_id,
                'inventory_id'        => $petriDishInv->id,
                'quantity'            => 3,
                'condition_on_borrow' => 'good',
                'condition_on_return' => 'good',
            ]);
        }

        AuditLog::log($labStaff->id, 'Equipment Returned – Condition: Good', 'borrowings', $borrowG->id);

        // ═════════════════════════════════════════════════════════════════════════
        // SCENARIO H – CANCELLED (student cancelled before faculty acted)
        // ═════════════════════════════════════════════════════════════════════════
        $erlenmeyerInv = $inv('Erlenmeyer');

        $borrowH = Borrowing::create([
            'user_id'    => $students[2]->id, // Carlos Miguel
            'faculty_id' => $faculty->id,
            'borrow_date' => Carbon::today()->addDays(5),
            'return_date' => Carbon::today()->addDays(7),
            'purpose'     => 'Extra experiment – cancelled due to schedule conflict',
            'subject'     => 'CHEM 101 – General Chemistry',
            'status'      => 'cancelled',
            'daily_fine_rate' => 10.00,
        ]);
        $borrowH->claim_code = 'CHEM-REQ-' . now()->format('Y') . '-' . str_pad($borrowH->id, 5, '0', STR_PAD_LEFT);
        $borrowH->save();

        if ($erlenmeyerInv) {
            BorrowingEquipment::create([
                'borrowing_id'      => $borrowH->id,
                'equipment_id'      => $erlenmeyerInv->equipment_id,
                'inventory_id'      => $erlenmeyerInv->id,
                'quantity'          => 4,
                'condition_on_borrow' => 'fair',
            ]);
        }

        AuditLog::log($students[2]->id, 'Cancelled Borrow Request', 'borrowings', $borrowH->id);
    }
}
