<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Root Admin',
                'email' => 'root.admin@sample.com',
                'password' => Hash::make('sample'),
                'role' => 'admin',
                'is_root_admin' => true,
            ],
            [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_root_admin' => false,
            ],
            [
                'name' => 'admin3',
                'email' => 'jeromebernante@gmail.com',
                'password' => Hash::make('1234'),
                'role' => 'admin',
                'is_root_admin' => false,
            ],
            [
                'name' => 'admin2',
                'email' => 'vallecera@gmail.com',
                'password' => Hash::make('sample'),
                'role' => 'admin',
                'is_root_admin' => false,
            ],
            [
                'name' => 'admin',
                'email' => 'admin@gmail.com',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_root_admin' => false,
            ],
            [
                'name' => 'Sample Instructor',
                'email' => 'instructor@sample.com',
                'password' => Hash::make('sample'),
                'role' => 'instructor',
                'rfid_tag' => 'RFID-INSTRUCTOR-SAMPLE',
            ],
            [
                'name' => 'Clinic Staff',
                'email' => 'clinic@sample.com',
                'password' => Hash::make('sample'),
                'role' => 'clinic',
            ],
            [
                'name' => 'Clinic Responder',
                'email' => 'clinic.responder@sample.com',
                'password' => Hash::make('sample'),
                'role' => 'clinic',
            ],
            [
                'name' => 'Registrar Staff',
                'email' => 'registrar@sample.com',
                'password' => Hash::make('sample'),
                'role' => 'registrar',
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                $user,
            );
        }
    }
}
