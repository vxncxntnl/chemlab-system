<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CampusSeeder::class,        // 1. Campuses & Lab locations first
            UserSeeder::class,          // 2. Users (Admin, Lab Staff, Faculty, Students)
            EquipmentSeeder::class,     // 3. Chemistry equipment + categories
            BorrowingSeeder::class,     // 4. Demo borrowings covering full workflow
            LabInventorySeeder::class,  // 5. Lab inventory (consumables)
            SystemSettingsSeeder::class, // 6. System settings
        ]);
    }
}
