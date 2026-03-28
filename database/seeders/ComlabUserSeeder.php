<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ComlabUserSeeder extends Seeder
{
  /**
   * Seed COMLAB user accounts (COMLAB 1 to COMLAB 5).
   */
  public function run(): void
  {
    for ($i = 1; $i <= 5; $i++) {
      User::updateOrCreate(
        ['email' => "comlab{$i}@example.com"],
        [
          'name' => "COMLAB {$i}",
          'email' => "comlab{$i}@example.com",
          'password' => Hash::make('1234'),
          'role' => 'user',
        ]
      );
    }
  }
}
