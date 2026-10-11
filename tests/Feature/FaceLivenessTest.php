<?php

use App\Models\User;
use App\Services\AwsFaceLivenessService;
use App\Services\FaceLivenessPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

test('face liveness reports disabled without calling AWS', function () {
    config()->set('services.aws_rekognition.liveness.enabled', false);
    $instructor = User::factory()->create(['role' => 'instructor']);

    $this->actingAs($instructor)
        ->postJson(route('faceLiveness.store'), [
            'purpose' => 'instructor_login',
            'subject_key' => (string) $instructor->user_id,
        ])
        ->assertStatus(409)
        ->assertJsonPath('enabled', false);
});

test('face liveness purpose is restricted by application role', function () {
    $instructor = User::factory()->create(['role' => 'instructor']);

    $this->actingAs($instructor)
        ->postJson(route('faceLiveness.store'), [
            'purpose' => 'attendance_student',
            'subject_key' => 'STUDENT-RFID-1',
        ])
        ->assertForbidden();
});

test('authorized liveness session is bound through the backend service', function () {
    $instructor = User::factory()->create(['role' => 'instructor']);

    $this->mock(AwsFaceLivenessService::class, function (MockInterface $mock) use ($instructor) {
        $mock->shouldReceive('createSession')
            ->once()
            ->withArgs(fn ($request, $purpose, $subjectKey, $subjectRole) => $request->user()->is($instructor)
                && $purpose === 'instructor_login'
                && $subjectKey === (string) $instructor->user_id
                && $subjectRole === FaceLivenessPolicy::INSTRUCTOR_ROLE)
            ->andReturn([
                'available' => true,
                'enabled' => true,
                'session_id' => '00000000-0000-4000-8000-000000000000',
                'region' => 'us-east-1',
                'identity_pool_id' => 'us-east-1:example',
            ]);
    });

    $this->actingAs($instructor)
        ->postJson(route('faceLiveness.store'), [
            'purpose' => 'instructor_login',
            'subject_key' => 'client-value-is-not-trusted',
        ])
        ->assertOk()
        ->assertJsonPath('region', 'us-east-1');
});

test('attendance instructor liveness role comes from the stored instructor account', function () {
    $console = User::factory()->create(['role' => 'console']);
    $instructor = User::factory()->create(['role' => 'instructor']);

    $this->mock(AwsFaceLivenessService::class, function (MockInterface $mock) use ($console, $instructor) {
        $mock->shouldReceive('createSession')
            ->once()
            ->withArgs(fn ($request, $purpose, $subjectKey, $subjectRole) => $request->user()->is($console)
                && $purpose === 'attendance_instructor'
                && $subjectKey === (string) $instructor->user_id
                && $subjectRole === FaceLivenessPolicy::INSTRUCTOR_ROLE)
            ->andReturn([
                'available' => true,
                'enabled' => true,
                'session_id' => '00000000-0000-4000-8000-000000000001',
                'region' => 'us-east-1',
                'identity_pool_id' => 'us-east-1:example',
            ]);
    });

    $this->actingAs($console)
        ->postJson(route('faceLiveness.store'), [
            'purpose' => 'attendance_instructor',
            'subject_key' => (string) $instructor->user_id,
        ])
        ->assertOk();
});

test('failed liveness result returns safe diagnostics for instructor testing', function () {
    $instructor = User::factory()->create(['role' => 'instructor']);

    $this->mock(AwsFaceLivenessService::class, function (MockInterface $mock) {
        $mock->shouldReceive('completeSession')
            ->once()
            ->andReturn([
                'ok' => false,
                'message' => 'Liveness verification did not pass. Please try again.',
                'status' => 'FAILED',
                'confidence' => 67.25,
                'threshold' => 70.0,
                'reference_image_received' => false,
            ]);
    });

    $this->actingAs($instructor)
        ->postJson(route('faceLiveness.show', ['sessionId' => 'test-session']))
        ->assertUnprocessable()
        ->assertJsonPath('status', 'FAILED')
        ->assertJsonPath('confidence', 67.25)
        ->assertJsonPath('threshold', 70)
        ->assertJsonPath('reference_image_received', false);
});

test('verified liveness reference token is purpose bound and single use', function () {
    Storage::fake('local');
    Storage::disk('local')->put('face-liveness/reference.jpg', 'reference-bytes');

    $request = Request::create('/');
    $request->setLaravelSession(app('session')->driver());
    $token = str_repeat('a', 64);
    $request->session()->put('face_liveness_tokens', [
        hash('sha256', $token) => [
            'purpose' => 'attendance_student',
            'subject_key' => 'STUDENT-RFID-1',
            'subject_role' => FaceLivenessPolicy::STUDENT_ROLE,
            'starter_user_id' => null,
            'path' => 'face-liveness/reference.jpg',
            'expires_at' => now()->addMinute()->timestamp,
        ],
    ]);

    $service = app(AwsFaceLivenessService::class);
    expect($service->consumeReferenceImage(
        $request,
        $token,
        'attendance_student',
        'STUDENT-RFID-1',
        FaceLivenessPolicy::STUDENT_ROLE,
    ))->toBe('data:image/jpeg;base64,'.base64_encode('reference-bytes'))
        ->and($service->consumeReferenceImage(
            $request,
            $token,
            'attendance_student',
            'STUDENT-RFID-1',
            FaceLivenessPolicy::STUDENT_ROLE,
        ))->toBeNull();
});

test('liveness thresholds are role based and invalid configuration uses safe defaults', function () {
    $policy = app(FaceLivenessPolicy::class);

    config()->set('services.aws_rekognition.liveness.minimum_confidence.student', '61.5');
    config()->set('services.aws_rekognition.liveness.minimum_confidence.instructor', 72);
    expect($policy->thresholdForRole('student'))->toBe(61.5)
        ->and($policy->thresholdForRole('instructor'))->toBe(72.0);

    config()->set('services.aws_rekognition.liveness.minimum_confidence.student', null);
    config()->set('services.aws_rekognition.liveness.minimum_confidence.instructor', 101);
    expect($policy->thresholdForRole('student'))->toBe(60.0)
        ->and($policy->thresholdForRole('instructor'))->toBe(70.0);
});

test('liveness reference tokens cannot be reused across subject roles', function () {
    Storage::fake('local');
    Storage::disk('local')->put('face-liveness/reference.jpg', 'reference-bytes');

    $request = Request::create('/');
    $request->setLaravelSession(app('session')->driver());
    $token = str_repeat('b', 64);
    $request->session()->put('face_liveness_tokens', [
        hash('sha256', $token) => [
            'purpose' => 'attendance_student',
            'subject_key' => 'STUDENT-RFID-1',
            'subject_role' => FaceLivenessPolicy::STUDENT_ROLE,
            'starter_user_id' => null,
            'path' => 'face-liveness/reference.jpg',
            'expires_at' => now()->addMinute()->timestamp,
        ],
    ]);

    expect(app(AwsFaceLivenessService::class)->consumeReferenceImage(
        $request,
        $token,
        'attendance_student',
        'STUDENT-RFID-1',
        FaceLivenessPolicy::INSTRUCTOR_ROLE,
    ))->toBeNull()
        ->and($request->session()->get('face_liveness_tokens'))->toBe([]);
    Storage::disk('local')->assertMissing('face-liveness/reference.jpg');
});
