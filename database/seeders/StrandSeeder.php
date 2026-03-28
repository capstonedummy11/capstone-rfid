<?php

namespace Database\Seeders;

use App\Models\Strand;
use Illuminate\Database\Seeder;

class StrandSeeder extends Seeder
{
    public function run(): void
    {
        $strands = [
            [
                'strand_code' => 'STEM',
                'strand_name' => 'Science, Technology, Engineering and Mathematics',
                'department' => 'Senior High School',
                'status' => 'active',
            ],
            [
                'strand_code' => 'ABM',
                'strand_name' => 'Accountancy, Business and Management',
                'department' => 'Senior High School',
                'status' => 'active',
            ],
            [
                'strand_code' => 'HUMSS',
                'strand_name' => 'Humanities and Social Sciences',
                'department' => 'Senior High School',
                'status' => 'active',
            ],
        ];

        foreach ($strands as $strand) {
            Strand::updateOrCreate(
                ['strand_code' => $strand['strand_code']],
                $strand,
            );
        }
    }
}