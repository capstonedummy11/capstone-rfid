<?php

use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\Strand;
use App\Models\StudentEnrollment;
use App\Models\Students;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

test('admin-created academic setup data is reused across strand section student and subject pages', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $year = AcademicYear::query()->create([
        'name' => '2026-2027',
        'starts_on' => '2026-06-01',
        'ends_on' => '2027-03-31',
        'status' => AcademicYear::STATUS_ACTIVE,
        'active_semester' => '1st Semester',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.strands.store'), [
            'strand_code' => 'XPT',
            'strand_name' => 'Cross Page Testing',
            'department' => 'SHS',
            'status' => 'active',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $strand = Strand::query()->where('strand_code', 'XPT')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.sections.index', ['strand' => $strand->strand_id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Admin/Sections')
            ->has('strandOptions', 1)
            ->where('strandOptions.0.strand_id', $strand->strand_id)
            ->where('strandOptions.0.strand_code', 'XPT')
        );

    $this->actingAs($admin)
        ->post(route('admin.sections.store'), [
            'section_name' => 'XPT 11-A',
            'strand_id' => $strand->strand_id,
            'year_level' => 11,
            'semester' => '1st Semester',
            'school_year' => $year->name,
            'status' => 'active',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $section = Section::query()->where('section_name', 'XPT 11-A')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.students.index', [
            'strand' => $strand->strand_id,
            'section' => $section->section_id,
            'school_year' => $year->name,
            'semester' => '1st Semester',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Admin/Students')
            ->has('strandOptions', 1)
            ->where('strandOptions.0.strand_id', $strand->strand_id)
            ->where('strandOptions.0.strand_code', 'XPT')
            ->has('sectionOptions', 1)
            ->where('sectionOptions.0.section_id', $section->section_id)
            ->where('sectionOptions.0.strand_id', $strand->strand_id)
            ->where('sectionOptions.0.section_name', 'XPT 11-A')
        );

    $this->actingAs($admin)
        ->post(route('admin.students.store'), [
            'student_number' => 'XPT-2026-001',
            'first_name' => 'Cross',
            'middle_name' => null,
            'last_name' => 'Page',
            'email' => 'cross.page.student@example.test',
            'phone' => null,
            'gender' => 'female',
            'strand_id' => $strand->strand_id,
            'section_id' => $section->section_id,
            'year_level' => 11,
            'semester' => '1st Semester',
            'school_year' => $year->name,
            'rfid_tag' => 'XPT-RFID-001',
            'status' => 'active',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $student = Students::query()->where('student_number', 'XPT-2026-001')->firstOrFail();

    $this->assertDatabaseHas('student_enrollments', [
        'student_id' => $student->student_id,
        'academic_year_id' => $year->academic_year_id,
        'strand_id' => $strand->strand_id,
        'section_id' => $section->section_id,
        'year_level' => 11,
        'semester' => '1st Semester',
        'status' => 'enrolled',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.students.index', [
            'search' => 'XPT-2026-001',
            'school_year' => $year->name,
            'semester' => '1st Semester',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Admin/Students')
            ->has('students', 1)
            ->where('students.0.student_number', 'XPT-2026-001')
            ->where('students.0.strand_id', $strand->strand_id)
            ->where('students.0.strand_code', 'XPT')
            ->where('students.0.section_id', $section->section_id)
            ->where('students.0.section_name', 'XPT 11-A')
            ->where('students.0.school_year', $year->name)
        );

    $this->actingAs($admin)
        ->post(route('admin.subjects.store'), [
            'section_id' => $section->section_id,
            'user_id' => null,
            'subject_name' => 'Cross Page Subject',
            'subject_code' => 'XPT-101',
            'subject_description' => 'Created from the shared cross-page academic setup test.',
            'department' => 'SHS',
            'unit' => 3,
            'semester' => '1st Semester',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $subject = Subject::query()->where('subject_code', 'XPT-101')->firstOrFail();

    $this->assertDatabaseHas('subject_offerings', [
        'subject_id' => $subject->subject_id,
        'academic_year_id' => $year->academic_year_id,
        'section_id' => $section->section_id,
        'semester' => '1st Semester',
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.subjects.index', [
            'search' => 'XPT-101',
            'academic_year_id' => $year->academic_year_id,
            'semester' => '1st Semester',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Admin/Subjects')
            ->has('subjects', 1)
            ->where('subjects.0.subject_code', 'XPT-101')
            ->where('subjects.0.offerings.0.academic_year', $year->name)
            ->where('subjects.0.offerings.0.section_id', $section->section_id)
            ->where('subjects.0.offerings.0.section_name', 'XPT 11-A')
            ->where('sectionOptions.0.section_id', $section->section_id)
            ->where('sectionOptions.0.academic_year_id', $year->academic_year_id)
            ->where('sectionOptions.0.year_level', 11)
        );

    expect(StudentEnrollment::query()->where('student_id', $student->student_id)->count())->toBe(1)
        ->and(SubjectOffering::query()->where('subject_id', $subject->subject_id)->count())->toBe(1);
});
