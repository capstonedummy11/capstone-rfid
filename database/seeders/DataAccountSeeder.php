<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DataAccountSeeder extends Seeder
{
    // @function run: Pinapatakbo ang Data Account Seeder task.
    // @useIn run: php artisan db:seed
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
