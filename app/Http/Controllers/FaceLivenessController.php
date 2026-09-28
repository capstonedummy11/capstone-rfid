<?php

namespace App\Http\Controllers;

use App\Services\AwsFaceLivenessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FaceLivenessController
{
    public function store(Request $request, AwsFaceLivenessService $service): JsonResponse
    {
        $validated = $request->validate([
            'purpose' => ['required', Rule::in(['attendance_student', 'attendance_instructor', 'instructor_login', 'online_class_student'])],
            'subject_key' => ['required', 'string', 'max:255'],
        ]);

        $role = strtolower(trim((string) $request->user()?->role));
        $allowedPurposes = match ($role) {
            'console' => ['attendance_student', 'attendance_instructor'],
            'instructor' => ['instructor_login'],
            'student', 'parent' => ['online_class_student'],
            default => [],
        };

        abort_unless(in_array($validated['purpose'], $allowedPurposes, true), 403);

        try {
            $result = $service->createSession($request, $validated['purpose'], $validated['subject_key']);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'AWS Face Liveness could not create a session.'], 503);
        }

        if (! $result['available']) {
            return response()->json($result, ($result['enabled'] ?? false) ? 503 : 409);
        }

        return response()->json($result);
    }

    public function show(Request $request, string $sessionId, AwsFaceLivenessService $service): JsonResponse
    {
        try {
            $result = $service->completeSession($request, $sessionId);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'AWS Face Liveness results are unavailable.'], 503);
        }

        return response()->json($result, $result['ok'] ? 200 : 422);
    }
}
