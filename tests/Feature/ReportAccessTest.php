<?php

use App\Models\Instructor;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Strand;
use App\Models\Students;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function reportFixture(): array
{
    $strand = Strand::query()->create([
        'strand_code' => 'REP-ICT',
        'strand_name' => 'Reports ICT',
        'department' => 'SHS',
        'status' => 'active',
    ]);

    $section = Section::query()->create([
        'strand_id' => $strand->strand_id,
        'section_name' => 'Reports 11-A',
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => '2026-2027',
        'status' => 'active',
    ]);

    $instructorUser = User::factory()->create([
        'role' => 'instructor',
        'email' => 'reports.instructor@example.com',
    ]);
    $instructor = Instructor::query()->create([
        'user_id' => $instructorUser->user_id,
        'strand_id' => $strand->strand_id,
        'instructor_number' => 'REP-INS-001',
        'status' => 'active',
    ]);

    $schedule = Schedule::query()->create([
        'instructor_id' => $instructor->instructor_id,
        'section_id' => $section->section_id,
        'subject_code' => 'REP-101',
        'weekdays' => now()->format('l'),
        'time_start' => '08:00:00',
        'time_end' => '09:00:00',
        'room' => 'REPORT-LAB',
    ]);

    $studentUser = User::factory()->create([
        'role' => 'student',
        'email' => 'reports.student@example.com',
    ]);
    $parentUser = User::factory()->create([
        'role' => 'parent',
        'email' => 'reports.parent@example.com',
    ]);

    $student = Students::query()->create([
        'section_id' => $section->section_id,
        'strand_id' => $strand->strand_id,
        'student_number' => 'REP-STU-001',
        'first_name' => 'Reports',
        'last_name' => 'Student',
        'gender' => 'female',
        'email' => $studentUser->email,
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => '2026-2027',
        'rfid_tag' => 'REP-STUDENT-RFID',
        'status' => 'active',
        'face_images' => [],
    ]);

    DB::table('parent_student_links')->insert([
        'parent_user_id' => $parentUser->user_id,
        'student_id' => $student->student_id,
        'relationship' => 'parent',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('attendances')->insert([
        'student_id' => $student->student_id,
        'schedule_id' => $schedule->scheduled_id,
        'date' => now()->toDateString(),
        'time_in' => '08:01:00',
        'status' => 'present',
        'subject_code' => 'REP-101',
        'room' => 'REPORT-LAB',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return compact('studentUser', 'parentUser', 'instructorUser');
}

test('reports are role specific and include different chart types', function () {
    $fixture = reportFixture();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->where('role', 'admin')
            ->has('charts', fn (Assert $charts) => $charts
                ->where('0.type', 'donut')
                ->where('4.type', 'trend')
                ->etc()
            )
        );

    $this->actingAs($fixture['studentUser'])
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->where('role', 'student')
            ->where('charts.0.title', 'My Attendance by Status')
            ->where('charts.0.data.0.value', 1)
        );

    $this->actingAs($fixture['parentUser'])
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->where('role', 'parent')
            ->where('summaryCards.0.label', 'Linked Students')
            ->where('summaryCards.0.value', 1)
        );
});

test('console cannot access shared reports', function () {
    $console = User::factory()->create(['role' => 'console']);

    $this->actingAs($console)
        ->get(route('reports.index'))
        ->assertForbidden();
});
