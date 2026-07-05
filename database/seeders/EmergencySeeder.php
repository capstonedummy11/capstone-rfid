<?php

namespace Database\Seeders;

use App\Models\EmergencyHotline;
use App\Models\EmergencyType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EmergencySeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'clinic@sample.com'],
            [
                'name' => 'Clinic Staff',
                'email' => 'clinic@sample.com',
                'password' => Hash::make('sample'),
                'role' => 'clinic',
            ],
        );

        $types = [
            ['name' => 'Seizure', 'category' => 'clinic', 'default_message' => 'Clinic emergency: possible seizure. Please respond immediately.'],
            ['name' => 'Fainting', 'category' => 'clinic', 'default_message' => 'Clinic emergency: student/person fainted. Please respond immediately.'],
            ['name' => 'Severe Bleeding', 'category' => 'clinic', 'default_message' => 'Clinic emergency: severe bleeding reported. Bring first-aid supplies immediately.'],
            ['name' => 'Asthma/Allergy', 'category' => 'clinic', 'default_message' => 'Clinic emergency: asthma or allergy distress reported. Please respond immediately.'],
            ['name' => 'General Distress', 'category' => 'clinic', 'default_message' => 'Clinic emergency: general distress reported. Please assess immediately.'],
            ['name' => 'Fire Emergency', 'category' => 'disaster', 'default_message' => 'Fire emergency reported. Follow evacuation and emergency response protocol.'],
            ['name' => 'Earthquake', 'category' => 'disaster', 'default_message' => 'Earthquake emergency reported. Follow drop, cover, hold and evacuation protocol.'],
            ['name' => 'Other Emergency', 'category' => 'general', 'default_message' => 'Emergency assistance requested. Please respond immediately.'],
        ];

        foreach ($types as $index => $type) {
            EmergencyType::updateOrCreate(
                ['name' => $type['name']],
                [
                    ...$type,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }

        $hotlines = [
            [
                'name' => 'School Clinic',
                'category' => 'clinic',
                'phone_number' => 'Local 101',
                'contact_person' => 'Clinic Staff',
                'sms_enabled' => false,
                'notes' => 'Primary on-campus medical response contact.',
            ],
            [
                'name' => 'Security Office',
                'category' => 'security',
                'phone_number' => 'Local 102',
                'contact_person' => 'Security Desk',
                'sms_enabled' => false,
                'notes' => 'Use for crowd control, gate assistance, and urgent campus response.',
            ],
            [
                'name' => 'Barangay Emergency',
                'category' => 'external',
                'phone_number' => '911',
                'contact_person' => 'Emergency Dispatch',
                'sms_enabled' => false,
                'notes' => 'External emergency dispatch placeholder. Replace with official local number before production.',
            ],
        ];

        foreach ($hotlines as $index => $hotline) {
            EmergencyHotline::query()->updateOrCreate(
                ['name' => $hotline['name']],
                [
                    ...$hotline,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }
    }
}
