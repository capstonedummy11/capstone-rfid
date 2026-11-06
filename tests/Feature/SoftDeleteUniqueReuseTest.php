<?php

use App\Models\AcademicYear;
use App\Models\Instructor;
use App\Models\Item;
use App\Models\Section;
use App\Models\Strand;
use App\Models\Students;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

function uniqueReuseAcademicContext(): array
{
    $year = AcademicYear::query()->create([
        'name' => '2026-2027',
        'starts_on' => '2026-06-01',
        'ends_on' => '2027-03-31',
        'status' => AcademicYear::STATUS_ACTIVE,
        'active_semester' => '1st Semester',
    ]);
    $strand = Strand::query()->create([
        'strand_code' => 'REUSE',
        'strand_name' => 'Unique Reuse Strand',
        'department' => 'SHS',
        'status' => 'active',
    ]);
    $section = Section::query()->create([
        'academic_year_id' => $year->academic_year_id,
        'strand_id' => $strand->strand_id,
        'section_name' => 'REUSE 11-A',
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => $year->name,
        'status' => 'active',
    ]);

    return compact('year', 'strand', 'section');
}

test('instructor unique values are reusable after deletion but active duplicates fail', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    ['strand' => $strand] = uniqueReuseAcademicContext();
    $payload = [
        'instructor_number' => 'INS-REUSE-001',
        'first_name' => 'Reusable',
        'middle_name' => null,
        'last_name' => 'Instructor',
        'email' => 'reusable.instructor@example.test',
        'phone' => '09170000111',
        'gender' => 'female',
        'strand_id' => $strand->strand_id,
        'rfid_tag' => 'RFID-INS-REUSE-001',
        'status' => 'active',
    ];

    $this->actingAs($admin)->post(route('admin.instructors.store'), $payload)
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->actingAs($admin)->post(route('admin.instructors.store'), $payload)
        ->assertSessionHasErrors(['instructor_number', 'email', 'rfid_tag']);

    $original = Instructor::query()->where('instructor_number', $payload['instructor_number'])->firstOrFail();
    $originalUserId = $original->user_id;

    $this->actingAs($admin)->delete(route('admin.instructors.destroy', $original->instructor_id))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertSoftDeleted('instructors', ['instructor_id' => $original->instructor_id]);
    $this->assertSoftDeleted('users', ['user_id' => $originalUserId]);

    $this->actingAs($admin)->post(route('admin.instructors.store'), $payload)
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors()
        ->assertSessionHas('success');

    expect(Instructor::query()->where('instructor_number', $payload['instructor_number'])->count())->toBe(1)
        ->and(Instructor::withTrashed()->where('instructor_number', $payload['instructor_number'])->count())->toBe(2)
        ->and(User::query()->where('email', $payload['email'])->count())->toBe(1)
        ->and(User::withTrashed()->where('email', $payload['email'])->count())->toBe(2);
});

test('subject code is reusable after deletion but an active duplicate fails', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    ['section' => $section] = uniqueReuseAcademicContext();
    $payload = [
        'section_id' => $section->section_id,
        'user_id' => null,
        'subject_name' => 'Reusable Subject',
        'subject_code' => 'SUB-REUSE-001',
        'subject_description' => 'Unique reuse regression coverage.',
        'department' => 'ICT',
        'unit' => 3,
        'semester' => '1st Semester',
    ];

    $this->actingAs($admin)->post(route('admin.subjects.store'), $payload)
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->actingAs($admin)->post(route('admin.subjects.store'), $payload)
        ->assertSessionHasErrors(['subject_code']);

    $subject = Subject::query()->where('subject_code', $payload['subject_code'])->firstOrFail();
    $offering = SubjectOffering::query()->where('subject_id', $subject->subject_id)->firstOrFail();

    $this->actingAs($admin)->delete(route('admin.subjects.offerings.destroy', $offering))
        ->assertRedirect()
        ->assertSessionHas('success');
    $this->actingAs($admin)->delete(route('admin.subjects.destroy', $subject->subject_id))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertSoftDeleted('subjects', ['subject_id' => $subject->subject_id]);

    $this->actingAs($admin)->post(route('admin.subjects.store'), $payload)
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors()
        ->assertSessionHas('success');

    expect(Subject::query()->where('subject_code', $payload['subject_code'])->count())->toBe(1)
        ->and(Subject::withTrashed()->where('subject_code', $payload['subject_code'])->count())->toBe(2);
});

test('student unique values are reusable after deletion but active duplicates fail', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    ['year' => $year, 'strand' => $strand, 'section' => $section] = uniqueReuseAcademicContext();
    $payload = [
        'student_number' => 'STU-REUSE-001',
        'first_name' => 'Reusable',
        'middle_name' => null,
        'last_name' => 'Student',
        'email' => 'reusable.student@example.test',
        'phone' => '09170000222',
        'gender' => 'female',
        'strand_id' => $strand->strand_id,
        'section_id' => $section->section_id,
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => $year->name,
        'rfid_tag' => 'RFID-STU-REUSE-001',
        'status' => 'active',
    ];

    $this->actingAs($admin)->post(route('admin.students.store'), $payload)
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->actingAs($admin)->post(route('admin.students.store'), $payload)
        ->assertSessionHasErrors(['student_number', 'email', 'rfid_tag']);

    $student = Students::query()->where('student_number', $payload['student_number'])->firstOrFail();
    $this->actingAs($admin)->delete(route('admin.students.destroy', $student->student_id))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->actingAs($admin)->post(route('admin.students.store'), $payload)
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors()
        ->assertSessionHas('success');

    expect(Students::query()->where('student_number', $payload['student_number'])->count())->toBe(1)
        ->and(Students::withTrashed()->where('student_number', $payload['student_number'])->count())->toBe(2);
});

test('strand code barcode and managed user email are reusable only after deletion', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $strandPayload = [
        'strand_code' => 'DELETE-REUSE',
        'strand_name' => 'Delete Reuse Strand',
        'department' => 'SHS',
        'status' => 'active',
    ];
    $this->actingAs($admin)->post(route('admin.strands.store'), $strandPayload)->assertSessionHas('success');
    $this->actingAs($admin)->post(route('admin.strands.store'), $strandPayload)->assertSessionHasErrors(['strand_code']);
    $strand = Strand::query()->where('strand_code', $strandPayload['strand_code'])->firstOrFail();
    $this->actingAs($admin)->delete(route('admin.strands.destroy', $strand->strand_id))->assertSessionHas('success');
    $this->actingAs($admin)->post(route('admin.strands.store'), $strandPayload)->assertSessionDoesntHaveErrors();

    SystemSetting::setBoolean(SystemSetting::INVENTORY_ENABLED, true);
    $itemPayload = [
        'barcode' => 'BARCODE-REUSE-001',
        'name' => 'Reusable Barcode Item',
        'sku' => 'SKU-REUSE-001',
        'description' => 'Reusable item barcode test.',
        'status' => 'Available',
    ];
    $this->actingAs($admin)->post(route('admin.items.store'), $itemPayload)->assertSessionHas('success');
    $this->actingAs($admin)->post(route('admin.items.store'), $itemPayload)->assertSessionHasErrors(['barcode']);
    $item = Item::query()->where('barcode', $itemPayload['barcode'])->firstOrFail();
    $this->actingAs($admin)->delete(route('admin.items.destroy', $item))->assertSessionHas('success');
    $this->actingAs($admin)->post(route('admin.items.store'), $itemPayload)->assertSessionDoesntHaveErrors();

    $root = User::factory()->create(['role' => 'admin', 'is_root_admin' => true]);
    $userPayload = [
        'name' => 'Reusable Registrar',
        'email' => 'reusable.registrar@example.test',
        'password' => 'StrongPass123!',
        'password_confirmation' => 'StrongPass123!',
        'role' => 'registrar',
        'phone' => '09170000333',
        'is_root_admin' => false,
    ];
    $this->actingAs($root)->post(route('admin.users.store'), $userPayload)->assertSessionHas('success');
    $this->actingAs($root)->post(route('admin.users.store'), $userPayload)->assertSessionHasErrors(['email']);
    $user = User::query()->where('email', $userPayload['email'])->firstOrFail();
    $this->actingAs($root)->delete(route('admin.users.destroy', $user->user_id))->assertSessionHas('success');
    $this->actingAs($root)->post(route('admin.users.store'), $userPayload)->assertSessionDoesntHaveErrors();
});
