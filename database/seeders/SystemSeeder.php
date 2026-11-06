<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SystemSeeder extends Seeder
{
    // @function run: Pinapatakbo ang System Seeder task.
    // @useIn run: php artisan db:seed
    /**
     * Seed system reference records and demo operational data.
     */
    public function run(): void
    {
        $this->call([
            AcademicYearSeeder::class,
            EmergencySeeder::class,
            DemoSystemSeeder::class,
            ClinicDashboardSeeder::class,
            MessageSeeder::class,
        ]);
    }
}
