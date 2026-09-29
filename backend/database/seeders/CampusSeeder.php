<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\Location;
use Illuminate\Database\Seeder;

class CampusSeeder extends Seeder
{
    public function run(): void
    {
        // ─── CARMEN Campus ───────────────────────────────────────────────────────
        $carmen = Campus::create([
            'campus_name' => 'PHINMA COC – Carmen',
            'address'      => 'Carmen, Cagayan de Oro City',
            'contact_info' => '(088) 856-1234',
        ]);

        Location::create([
            'campus_id'     => $carmen->id,
            'location_name' => 'Chem Lab 01 – Carmen',
            'description'   => 'Main Chemistry Laboratory (General Chemistry)',
        ]);

        Location::create([
            'campus_id'     => $carmen->id,
            'location_name' => 'Chem Lab 02 – Carmen',
            'description'   => 'Organic Chemistry Laboratory',
        ]);

        Location::create([
            'campus_id'     => $carmen->id,
            'location_name' => 'Bio Lab – Carmen',
            'description'   => 'Biology & Microbiology Laboratory',
        ]);

        Location::create([
            'campus_id'     => $carmen->id,
            'location_name' => 'Lab Storage Room – Carmen',
            'description'   => 'Central Equipment Storage',
        ]);

        // ─── PUERTO Campus ───────────────────────────────────────────────────────
        $puerto = Campus::create([
            'campus_name' => 'PHINMA COC – Puerto',
            'address'      => 'Puerto, Cagayan de Oro City',
            'contact_info' => '(088) 856-2345',
        ]);

        Location::create([
            'campus_id'     => $puerto->id,
            'location_name' => 'Chem Lab 01 – Puerto',
            'description'   => 'Main Chemistry Laboratory',
        ]);

        Location::create([
            'campus_id'     => $puerto->id,
            'location_name' => 'Physical Science Lab – Puerto',
            'description'   => 'Physics & Physical Chemistry Laboratory',
        ]);

        Location::create([
            'campus_id'     => $puerto->id,
            'location_name' => 'Lab Storage Room – Puerto',
            'description'   => 'Equipment Storage & Preparation Room',
        ]);

        // ─── ILIGAN Campus ───────────────────────────────────────────────────────
        $iligan = Campus::create([
            'campus_name' => 'PHINMA COC – Iligan',
            'address'      => 'Iligan City, Lanao del Norte',
            'contact_info' => '(063) 221-3456',
        ]);

        Location::create([
            'campus_id'     => $iligan->id,
            'location_name' => 'Chem Lab 01 – Iligan',
            'description'   => 'Main Chemistry Laboratory',
        ]);

        Location::create([
            'campus_id'     => $iligan->id,
            'location_name' => 'Bio-Chem Lab – Iligan',
            'description'   => 'Biochemistry & Microbiology Laboratory',
        ]);

        Location::create([
            'campus_id'     => $iligan->id,
            'location_name' => 'Lab Storage Room – Iligan',
            'description'   => 'Equipment Storage Room',
        ]);
    }
}
