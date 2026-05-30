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
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ],
            [
                'name' => 'Dev Jerome',
                'email' => 'jeromebernante@gmail.com',
                'password' => Hash::make('1234'),
                'role' => 'admin',
            ],
            [
                'name' => 'Ronie',
                'email' => 'vallecera@gmail.com',
                'password' => Hash::make('sample'),
                'role' => 'admin',
            ],
            [
                'name' => 'admin',
                'email' => 'admin@gmail.com',
                'password' => Hash::make('password'),
                'role' => 'admin',
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
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                $user,
            );
        }
    }
}
