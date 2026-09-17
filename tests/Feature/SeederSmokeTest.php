<?php

use Database\Seeders\AcademicYearSeeder;
use Database\Seeders\BorrowingSeeder;
use Database\Seeders\ClinicDashboardSeeder;
use Database\Seeders\ComlabUserSeeder;
use Database\Seeders\DataAccountSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSystemSeeder;
use Database\Seeders\EmergencySeeder;
use Database\Seeders\MessageSeeder;
use Database\Seeders\MinimalSeeder;
use Database\Seeders\SampleInstructorSeeder;
use Database\Seeders\SeniorHighAcademicSeeder;
use Database\Seeders\StudentParentAccountSeeder;
use Database\Seeders\SystemSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('each seeder can run without errors', function (string $seeder) {
    $this->seed($seeder);

    expect(true)->toBeTrue();
})->with([
    'AcademicYearSeeder' => [AcademicYearSeeder::class],
    'BorrowingSeeder' => [BorrowingSeeder::class],
    'ClinicDashboardSeeder' => [ClinicDashboardSeeder::class],
    'ComlabUserSeeder' => [ComlabUserSeeder::class],
    'DataAccountSeeder' => [DataAccountSeeder::class],
    'DatabaseSeeder' => [DatabaseSeeder::class],
    'DemoSystemSeeder' => [DemoSystemSeeder::class],
    'EmergencySeeder' => [EmergencySeeder::class],
    'MessageSeeder' => [MessageSeeder::class],
    'MinimalSeeder' => [MinimalSeeder::class],
    'SampleInstructorSeeder' => [SampleInstructorSeeder::class],
    'SeniorHighAcademicSeeder' => [SeniorHighAcademicSeeder::class],
    'StudentParentAccountSeeder' => [StudentParentAccountSeeder::class],
    'SystemSeeder' => [SystemSeeder::class],
    'UserSeeder' => [UserSeeder::class],
]);
