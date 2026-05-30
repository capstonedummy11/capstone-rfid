<?php

namespace Database\Seeders;

use App\Models\ClinicCase;
use App\Models\EmergencyAlert;
use App\Models\EmergencyType;
use App\Models\PatientHistory;
use App\Models\Schedule;
use App\Models\Students;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ClinicDashboardSeeder extends Seeder
{
    public function run(): void
    {
        $clinicUser = User::updateOrCreate(
            ['email' => 'clinic@sample.com'],
            [
                'name' => 'Clinic Staff',
                'password' => Hash::make('sample'),
                'role' => 'clinic',
                'rfid_tag' => 'RFID-CLINIC-SAMPLE',
            ],
        );

        $instructor = User::where('email', 'instructor@sample.com')->first();
        $schedule = Schedule::query()->orderBy('scheduled_id')->first();
        $students = Students::query()
            ->whereIn('student_number', ['SHS-ICT-1101', 'SHS-ICT-1102', 'SHS-ICT-1103'])
            ->orderBy('student_number')
            ->get();

        if ($students->isEmpty()) {
            $this->command?->warn('ClinicDashboardSeeder skipped: no demo students found. Run DemoSystemSeeder first.');
            return;
        }

        $types = [
            'RFID Timed In' => EmergencyType::updateOrCreate(
                ['name' => 'Fainting'],
                [
                    'category' => 'clinic',
                    'default_message' => 'Clinic emergency: student/person fainted. Please respond immediately.',
                    'is_active' => true,
                    'sort_order' => 2,
                ],
            ),
            'Manual Report' => EmergencyType::updateOrCreate(
                ['name' => 'Asthma/Allergy'],
                [
                    'category' => 'clinic',
                    'default_message' => 'Clinic emergency: asthma or allergy distress reported. Please respond immediately.',
                    'is_active' => true,
                    'sort_order' => 4,
                ],
            ),
            'Panel Alert' => EmergencyType::updateOrCreate(
                ['name' => 'General Distress'],
                [
                    'category' => 'clinic',
                    'default_message' => 'Clinic emergency: general distress reported. Please assess immediately.',
                    'is_active' => true,
                    'sort_order' => 5,
                ],
            ),
        ];

        $alerts = [
            [
                'student' => $students[0],
                'type' => $types['RFID Timed In'],
                'sub_type' => 'RFID Timed In',
                'severity' => 'urgent',
                'status' => 'acknowledged',
                'message' => 'Demo clinic alert from attendance control panel.',
                'symptoms' => 'Dizziness after class activity.',
                'case_status' => 'monitoring',
                'minutes_ago' => 120,
            ],
            [
                'student' => $students[1] ?? $students[0],
                'type' => $types['Manual Report'],
                'sub_type' => 'Manual Report',
                'severity' => 'critical',
                'status' => 'open',
                'message' => 'Student reported breathing difficulty in the laboratory.',
                'symptoms' => 'Shortness of breath and chest tightness.',
                'case_status' => 'open',
                'minutes_ago' => 45,
            ],
            [
                'student' => $students[2] ?? $students[0],
                'type' => $types['Panel Alert'],
                'sub_type' => 'Panel Alert',
                'severity' => 'urgent',
                'status' => 'resolved',
                'message' => 'Student requested clinic assistance after feeling weak.',
                'symptoms' => 'Weakness and headache.',
                'case_status' => 'resolved',
                'minutes_ago' => 1440,
            ],
        ];

        foreach ($alerts as $entry) {
            $student = $entry['student'];
            $patientName = trim($student->first_name . ' ' . $student->last_name);

            $alert = EmergencyAlert::updateOrCreate(
                [
                    'room' => $schedule?->room ?? 'Clinic',
                    'subject_code' => $schedule?->subject_code,
                    'message' => $entry['message'],
                ],
                [
                    'emergency_type_id' => $entry['type']->emergency_type_id,
                    'triggered_by_user_id' => $instructor?->user_id,
                    'schedule_id' => $schedule?->scheduled_id,
                    'triggered_by_name' => $instructor?->name ?? $patientName,
                    'sub_type' => $entry['sub_type'],
                    'severity' => $entry['severity'],
                    'status' => $entry['status'],
                    'metadata' => [
                        'student_id' => $student->student_id,
                        'student_rfid' => $student->rfid_tag,
                        'symptoms' => $entry['symptoms'],
                    ],
                    'resolved_at' => $entry['status'] === 'resolved' ? now()->subMinutes($entry['minutes_ago'] - 30) : null,
                    'created_at' => now()->subMinutes($entry['minutes_ago']),
                    'updated_at' => now()->subMinutes($entry['minutes_ago']),
                ],
            );

            ClinicCase::updateOrCreate(
                [
                    'emergency_alert_id' => $alert->emergency_alert_id,
                    'patient_name' => $patientName,
                ],
                [
                    'student_id' => $student->student_id,
                    'handled_by_user_id' => $clinicUser->user_id,
                    'patient_type' => 'student',
                    'case_type' => $entry['type']->name,
                    'symptoms' => $entry['symptoms'],
                    'action_taken' => $entry['status'] === 'open' ? null : 'Clinic response dispatched.',
                    'notes' => 'Seeded for clinic dashboard emergency detail cards.',
                    'status' => $entry['case_status'],
                    'occurred_at' => now()->subMinutes($entry['minutes_ago']),
                ],
            );

            PatientHistory::updateOrCreate(
                [
                    'student_id' => $student->student_id,
                    'summary' => $entry['type']->name . ' - ' . $entry['sub_type'],
                ],
                [
                    'recorded_by_user_id' => $clinicUser->user_id,
                    'patient_type' => 'student',
                    'patient_name' => $patientName,
                    'notes' => $entry['symptoms'],
                    'occurred_at' => now()->subMinutes($entry['minutes_ago']),
                ],
            );
        }
    }
}
