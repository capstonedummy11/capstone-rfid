<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DataAccountSeeder extends Seeder
{
    /**
     * Seed login accounts, console accounts, and portal account links.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ComlabUserSeeder::class,
            StudentParentAccountSeeder::class,
        ]);
    }
}
