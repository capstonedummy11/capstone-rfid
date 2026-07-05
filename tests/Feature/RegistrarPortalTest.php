<?php

use App\Models\Instructor;
use App\Models\Section;
use App\Models\Strand;
use App\Models\Students;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function registrarFixture(): array
{
    $strand = Strand::query()->create([
        'strand_code' => 'ICT',
        'strand_name' => 'Information and Communications Technology',
        'department' => 'Senior High School',
        'status' => 'active',
    ]);

    $section = Section::query()->create([
        'strand_id' => $strand->strand_id,
        'section_name' => 'ICT 11-A',
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => '2026-2027',
        'status' => 'active',
    ]);

    $student = Students::query()->create([
        'section_id' => $section->section_id,
        'strand_id' => $strand->strand_id,
        'student_number' => 'SHS-REG-001',
        'first_name' => 'Andrea',
        'last_name' => 'Santos',
        'gender' => 'female',
        'email' => 'registrar.student@example.com',
        'phone' => '09170000001',
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => '2026-2027',
        'status' => 'active',
    ]);

    $facultyUser = User::factory()->create([
        'name' => 'Instructor One',
        'email' => 'registrar.instructor@example.com',
        'role' => 'instructor',
    ]);

    Instructor::query()->create([
        'user_id' => $facultyUser->user_id,
        'strand_id' => $strand->strand_id,
        'instructor_number' => 'INS-REG-001',
        'status' => 'active',
    ]);

    $registrar = User::factory()->create([
        'name' => 'Registrar User',
        'email' => 'registrar.user@example.com',
        'role' => 'registrar',
    ]);

    return compact('strand', 'section', 'student', 'facultyUser', 'registrar');
}

test('registrar can view dashboard and biometric enrollment pages', function () {
    $fixture = registrarFixture();

    $this->actingAs($fixture['registrar'])
        ->get(route('registrar.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Registrar/Dashboard')
            ->where('stats.total', 2)
            ->where('stats.students', 1)
            ->where('stats.faculty', 1)
        );

    $this->actingAs($fixture['registrar'])
        ->get(route('registrar.biometric-enrollment'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Registrar/BiometricEnrollment')
            ->has('people', 2)
        );

    $this->actingAs($fixture['registrar'])
        ->get(route('registrar.instructor-face-enrollment'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Registrar/InstructorFaceEnrollment')
            ->has('people', 1)
        );
});

test('registrar assigns student rfid and writes enrollment plus activity logs', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $fixture = registrarFixture();

    $this->actingAs($fixture['registrar'])
        ->put(route('registrar.students.rfid', $fixture['student']), [
            'rfid_tag' => 'RFID-STUDENT-REG-001',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Student RFID card assigned.');

    expect($fixture['student']->fresh()->rfid_tag)->toBe('RFID-STUDENT-REG-001');

    $this->assertDatabaseHas('registrar_enrollment_logs', [
        'registrar_user_id' => $fixture['registrar']->user_id,
        'action' => 'rfid',
        'person_type' => 'student',
        'person_id' => $fixture['student']->student_id,
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $fixture['registrar']->user_id,
        'action' => 'update',
        'table_name' => 'students',
    ]);
});

test('registrar cannot assign duplicate rfid across students and faculty', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $fixture = registrarFixture();
    $fixture['facultyUser']->update(['rfid_tag' => 'DUPLICATE-RFID']);

    $this->actingAs($fixture['registrar'])
        ->from(route('registrar.biometric-enrollment'))
        ->put(route('registrar.students.rfid', $fixture['student']), [
            'rfid_tag' => 'DUPLICATE-RFID',
        ])
        ->assertRedirect(route('registrar.biometric-enrollment'))
        ->assertSessionHasErrors('rfid_tag');
});

test('registrar uploads student face image and writes enrollment plus activity logs', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    Storage::fake('public');
    $fixture = registrarFixture();

    $this->actingAs($fixture['registrar'])
        ->post(route('registrar.students.face', $fixture['student']), [
            'image' => UploadedFile::fake()->image('student-face.jpg'),
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Student face image submitted.');

    expect($fixture['student']->fresh()->face_images)->toHaveCount(1);

    $this->assertDatabaseHas('registrar_enrollment_logs', [
        'registrar_user_id' => $fixture['registrar']->user_id,
        'action' => 'face',
        'person_type' => 'student',
        'person_id' => $fixture['student']->student_id,
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $fixture['registrar']->user_id,
        'action' => 'upload',
        'table_name' => 'students',
    ]);
});

test('registrar uploads instructor face image for instructor login verification', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    Storage::fake('public');
    $fixture = registrarFixture();

    $this->actingAs($fixture['registrar'])
        ->post(route('registrar.faculty.face', $fixture['facultyUser']), [
            'image' => UploadedFile::fake()->image('instructor-face.jpg'),
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Faculty face image submitted.');

    expect($fixture['facultyUser']->fresh()->face_images)->toHaveCount(1);

    $this->assertDatabaseHas('registrar_enrollment_logs', [
        'registrar_user_id' => $fixture['registrar']->user_id,
        'action' => 'face',
        'person_type' => 'faculty',
        'person_id' => $fixture['facultyUser']->user_id,
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $fixture['registrar']->user_id,
        'action' => 'upload',
        'table_name' => 'users',
    ]);
});

test('registrar removes student and instructor face images and writes shared activity logs', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    Storage::fake('public');
    $fixture = registrarFixture();
    Storage::disk('public')->put('student_faces/remove.jpg', 'student image');
    Storage::disk('public')->put('instructor_faces/remove.jpg', 'instructor image');
    $fixture['student']->update(['face_images' => ['student_faces/remove.jpg']]);
    $fixture['facultyUser']->update(['face_images' => ['instructor_faces/remove.jpg']]);

    $this->actingAs($fixture['registrar'])
        ->delete(route('registrar.students.face.delete', [
            'student' => $fixture['student'],
            'index' => 0,
        ]))
        ->assertRedirect()
        ->assertSessionHas('success', 'Student face image removed.');

    $this->actingAs($fixture['registrar'])
        ->delete(route('registrar.faculty.face.delete', [
            'user' => $fixture['facultyUser'],
            'index' => 0,
        ]))
        ->assertRedirect()
        ->assertSessionHas('success', 'Faculty face image removed.');

    expect($fixture['student']->fresh()->face_images)->toBe([]);
    expect($fixture['facultyUser']->fresh()->face_images)->toBe([]);
    Storage::disk('public')->assertMissing('student_faces/remove.jpg');
    Storage::disk('public')->assertMissing('instructor_faces/remove.jpg');

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $fixture['registrar']->user_id,
        'action' => 'delete',
        'table_name' => 'students',
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $fixture['registrar']->user_id,
        'action' => 'delete',
        'table_name' => 'users',
    ]);
});

test('admin student face image maintenance endpoint is no longer available', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    Storage::fake('public');
    $fixture = registrarFixture();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post('/admin/students/'.$fixture['student']->student_id.'/face-images', [
            'image' => UploadedFile::fake()->image('student-face.jpg'),
        ])
        ->assertNotFound();
});

test('registrar face upload enforces five image limit', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    Storage::fake('public');
    $fixture = registrarFixture();
    $fixture['student']->update([
        'face_images' => [
            'student_faces/1.jpg',
            'student_faces/2.jpg',
            'student_faces/3.jpg',
            'student_faces/4.jpg',
            'student_faces/5.jpg',
        ],
    ]);

    $this->actingAs($fixture['registrar'])
        ->from(route('registrar.biometric-enrollment'))
        ->post(route('registrar.students.face', $fixture['student']), [
            'image' => UploadedFile::fake()->image('sixth-face.jpg'),
        ])
        ->assertRedirect(route('registrar.biometric-enrollment'))
        ->assertSessionHasErrors('image');

    expect($fixture['student']->fresh()->face_images)->toHaveCount(5);
});
