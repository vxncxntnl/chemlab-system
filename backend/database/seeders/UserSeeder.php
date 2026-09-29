<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $carmen = Campus::where('campus_name', 'like', '%Carmen%')->first();
        $puerto = Campus::where('campus_name', 'like', '%Puerto%')->first();
        $iligan = Campus::where('campus_name', 'like', '%Iligan%')->first();

        // ─── ADMINISTRATOR ────────────────────────────────────────────────────────
        User::create([
            'name'           => 'Admin – PHINMA COC',
            'email'          => 'admin@phinmacoc.edu.ph',
            'password'       => Hash::make('admin123'),
            'role'           => 'admin',
            'department'     => 'Administration',
            'employee_id'    => 'ADM-001',
            'contact_number' => '09171000001',
        ]);

        // ─── LAB STAFF ────────────────────────────────────────────────────────────
        User::create([
            'name'           => 'Liza Macaraeg',
            'email'          => 'liza.macaraeg@phinmacoc.edu.ph',
            'password'       => Hash::make('labstaff123'),
            'role'           => 'lab_staff',
            'department'     => 'Science Laboratory',
            'employee_id'    => 'LAB-001',
            'contact_number' => '09171000002',
            'campus_id'      => $carmen?->id,
        ]);

        User::create([
            'name'           => 'Ramon Durano',
            'email'          => 'ramon.durano@phinmacoc.edu.ph',
            'password'       => Hash::make('labstaff123'),
            'role'           => 'lab_staff',
            'department'     => 'Science Laboratory',
            'employee_id'    => 'LAB-002',
            'contact_number' => '09171000003',
            'campus_id'      => $puerto?->id,
        ]);

        User::create([
            'name'           => 'Grace Buenaventura',
            'email'          => 'grace.buenaventura@phinmacoc.edu.ph',
            'password'       => Hash::make('labstaff123'),
            'role'           => 'lab_staff',
            'department'     => 'Science Laboratory',
            'employee_id'    => 'LAB-003',
            'contact_number' => '09171000004',
            'campus_id'      => $iligan?->id,
        ]);

        // ─── FACULTY ─────────────────────────────────────────────────────────────
        User::create([
            'name'           => 'Dr. Maria Santos',
            'email'          => 'maria.santos@phinmacoc.edu.ph',
            'password'       => Hash::make('faculty123'),
            'role'           => 'faculty',
            'department'     => 'College of Science – Chemistry',
            'employee_id'    => 'FAC-001',
            'contact_number' => '09181000001',
        ]);

        User::create([
            'name'           => 'Prof. Jose Reyes',
            'email'          => 'jose.reyes@phinmacoc.edu.ph',
            'password'       => Hash::make('faculty123'),
            'role'           => 'faculty',
            'department'     => 'College of Science – Biology',
            'employee_id'    => 'FAC-002',
            'contact_number' => '09181000002',
        ]);

        User::create([
            'name'           => 'Dr. Ana Villanueva',
            'email'          => 'ana.villanueva@phinmacoc.edu.ph',
            'password'       => Hash::make('faculty123'),
            'role'           => 'faculty',
            'department'     => 'College of Science – Physical Science',
            'employee_id'    => 'FAC-003',
            'contact_number' => '09181000003',
        ]);

        // ─── STUDENTS ─────────────────────────────────────────────────────────────
        // BSED-Science students
        User::create([
            'name'           => 'Juan dela Cruz',
            'email'          => 'juan.delacruz@student.phinmacoc.edu.ph',
            'password'       => Hash::make('student123'),
            'role'           => 'student',
            'department'     => 'College of Science',
            'course'         => 'BSED',
            'student_id'     => '2024-CHEM-0001',
            'contact_number' => '09201000001',
            'campus_id'      => $carmen?->id,
        ]);

        User::create([
            'name'           => 'Maria Clara Reyes',
            'email'          => 'maria.reyes@student.phinmacoc.edu.ph',
            'password'       => Hash::make('student123'),
            'role'           => 'student',
            'department'     => 'College of Science',
            'course'         => 'BSED',
            'student_id'     => '2024-CHEM-0002',
            'contact_number' => '09201000002',
            'campus_id'      => $carmen?->id,
        ]);

        User::create([
            'name'           => 'Carlos Miguel Buenaflor',
            'email'          => 'carlos.buenaflor@student.phinmacoc.edu.ph',
            'password'       => Hash::make('student123'),
            'role'           => 'student',
            'department'     => 'College of Science',
            'course'         => 'BSIT',
            'student_id'     => '2024-CHEM-0003',
            'contact_number' => '09201000003',
            'campus_id'      => $carmen?->id,
        ]);

        User::create([
            'name'           => 'Anna Patricia Gomez',
            'email'          => 'anna.gomez@student.phinmacoc.edu.ph',
            'password'       => Hash::make('student123'),
            'role'           => 'student',
            'department'     => 'College of Science',
            'course'         => 'BSED',
            'student_id'     => '2024-CHEM-0004',
            'contact_number' => '09201000004',
            'campus_id'      => $puerto?->id,
        ]);

        User::create([
            'name'           => 'Pedro Alvarez',
            'email'          => 'pedro.alvarez@student.phinmacoc.edu.ph',
            'password'       => Hash::make('student123'),
            'role'           => 'student',
            'department'     => 'College of Science',
            'course'         => 'BSED',
            'student_id'     => '2024-CHEM-0005',
            'contact_number' => '09201000005',
            'campus_id'      => $iligan?->id,
        ]);
    }
}
