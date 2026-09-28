<?php

use App\Models\User;
use App\Services\AwsFaceLivenessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

test('face liveness reports disabled without calling AWS', function () {
    config()->set('services.aws_rekognition.liveness.enabled', false);
    $console = User::factory()->create(['role' => 'console']);

    $this->actingAs($console)
        ->postJson(route('faceLiveness.store'), [
            'purpose' => 'attendance_student',
            'subject_key' => 'STUDENT-RFID-1',
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
    $console = User::factory()->create(['role' => 'console']);

    $this->mock(AwsFaceLivenessService::class, function (MockInterface $mock) use ($console) {
        $mock->shouldReceive('createSession')
            ->once()
            ->withArgs(fn ($request, $purpose, $subjectKey) => $request->user()->is($console)
                && $purpose === 'attendance_student'
                && $subjectKey === 'STUDENT-RFID-1')
            ->andReturn([
                'available' => true,
                'enabled' => true,
                'session_id' => '00000000-0000-4000-8000-000000000000',
                'region' => 'us-east-1',
                'identity_pool_id' => 'us-east-1:example',
            ]);
    });

    $this->actingAs($console)
        ->postJson(route('faceLiveness.store'), [
            'purpose' => 'attendance_student',
            'subject_key' => 'STUDENT-RFID-1',
        ])
        ->assertOk()
        ->assertJsonPath('region', 'us-east-1');
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
    ))->toBe('data:image/jpeg;base64,'.base64_encode('reference-bytes'))
        ->and($service->consumeReferenceImage(
            $request,
            $token,
            'attendance_student',
            'STUDENT-RFID-1',
        ))->toBeNull();
});
