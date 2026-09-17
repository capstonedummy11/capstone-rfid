<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Keep the default seed order stable while the wrapper seeders remain available for partial seeding.
        $this->call([
            UserSeeder::class,
            AcademicYearSeeder::class,
            EmergencySeeder::class,
            ComlabUserSeeder::class,
            DemoSystemSeeder::class,
            StudentParentAccountSeeder::class,
            ClinicDashboardSeeder::class,
            MessageSeeder::class,
        ]);
    }
}
