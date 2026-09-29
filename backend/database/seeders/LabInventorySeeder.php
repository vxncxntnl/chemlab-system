<?php

namespace Database\Seeders;

use App\Models\LabInventory;
use Illuminate\Database\Seeder;

class LabInventorySeeder extends Seeder
{
    public function run(): void
    {
        // Chemistry Laboratory - General Apparatus & Equipment
        $chemistryGeneral = [
            ['item_no' => '1', 'description' => 'AC ADAPTOR', 'size' => null, 'quantity' => 1, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'CD-1', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '2', 'description' => 'AC CONVERTER', 'size' => null, 'quantity' => 1, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'CD-1', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '4', 'description' => 'ALCOHOL LAMP', 'size' => null, 'quantity' => 15, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'CD-1', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '7', 'description' => 'ALLIGATOR CLIP', 'size' => 'BIG', 'quantity' => 8, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'CD-1', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '7', 'description' => 'ALLIGATOR CLIP', 'size' => 'SMALL', 'quantity' => 108, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'CD-1', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '13', 'description' => 'ANALYTICAL BALANCE (OHAUS-NEW)', 'size' => null, 'quantity' => 1, 'uom' => 'UNIT', 'brand' => 'OHAUS', 'storage_location' => null, 'condition_remarks' => 'Good/Working (Newly Purchased PO12178)', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '22', 'description' => 'BEAKER', 'size' => '250mL', 'quantity' => 433, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'C-1/CD-6', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 12, 'qty_sent_butuan' => 5],
            ['item_no' => '22', 'description' => 'BEAKER', 'size' => '1000mL', 'quantity' => 51, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'C-1/CD-6', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '35', 'description' => 'BUNSEN BURNER', 'size' => null, 'quantity' => 75, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'CD-3', 'condition_remarks' => 'Good/Working but 23 pcs. For repair', 'qty_sent_iligan' => 7, 'qty_sent_butuan' => 5],
            ['item_no' => '36', 'description' => 'BURETTE', 'size' => '25mL', 'quantity' => 49, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'CD-21', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '36', 'description' => 'BURETTE', 'size' => '50mL', 'quantity' => 26, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'CD-22', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '65', 'description' => 'CRUCIBLE w/ COVER', 'size' => null, 'quantity' => 114, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'CD-6', 'condition_remarks' => '14pcs. Broken (Crucible), 8pcs. Broken (Cover)', 'qty_sent_iligan' => 4, 'qty_sent_butuan' => 0],
            ['item_no' => '92', 'description' => 'ERLENMEYER FLASK', 'size' => '250mL', 'quantity' => 898, 'uom' => 'pcs.', 'brand' => 'PYREX', 'storage_location' => 'C-3/CD-12', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 7, 'qty_sent_butuan' => 5],
            ['item_no' => '112', 'description' => 'GOGGLES', 'size' => null, 'quantity' => 68, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'C-2', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '115', 'description' => 'GRADUATED CYLINDER', 'size' => '10mL', 'quantity' => 114, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'C-2', 'condition_remarks' => 'Good but 15pcs. Broken', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '115', 'description' => 'GRADUATED CYLINDER', 'size' => '50mL', 'quantity' => 50, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'C-2', 'condition_remarks' => 'Good but 15pcs. Broken', 'qty_sent_iligan' => 7, 'qty_sent_butuan' => 4],
            ['item_no' => '159', 'description' => 'MICROSCOPE', 'size' => 'BINOCULAR', 'quantity' => 7, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'At top of C-2/C-3', 'condition_remarks' => 'Working but 1pc blurred', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '159', 'description' => 'MICROSCOPE', 'size' => 'ELECTRIC (SVBONY SV601 NEW)', 'quantity' => 10, 'uom' => 'pcs.', 'brand' => 'SVBONY', 'storage_location' => null, 'condition_remarks' => 'Good/Working (Received 11/20/2024)', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '167', 'description' => 'MORTAR & PESTLE', 'size' => null, 'quantity' => 158, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'C-3', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 4, 'qty_sent_butuan' => 0],
            ['item_no' => '177', 'description' => 'pH METER', 'size' => null, 'quantity' => 8, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'C-3', 'condition_remarks' => 'Good/Working but 1pc. damage', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '240', 'description' => 'TEST TUBE', 'size' => '7mL', 'quantity' => 1031, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'C-4', 'condition_remarks' => '100pcs. Broken', 'qty_sent_iligan' => 40, 'qty_sent_butuan' => 40],
            ['item_no' => '240', 'description' => 'TEST TUBE', 'size' => '25mL', 'quantity' => 463, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'C-4', 'condition_remarks' => '84pcs. Broken', 'qty_sent_iligan' => 40, 'qty_sent_butuan' => 0],
            ['item_no' => '242', 'description' => 'TEST TUBE RACK', 'size' => null, 'quantity' => 56, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'C-4', 'condition_remarks' => '2pcs. Damage', 'qty_sent_iligan' => 2, 'qty_sent_butuan' => 0],
            ['item_no' => '243', 'description' => 'THERMOMETER', 'size' => '100°C', 'quantity' => 85, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'CD-16', 'condition_remarks' => 'Working', 'qty_sent_iligan' => 5, 'qty_sent_butuan' => 0],
            ['item_no' => '255', 'description' => 'TRIPLE BEAM BALANCE', 'size' => null, 'quantity' => 15, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'C-5', 'condition_remarks' => '1pc. Damage', 'qty_sent_iligan' => 3, 'qty_sent_butuan' => 0],
            ['item_no' => '264', 'description' => 'VERNIER CALIPER', 'size' => null, 'quantity' => 23, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'CD-18', 'condition_remarks' => 'Working', 'qty_sent_iligan' => 3, 'qty_sent_butuan' => 0],
            ['item_no' => '269', 'description' => 'VOLUMETRIC FLASK', 'size' => '250mL', 'quantity' => 97, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => null, 'condition_remarks' => 'Good but 1pc. Broken', 'qty_sent_iligan' => 3, 'qty_sent_butuan' => 0],
            ['item_no' => '271', 'description' => 'VOLTMETER', 'size' => null, 'quantity' => 20, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'C-5', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '274', 'description' => 'WATER BATH', 'size' => null, 'quantity' => 11, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'C-5', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 5, 'qty_sent_butuan' => 0],
        ];

        foreach ($chemistryGeneral as $item) {
            LabInventory::create(array_merge($item, ['lab' => 'chemistry', 'category' => 'general']));
        }

        // Chemistry Laboratory - PASCO Equipment
        $chemistryPasco = [
            ['item_no' => '1', 'description' => 'EC-6361 ESSENTIAL CHEMISTRY STANDARD EQUIPMENT', 'size' => 'N/A', 'quantity' => 1, 'uom' => 'SET', 'brand' => 'PASCO', 'storage_location' => 'Chem Lab', 'condition_remarks' => 'Good/Working (Received Oct.19,2023)', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0, 'current_campus_location' => 'Butuan Campus'],
            ['item_no' => null, 'description' => 'PS-3201 WIRELESS TEMPERATURE SENSOR', 'size' => 'N/A', 'quantity' => 1, 'uom' => 'pc.', 'brand' => 'PASCO', 'storage_location' => 'Chem Lab', 'condition_remarks' => 'Part of EC-6361 set', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => null, 'description' => 'PS-3204 WIRELESS PH SENSOR', 'size' => 'N/A', 'quantity' => 1, 'uom' => 'pc.', 'brand' => 'PASCO', 'storage_location' => 'Chem Lab', 'condition_remarks' => 'Part of EC-6361 set', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => null, 'description' => 'PS-3210A WIRELESS CONDUCTIVITY SENSOR', 'size' => 'N/A', 'quantity' => 1, 'uom' => 'pc.', 'brand' => 'PASCO', 'storage_location' => 'Chem Lab', 'condition_remarks' => 'Part of EC-6361 set', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => null, 'description' => 'PS-3203 WIRELESS PRESSURE SENSOR', 'size' => 'N/A', 'quantity' => 1, 'uom' => 'pc.', 'brand' => 'PASCO', 'storage_location' => 'Chem Lab', 'condition_remarks' => 'Part of EC-6361 set', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => null, 'description' => 'PS-3211 WIRELESS VOLTAGE SENSOR', 'size' => 'N/A', 'quantity' => 1, 'uom' => 'pc.', 'brand' => 'PASCO', 'storage_location' => 'Chem Lab', 'condition_remarks' => 'Part of EC-6361 set', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => null, 'description' => 'PS-3215 COLORIMETER & TURBIDITY SENSOR', 'size' => 'N/A', 'quantity' => 1, 'uom' => 'pc.', 'brand' => 'PASCO', 'storage_location' => 'Chem Lab', 'condition_remarks' => 'Part of EC-6361 set', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '2', 'description' => 'EC-6352 ESSENTIAL CHEMISTRY LAB MANUAL', 'size' => 'N/A', 'quantity' => 1, 'uom' => 'pc.', 'brand' => 'PASCO', 'storage_location' => 'Chem Lab', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '3', 'description' => 'PS-3201 WIRELESS TEMPERATURE SENSOR', 'size' => 'N/A', 'quantity' => 1, 'uom' => 'pc.', 'brand' => 'PASCO', 'storage_location' => 'Chem Lab', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '9', 'description' => 'PS-3401 HEATER STIRRER (MAGNETIC STIRRER)', 'size' => 'N/A', 'quantity' => 1, 'uom' => 'pc.', 'brand' => 'PASCO', 'storage_location' => 'Chem Lab', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '10', 'description' => 'PS-3208 WIRELESS CO2 SENSOR', 'size' => 'N/A', 'quantity' => 1, 'uom' => 'pc.', 'brand' => 'PASCO', 'storage_location' => 'Chem Lab', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '11', 'description' => 'ME-6667 ECOCHAMBER', 'size' => 'N/A', 'quantity' => 1, 'uom' => 'pc.', 'brand' => 'PASCO', 'storage_location' => 'Chem Lab', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
        ];

        foreach ($chemistryPasco as $item) {
            LabInventory::create(array_merge($item, ['lab' => 'chemistry', 'category' => 'pasco']));
        }

        // Physics Laboratory - General Apparatus & Equipment
        $physicsGeneral = [
            ['item_no' => null, 'description' => 'AC AMMETER', 'size' => null, 'quantity' => 2, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'CD-1', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => null, 'description' => 'ANALOG AMMETER', 'size' => null, 'quantity' => 6, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'CD-1', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => null, 'description' => 'BULB HOLDER', 'size' => null, 'quantity' => 15, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'CD-19', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => null, 'description' => 'BURETTE', 'size' => '25mL', 'quantity' => 18, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'CD-5', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => null, 'description' => 'BURETTE', 'size' => '50mL', 'quantity' => 45, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => null, 'condition_remarks' => 'Good but 2pcs. Broken', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => null, 'description' => 'CALIPERS', 'size' => null, 'quantity' => 11, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'CD-3', 'condition_remarks' => 'Good but 1pc. Not Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => null, 'description' => 'CENTRIFUGAL FORCE/FORCE TABLE', 'size' => null, 'quantity' => 4, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'CD-7,10,11 & 12', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 4, 'qty_sent_butuan' => 0],
            ['item_no' => null, 'description' => 'DC AMMETER', 'size' => null, 'quantity' => 27, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'CD-3 & 4', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => null, 'description' => 'GALVANOMETER', 'size' => null, 'quantity' => 5, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'CD-4', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => null, 'description' => 'IRON CLAMP', 'size' => null, 'quantity' => 102, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'CD-13', 'condition_remarks' => 'Good but 43pcs. Damage', 'qty_sent_iligan' => 5, 'qty_sent_butuan' => 0],
            ['item_no' => null, 'description' => 'IRON RING', 'size' => null, 'quantity' => 70, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'CD-13', 'condition_remarks' => 'Good but 3 Damage', 'qty_sent_iligan' => 2, 'qty_sent_butuan' => 4],
            ['item_no' => null, 'description' => 'IRON STAND (BIG)', 'size' => null, 'quantity' => 51, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'CD-3,4,15 & 18', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 5, 'qty_sent_butuan' => 4],
            ['item_no' => null, 'description' => 'OPTICAL BENCH', 'size' => null, 'quantity' => 5, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'At top of C-2', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => null, 'description' => 'POLARIMETER', 'size' => null, 'quantity' => 2, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'CD-21 & 22', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => null, 'description' => 'PROJECTILE KIT', 'size' => null, 'quantity' => 3, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'At top of C-3 & C-4', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 3, 'qty_sent_butuan' => 0],
            ['item_no' => null, 'description' => 'SLIDING RHEOSTAT', 'size' => null, 'quantity' => 10, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'CD-14 & 15', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => null, 'description' => 'TEST TUBE RACK', 'size' => null, 'quantity' => 81, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'At top of C-1', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => null, 'description' => 'TONG', 'size' => null, 'quantity' => 50, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'CD-17', 'condition_remarks' => 'Good (current qty 96)', 'qty_sent_iligan' => 4, 'qty_sent_butuan' => 0],
            ['item_no' => null, 'description' => 'TRIPOD', 'size' => null, 'quantity' => 70, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'CD-25 & CD-26', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 8, 'qty_sent_butuan' => 0],
            ['item_no' => null, 'description' => 'VIALS', 'size' => null, 'quantity' => 127, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'CD-28 & 29', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => null, 'description' => 'WOODEN STOOL/CHAIR', 'size' => null, 'quantity' => 47, 'uom' => 'pcs.', 'brand' => null, 'storage_location' => 'Inside Physics Lab', 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
        ];

        foreach ($physicsGeneral as $item) {
            LabInventory::create(array_merge($item, ['lab' => 'physics', 'category' => 'general']));
        }

        // Physics Laboratory - PASCO Equipment
        $physicsPasco = [
            ['item_no' => '1', 'description' => 'EM-3536 ESSENTIAL PHYSICS MODULAR CIRCUITS KIT', 'size' => 'N/A', 'quantity' => 1, 'uom' => 'SET', 'brand' => 'PASCO', 'storage_location' => null, 'condition_remarks' => 'Good/Working (Received Oct.19,2023)', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '2', 'description' => 'EX-5534 RESISTIVITY SET', 'size' => 'N/A', 'quantity' => 1, 'uom' => 'SET', 'brand' => 'PASCO', 'storage_location' => null, 'condition_remarks' => 'Good/Working (Received Oct.19,2023)', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '3', 'description' => 'ME-6796 PROJECTILE LAUNCHER WIRELESS SMART GATE', 'size' => 'N/A', 'quantity' => 1, 'uom' => 'SET', 'brand' => 'PASCO', 'storage_location' => null, 'condition_remarks' => 'Good/Working (Received Oct.19,2023)', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '4', 'description' => 'ME-6810A TIME-OF-FLIGHT ACCESSORY', 'size' => 'N/A', 'quantity' => 1, 'uom' => 'UNIT', 'brand' => 'PASCO', 'storage_location' => null, 'condition_remarks' => 'Good/Working (Received Oct.19,2023)', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '5', 'description' => 'ME-8951 ROTATING PLATFORM', 'size' => 'N/A', 'quantity' => 1, 'uom' => 'UNIT', 'brand' => 'PASCO', 'storage_location' => null, 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '6', 'description' => 'ME-8094 WIRELESS CENTRIPETAL FORCE ACCESSORY', 'size' => 'N/A', 'quantity' => 1, 'uom' => 'UNIT', 'brand' => 'PASCO', 'storage_location' => null, 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '7', 'description' => 'ME-8569A DENSITY SET', 'size' => 'N/A', 'quantity' => 1, 'uom' => 'UNIT', 'brand' => 'PASCO', 'storage_location' => null, 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '10', 'description' => 'ME-5718A STANDARD SMART CART METAL TRACK 1.2M', 'size' => 'N/A', 'quantity' => 1, 'uom' => 'SET', 'brand' => 'PASCO', 'storage_location' => null, 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '12', 'description' => 'PS-3202 WIRELESS FORCE ACCELERATION SENSOR', 'size' => 'N/A', 'quantity' => 1, 'uom' => 'UNIT', 'brand' => 'PASCO', 'storage_location' => null, 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '13', 'description' => 'PS-3201 WIRELESS TEMPERATURE SENSOR', 'size' => 'N/A', 'quantity' => 1, 'uom' => 'UNIT', 'brand' => 'PASCO', 'storage_location' => null, 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '16', 'description' => 'SE-8710 DIGITAL CALIPER', 'size' => 'N/A', 'quantity' => 1, 'uom' => 'UNIT', 'brand' => 'PASCO', 'storage_location' => null, 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '17', 'description' => 'SE-7337A MICROMETER', 'size' => 'N/A', 'quantity' => 1, 'uom' => 'UNIT', 'brand' => 'PASCO', 'storage_location' => null, 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '18', 'description' => 'TD-8856 THERMAL EXPANSION APPARATUS', 'size' => 'N/A', 'quantity' => 1, 'uom' => 'SET', 'brand' => 'PASCO', 'storage_location' => null, 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
            ['item_no' => '20', 'description' => 'UI-5001 550 UNIVERSAL INTERFACE', 'size' => 'N/A', 'quantity' => 1, 'uom' => 'UNIT', 'brand' => 'PASCO', 'storage_location' => null, 'condition_remarks' => 'Good', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0, 'current_campus_location' => 'Butuan Campus (Nov.08,2025)'],
            ['item_no' => '21', 'description' => 'UI-5400 PASCO CAPSTONE SOFTWARE', 'size' => 'N/A', 'quantity' => 1, 'uom' => 'UNIT', 'brand' => 'PASCO', 'storage_location' => null, 'condition_remarks' => 'Good/Working', 'qty_sent_iligan' => 0, 'qty_sent_butuan' => 0],
        ];

        foreach ($physicsPasco as $item) {
            LabInventory::create(array_merge($item, ['lab' => 'physics', 'category' => 'pasco']));
        }
    }
}
