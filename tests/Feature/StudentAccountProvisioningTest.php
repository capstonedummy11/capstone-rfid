<?php

use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\Strand;
use App\Models\Students;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('student created by an admin can sign in with the submitted email and generated password', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $academicYear = AcademicYear::query()->create([
        'name' => '2026-2027',
        'starts_on' => '2026-06-01',
        'ends_on' => '2027-03-31',
        'status' => AcademicYear::STATUS_ACTIVE,
        'active_semester' => '1st Semester',
    ]);
    $strand = Strand::query()->create([
        'strand_code' => 'ICT-E2E',
        'strand_name' => 'Information and Communications Technology',
        'department' => 'Senior High School',
        'status' => 'active',
    ]);
    $section = Section::query()->create([
        'academic_year_id' => $academicYear->academic_year_id,
        'strand_id' => $strand->strand_id,
        'section_name' => 'ICT 11-E2E',
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => $academicYear->name,
        'status' => 'active',
    ]);

    $studentData = [
        'student_number' => 'SHS-E2E-0001',
        'first_name' => 'Ana Maria',
        'middle_name' => 'Reyes',
        'last_name' => 'Dela Cruz',
        'email' => 'ana.delacruz@student.example',
        'phone' => '09171234567',
        'gender' => 'female',
        'strand_id' => $strand->strand_id,
        'section_id' => $section->section_id,
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => $academicYear->name,
        'rfid_tag' => 'RFID-E2E-0001',
        'status' => 'active',
    ];
    $temporaryPassword = 'anamariadelacruz';

    $this->actingAs($admin)
        ->post(route('admin.students.store'), $studentData)
        ->assertRedirect()
        ->assertSessionHas('success', fn (string $message) => str_contains($message, $temporaryPassword));

    $student = Students::query()
        ->where('student_number', $studentData['student_number'])
        ->firstOrFail();
    $studentUser = User::query()
        ->where('email', $studentData['email'])
        ->firstOrFail();

    expect($student->first_name)->toBe($studentData['first_name'])
        ->and($student->last_name)->toBe($studentData['last_name'])
        ->and($studentUser->name)->toBe('Ana Maria Dela Cruz')
        ->and($studentUser->role)->toBe('student')
        ->and($studentUser->must_change_password)->toBeTrue()
        ->and(Hash::check($temporaryPassword, $studentUser->password))->toBeTrue();

    $this->assertDatabaseHas('student_enrollments', [
        'student_id' => $student->student_id,
        'academic_year_id' => $academicYear->academic_year_id,
        'section_id' => $section->section_id,
        'strand_id' => $strand->strand_id,
        'year_level' => 11,
        'semester' => '1st Semester',
        'status' => 'enrolled',
    ]);

    $this->post(route('logout'))->assertRedirect('/');
    $this->assertGuest();

    $this->post(route('student-parent.login.store'), [
        'email' => $studentData['email'],
        'password' => $temporaryPassword,
    ])->assertRedirect(route('student-parent.dashboard'));

    $this->assertAuthenticatedAs($studentUser);

    $this->get(route('student-parent.dashboard'))
        ->assertRedirect(route('password.first-login'));

    $this->put(route('password.first-login.update'), [
        'password' => 'PrivateStudentPassword123!',
        'password_confirmation' => 'PrivateStudentPassword123!',
    ])->assertRedirect(route('dashboard'));

    $this->get(route('student-parent.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('StudentParent/Dashboard')
            ->where('student.student_number', $studentData['student_number'])
            ->where('student.email', $studentData['email'])
        );
});
