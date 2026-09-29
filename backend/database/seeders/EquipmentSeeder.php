<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\Location;
use Illuminate\Database\Seeder;

class EquipmentSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Categories ───────────────────────────────────────────────────────────
        $glassware   = Category::create(['name' => 'Glassware',          'description' => 'Glass containers and volumetric tools']);
        $instruments = Category::create(['name' => 'Instruments',        'description' => 'Measuring and analytical instruments']);
        $heating     = Category::create(['name' => 'Heating Equipment',  'description' => 'Burners, hot plates, and heating apparatus']);
        $safety      = Category::create(['name' => 'Safety Equipment',   'description' => 'Protective gear and safety tools']);
        $microscopy  = Category::create(['name' => 'Microscopy',         'description' => 'Microscopes and magnification tools']);
        $disposables = Category::create(['name' => 'Consumables',        'description' => 'Reusable low-cost lab consumables']);

        // Grab locations by name for clarity
        $chemLab01Carmen  = Location::where('location_name', 'like', '%Chem Lab 01%Carmen%')->first();
        $chemLab02Carmen  = Location::where('location_name', 'like', '%Chem Lab 02%Carmen%')->first();
        $bioLabCarmen     = Location::where('location_name', 'like', '%Bio Lab%Carmen%')->first();
        $storageCarmen    = Location::where('location_name', 'like', '%Storage%Carmen%')->first();
        $chemLab01Puerto  = Location::where('location_name', 'like', '%Chem Lab 01%Puerto%')->first();
        $physLabPuerto    = Location::where('location_name', 'like', '%Physical%Puerto%')->first();
        $chemLab01Iligan  = Location::where('location_name', 'like', '%Chem Lab 01%Iligan%')->first();
        $bioChemIligan    = Location::where('location_name', 'like', '%Bio-Chem%')->first();

        // ─────────────────────────────────────────────────────────────────────────
        // 1. BEAKER – 250 mL
        // ─────────────────────────────────────────────────────────────────────────
        $beaker250 = Equipment::create([
            'name'        => 'Beaker (250 mL)',
            'description' => 'Borosilicate glass beaker with graduated markings. Used for mixing, heating, and measuring liquid volumes.',
            'category'    => 'Glassware',
            'category_id' => $glassware->id,
            'condition'   => 'good',
            'is_available' => true,
        ]);
        EquipmentInventory::create([
            'equipment_id'      => $beaker250->id,
            'campus_id'         => $chemLab01Carmen->campus_id,
            'location_id'       => $chemLab01Carmen->id,
            'total_quantity'    => 25,
            'available_quantity' => 11,
            'reorder_level'     => 5,
        ]);
        EquipmentInventory::create([
            'equipment_id'      => $beaker250->id,
            'campus_id'         => $chemLab01Puerto->campus_id,
            'location_id'       => $chemLab01Puerto->id,
            'total_quantity'    => 20,
            'available_quantity' => 18,
            'reorder_level'     => 5,
        ]);
        EquipmentInventory::create([
            'equipment_id'      => $beaker250->id,
            'campus_id'         => $chemLab01Iligan->campus_id,
            'location_id'       => $chemLab01Iligan->id,
            'total_quantity'    => 15,
            'available_quantity' => 15,
            'reorder_level'     => 3,
        ]);

        // ─────────────────────────────────────────────────────────────────────────
        // 2. TEST TUBE – 15 mL
        // ─────────────────────────────────────────────────────────────────────────
        $testTube = Equipment::create([
            'name'        => 'Test Tube (15 mL)',
            'description' => 'Borosilicate glass test tube, 15 mL capacity. Used for small-scale reactions and sample containment.',
            'category'    => 'Glassware',
            'category_id' => $glassware->id,
            'condition'   => 'good',
            'is_available' => true,
        ]);
        EquipmentInventory::create([
            'equipment_id'      => $testTube->id,
            'campus_id'         => $chemLab01Carmen->campus_id,
            'location_id'       => $chemLab01Carmen->id,
            'total_quantity'    => 50,
            'available_quantity' => 42,
            'reorder_level'     => 10,
        ]);
        EquipmentInventory::create([
            'equipment_id'      => $testTube->id,
            'campus_id'         => $chemLab01Puerto->campus_id,
            'location_id'       => $chemLab01Puerto->id,
            'total_quantity'    => 40,
            'available_quantity' => 40,
            'reorder_level'     => 8,
        ]);

        // ─────────────────────────────────────────────────────────────────────────
        // 3. GRADUATED CYLINDER – 100 mL
        // ─────────────────────────────────────────────────────────────────────────
        $gradCylinder = Equipment::create([
            'name'        => 'Graduated Cylinder (100 mL)',
            'description' => 'Borosilicate glass graduated cylinder with 1 mL graduation intervals. Used for accurate liquid volume measurement.',
            'category'    => 'Glassware',
            'category_id' => $glassware->id,
            'condition'   => 'good',
            'is_available' => true,
        ]);
        EquipmentInventory::create([
            'equipment_id'      => $gradCylinder->id,
            'campus_id'         => $chemLab01Carmen->campus_id,
            'location_id'       => $chemLab01Carmen->id,
            'total_quantity'    => 20,
            'available_quantity' => 17,
            'reorder_level'     => 4,
        ]);
        EquipmentInventory::create([
            'equipment_id'      => $gradCylinder->id,
            'campus_id'         => $chemLab01Puerto->campus_id,
            'location_id'       => $chemLab01Puerto->id,
            'total_quantity'    => 15,
            'available_quantity' => 13,
            'reorder_level'     => 3,
        ]);

        // ─────────────────────────────────────────────────────────────────────────
        // 4. BUNSEN BURNER
        // ─────────────────────────────────────────────────────────────────────────
        $bunsenBurner = Equipment::create([
            'name'        => 'Bunsen Burner',
            'description' => 'Single-opening laboratory gas burner. Produces a single open gas flame used for heating, sterilization, and combustion.',
            'category'    => 'Heating Equipment',
            'category_id' => $heating->id,
            'condition'   => 'good',
            'is_available' => true,
        ]);
        EquipmentInventory::create([
            'equipment_id'      => $bunsenBurner->id,
            'campus_id'         => $chemLab01Carmen->campus_id,
            'location_id'       => $chemLab01Carmen->id,
            'total_quantity'    => 15,
            'available_quantity' => 12,
            'reorder_level'     => 3,
        ]);
        EquipmentInventory::create([
            'equipment_id'      => $bunsenBurner->id,
            'campus_id'         => $chemLab01Puerto->campus_id,
            'location_id'       => $chemLab01Puerto->id,
            'total_quantity'    => 10,
            'available_quantity' => 10,
            'reorder_level'     => 3,
        ]);
        EquipmentInventory::create([
            'equipment_id'      => $bunsenBurner->id,
            'campus_id'         => $chemLab01Iligan->campus_id,
            'location_id'       => $chemLab01Iligan->id,
            'total_quantity'    => 8,
            'available_quantity' => 8,
            'reorder_level'     => 2,
        ]);

        // ─────────────────────────────────────────────────────────────────────────
        // 5. COMPOUND MICROSCOPE
        // ─────────────────────────────────────────────────────────────────────────
        $microscope = Equipment::create([
            'name'        => 'Compound Microscope',
            'description' => 'Binocular compound microscope with 4×, 10×, 40×, and 100× objectives. Used for observing microorganisms and cell structures.',
            'category'    => 'Microscopy',
            'category_id' => $microscopy->id,
            'condition'   => 'good',
            'is_available' => true,
        ]);
        EquipmentInventory::create([
            'equipment_id'      => $microscope->id,
            'campus_id'         => $bioLabCarmen->campus_id,
            'location_id'       => $bioLabCarmen->id,
            'total_quantity'    => 10,
            'available_quantity' => 8,
            'reorder_level'     => 2,
        ]);
        EquipmentInventory::create([
            'equipment_id'      => $microscope->id,
            'campus_id'         => $bioChemIligan->campus_id,
            'location_id'       => $bioChemIligan->id,
            'total_quantity'    => 6,
            'available_quantity' => 5,
            'reorder_level'     => 1,
        ]);

        // ─────────────────────────────────────────────────────────────────────────
        // 6. PETRI DISH (Set of 5)
        // ─────────────────────────────────────────────────────────────────────────
        $petriDish = Equipment::create([
            'name'        => 'Petri Dish (Set of 5)',
            'description' => 'Glass petri dishes, 90 mm diameter. Used for culturing microorganisms and observing reactions on flat surfaces.',
            'category'    => 'Glassware',
            'category_id' => $glassware->id,
            'condition'   => 'good',
            'is_available' => true,
        ]);
        EquipmentInventory::create([
            'equipment_id'      => $petriDish->id,
            'campus_id'         => $bioLabCarmen->campus_id,
            'location_id'       => $bioLabCarmen->id,
            'total_quantity'    => 30,
            'available_quantity' => 28,
            'reorder_level'     => 5,
        ]);
        EquipmentInventory::create([
            'equipment_id'      => $petriDish->id,
            'campus_id'         => $bioChemIligan->campus_id,
            'location_id'       => $bioChemIligan->id,
            'total_quantity'    => 20,
            'available_quantity' => 20,
            'reorder_level'     => 4,
        ]);

        // ─────────────────────────────────────────────────────────────────────────
        // 7. DIGITAL pH METER
        // ─────────────────────────────────────────────────────────────────────────
        $phMeter = Equipment::create([
            'name'        => 'Digital pH Meter',
            'description' => 'Portable digital pH meter, 0–14 pH range, ±0.01 accuracy. Used for measuring the acidity or alkalinity of solutions.',
            'category'    => 'Instruments',
            'category_id' => $instruments->id,
            'condition'   => 'good',
            'is_available' => true,
        ]);
        EquipmentInventory::create([
            'equipment_id'      => $phMeter->id,
            'campus_id'         => $chemLab01Carmen->campus_id,
            'location_id'       => $chemLab01Carmen->id,
            'total_quantity'    => 8,
            'available_quantity' => 6,
            'reorder_level'     => 2,
        ]);
        EquipmentInventory::create([
            'equipment_id'      => $phMeter->id,
            'campus_id'         => $chemLab01Puerto->campus_id,
            'location_id'       => $chemLab01Puerto->id,
            'total_quantity'    => 5,
            'available_quantity' => 4,
            'reorder_level'     => 1,
        ]);
        EquipmentInventory::create([
            'equipment_id'      => $phMeter->id,
            'campus_id'         => $chemLab01Iligan->campus_id,
            'location_id'       => $chemLab01Iligan->id,
            'total_quantity'    => 4,
            'available_quantity' => 4,
            'reorder_level'     => 1,
        ]);

        // ─────────────────────────────────────────────────────────────────────────
        // 8. SAFETY GOGGLES
        // ─────────────────────────────────────────────────────────────────────────
        $safetyGoggles = Equipment::create([
            'name'        => 'Safety Goggles',
            'description' => 'ANSI Z87.1-rated chemical splash safety goggles with indirect ventilation. Required PPE for all laboratory activities.',
            'category'    => 'Safety Equipment',
            'category_id' => $safety->id,
            'condition'   => 'good',
            'is_available' => true,
        ]);
        EquipmentInventory::create([
            'equipment_id'      => $safetyGoggles->id,
            'campus_id'         => $chemLab01Carmen->campus_id,
            'location_id'       => $chemLab01Carmen->id,
            'total_quantity'    => 40,
            'available_quantity' => 35,
            'reorder_level'     => 10,
        ]);
        EquipmentInventory::create([
            'equipment_id'      => $safetyGoggles->id,
            'campus_id'         => $chemLab01Puerto->campus_id,
            'location_id'       => $chemLab01Puerto->id,
            'total_quantity'    => 30,
            'available_quantity' => 30,
            'reorder_level'     => 8,
        ]);
        EquipmentInventory::create([
            'equipment_id'      => $safetyGoggles->id,
            'campus_id'         => $chemLab01Iligan->campus_id,
            'location_id'       => $chemLab01Iligan->id,
            'total_quantity'    => 25,
            'available_quantity' => 22,
            'reorder_level'     => 5,
        ]);

        // ─────────────────────────────────────────────────────────────────────────
        // 9. ANALYTICAL BALANCE (0.0001 g)
        // ─────────────────────────────────────────────────────────────────────────
        $analyticalBalance = Equipment::create([
            'name'        => 'Analytical Balance (0.0001 g)',
            'description' => 'Digital analytical balance with 0.1 mg readability and 220 g capacity. Used for precise mass measurements in quantitative analysis.',
            'category'    => 'Instruments',
            'category_id' => $instruments->id,
            'serial_number' => 'AB-PHINMA-001',
            'condition'   => 'good',
            'is_available' => true,
        ]);
        EquipmentInventory::create([
            'equipment_id'      => $analyticalBalance->id,
            'campus_id'         => $storageCarmen->campus_id,
            'location_id'       => $storageCarmen->id,
            'total_quantity'    => 5,
            'available_quantity' => 4,
            'reorder_level'     => 1,
        ]);
        EquipmentInventory::create([
            'equipment_id'      => $analyticalBalance->id,
            'campus_id'         => $physLabPuerto->campus_id,
            'location_id'       => $physLabPuerto->id,
            'total_quantity'    => 3,
            'available_quantity' => 3,
            'reorder_level'     => 1,
        ]);

        // ─────────────────────────────────────────────────────────────────────────
        // 10. VOLUMETRIC FLASK – 250 mL
        // ─────────────────────────────────────────────────────────────────────────
        $volumetricFlask = Equipment::create([
            'name'        => 'Volumetric Flask (250 mL)',
            'description' => 'Class A borosilicate volumetric flask with glass stopper. Used for preparing precise standard solutions.',
            'category'    => 'Glassware',
            'category_id' => $glassware->id,
            'condition'   => 'good',
            'is_available' => true,
        ]);
        EquipmentInventory::create([
            'equipment_id'      => $volumetricFlask->id,
            'campus_id'         => $chemLab02Carmen->campus_id,
            'location_id'       => $chemLab02Carmen->id,
            'total_quantity'    => 15,
            'available_quantity' => 14,
            'reorder_level'     => 3,
        ]);
        EquipmentInventory::create([
            'equipment_id'      => $volumetricFlask->id,
            'campus_id'         => $chemLab01Puerto->campus_id,
            'location_id'       => $chemLab01Puerto->id,
            'total_quantity'    => 12,
            'available_quantity' => 12,
            'reorder_level'     => 3,
        ]);

        // ─────────────────────────────────────────────────────────────────────────
        // 11. LABORATORY THERMOMETER
        // ─────────────────────────────────────────────────────────────────────────
        $thermometer = Equipment::create([
            'name'        => 'Laboratory Thermometer (–10°C to 110°C)',
            'description' => 'Mercury-free alcohol thermometer. Used for measuring temperature in chemical reactions and solution preparation.',
            'category'    => 'Instruments',
            'category_id' => $instruments->id,
            'condition'   => 'good',
            'is_available' => true,
        ]);
        EquipmentInventory::create([
            'equipment_id'      => $thermometer->id,
            'campus_id'         => $chemLab01Carmen->campus_id,
            'location_id'       => $chemLab01Carmen->id,
            'total_quantity'    => 20,
            'available_quantity' => 18,
            'reorder_level'     => 5,
        ]);

        // ─────────────────────────────────────────────────────────────────────────
        // 12. ERLENMEYER FLASK – 250 mL (low stock – for alerts demo)
        // ─────────────────────────────────────────────────────────────────────────
        $erlenmeyerFlask = Equipment::create([
            'name'        => 'Erlenmeyer Flask (250 mL)',
            'description' => 'Borosilicate conical flask with narrow neck. Widely used for mixing, swirling reagents, and titration experiments.',
            'category'    => 'Glassware',
            'category_id' => $glassware->id,
            'condition'   => 'fair',
            'is_available' => true,
        ]);
        EquipmentInventory::create([
            'equipment_id'      => $erlenmeyerFlask->id,
            'campus_id'         => $chemLab01Carmen->campus_id,
            'location_id'       => $chemLab01Carmen->id,
            'total_quantity'    => 20,
            'available_quantity' => 3,   // LOW STOCK – triggers alert
            'reorder_level'     => 5,
        ]);
    }
}
