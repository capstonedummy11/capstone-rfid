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
        User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);

        User::create([
            'name' => 'Dev Jerome',
            'email' => 'jeromebernante@gmail.com',
            'password' => Hash::make('1234'),
            'role' => 'admin',
        ]);
        User::create([
            'name' => 'Ronie',
            'email' => 'vallecera@gmail.com',
            'password' => Hash::make('sample'),
            'role' => 'admin',
        ]);
    }
}
