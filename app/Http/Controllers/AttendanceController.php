<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\Borrowing;
use App\Models\EmergencyHotline;
use App\Models\EmergencyType;
use App\Models\Instructor;
use App\Models\Item;
use App\Models\PanelDevice;
use App\Models\RfidPanelSession;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Students;
use App\Models\Subject;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AwsFaceRecognitionService;
use App\Services\CompreFaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController
{
    public function updatePanelSessionState(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:offline,online,paused,attendance,borrowing'],
            'subject_code' => ['nullable', 'string', 'max:255'],
            'schedule_id' => ['nullable', 'integer'],
            'opened_by_user_id' => ['nullable', 'integer'],
            'is_listening' => ['nullable', 'boolean'],
            'meta' => ['nullable', 'array'],
        ]);

        $session = RfidPanelSession::query()
            ->where('room', $validated['room'])
            ->whereNull('ended_at')
            ->latest('panel_session_id')
            ->first();

        if (! $session) {
            $session = new RfidPanelSession;
            $session->room = $validated['room'];
            $session->panel_id = $this->panelLabelForRoom($validated['room']);
        }

        $status = $validated['status'];

        if ($status === 'borrowing' && ! SystemSetting::boolean(SystemSetting::BORROWING_ENABLED, false)) {
            return response()->json([
                'ok' => false,
                'message' => 'Borrowing is currently disabled.',
            ], 423);
        }

        $session->status = $status;
        $session->subject_code = $validated['subject_code'] ?? null;
        $session->schedule_id = $validated['schedule_id'] ?? null;
        $session->opened_by_user_id = $validated['opened_by_user_id'] ?? null;
        $session->is_listening = $validated['is_listening'] ?? true;
        $session->meta = $validated['meta'] ?? $session->meta;

        if ($session->is_listening && ! $session->listening_started_at) {
            $session->listening_started_at = now();
        }

        if (! $session->is_listening) {
            $session->paused_at = now();
        } else {
            $session->paused_at = null;
        }

        if ($status === 'offline') {
            $session->ended_at = now();
        } else {
            $session->ended_at = null;
        }

        $session->save();

        $today = now()->toDateString();
        $nowTime = now()->format('H:i:s');
        $attendanceSession = DB::table('attendance_sessions')
            ->where('room', $validated['room'])
            ->whereDate('date', $today)
            ->orderByDesc('attendance_id')
            ->first();

        if ($status === 'attendance') {
            if (! $attendanceSession) {
                DB::table('attendance_sessions')->insert([
                    'subject_code' => $validated['subject_code'] ?? null,
                    'schedule_id' => $validated['schedule_id'] ?? null,
                    'date' => $today,
                    'time_start' => $nowTime,
                    'time_end' => null,
                    'status' => 'attendance',
                    'room' => $validated['room'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('attendance_sessions')
                    ->where('attendance_id', $attendanceSession->attendance_id)
                    ->update([
                        'subject_code' => $validated['subject_code'] ?? $attendanceSession->subject_code,
                        'schedule_id' => $validated['schedule_id'] ?? $attendanceSession->schedule_id,
                        'status' => 'attendance',
                        'time_start' => $attendanceSession->time_start ?: $nowTime,
                        'time_end' => null,
                        'updated_at' => now(),
                    ]);
            }
        } elseif ($attendanceSession) {
            if ($attendanceSession->status === 'attendance') {
                $this->finalizeCuttingStudents($attendanceSession);
                $request->session()->forget($this->cameraBypassKey((int) $attendanceSession->attendance_id));
            }

            $updateData = [
                'status' => $status,
                'updated_at' => now(),
            ];

            if (in_array($status, ['online', 'offline'], true) && ! $attendanceSession->time_end) {
                $updateData['time_end'] = $nowTime;
            }

            DB::table('attendance_sessions')
                ->where('attendance_id', $attendanceSession->attendance_id)
                ->update($updateData);
        }

        return response()->json([
            'ok' => true,
            'panel_session_id' => $session->panel_session_id,
        ]);
    }

    public function recordStudentTap(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rfid' => ['required', 'string', 'max:255'],
            'room' => ['required', 'string', 'max:255'],
            'subject_code' => ['nullable', 'string', 'max:255'],
            'schedule_id' => ['nullable', 'integer'],
            'force_checkout' => ['nullable', 'boolean'],
            'temporary_movement_instructor_rfid' => ['nullable', 'string', 'max:255'],
        ]);

        $rfid = strtolower(trim($validated['rfid']));
        $student = Students::query()
            ->with(['strand', 'section'])
            ->whereRaw('LOWER(rfid_tag) = ?', [$rfid])
            ->first();

        if (! $student) {
            return response()->json([
                'ok' => false,
                'message' => 'Student not found for this RFID.',
            ], 404);
        }

        $today = now()->toDateString();
        $nowTime = now()->format('H:i:s');

        $attendanceSession = DB::table('attendance_sessions')
            ->where('room', $validated['room'])
            ->whereDate('date', $today)
            ->where('status', 'attendance')
            ->orderByDesc('attendance_id')
            ->first();

        if (! $attendanceSession) {
            return response()->json([
                'ok' => false,
                'message' => 'No active attendance session was found for this room.',
            ], 422);
        }

        $scheduleId = $validated['schedule_id'] ?? $attendanceSession->schedule_id;
        $subjectCode = $validated['subject_code'] ?? $attendanceSession->subject_code;

        $currentSchedule = null;
        if ($scheduleId) {
            $currentSchedule = Schedule::query()->find($scheduleId);
        }

        $currentSubject = null;
        if ($subjectCode && $currentSchedule) {
            $currentSubject = Subject::query()
                ->where('subject_code', $subjectCode)
                ->where('section_id', $currentSchedule->section_id)
                ->first();
        }

        if (! $currentSchedule) {
            return response()->json([
                'ok' => false,
                'message' => 'Current class schedule could not be resolved.',
            ], 422);
        }

        // Main guard: a student must belong to the active schedule section.
        if ((int) $student->section_id !== (int) $currentSchedule->section_id) {
            return response()->json([
                'ok' => false,
                'message' => 'Student is not in this class section.',
            ], 422);
        }

        // Optional guard: if subject year level is set, ensure it aligns too.
        if ($currentSubject && ! is_null($currentSubject->year_level) && (int) $student->year_level !== (int) $currentSubject->year_level) {
            return response()->json([
                'ok' => false,
                'message' => 'Student year level does not match this class.',
            ], 422);
        }

        $verificationKey = $this->attendanceVerificationKey(
            (int) $attendanceSession->attendance_id,
            (int) $student->student_id,
        );

        $verification = $request->session()->get($verificationKey);

        if (! is_array($verification) || (int) ($verification['expires_at'] ?? 0) < now()->timestamp) {
            return response()->json([
                'ok' => false,
                'requires_verification' => true,
                'message' => 'Face verification or an instructor RFID override is required before attendance can be recorded.',
            ], 422);
        }

        $verificationMethod = (string) ($verification['method'] ?? 'unknown');
        $verificationFacePath = $verification['face_path'] ?? null;

        $now = now();
        $scheduleStart = $this->scheduleDateTime($today, (string) $currentSchedule->time_start);
        $scheduleEnd = $this->scheduleDateTime($today, (string) $currentSchedule->time_end);
        $checkoutWindowStart = $scheduleEnd?->copy()->subMinutes(15);
        $forceCheckout = (bool) ($validated['force_checkout'] ?? false);

        $result = DB::transaction(function () use ($student, $attendanceSession, $currentSchedule, $scheduleId, $subjectCode, $validated, $today, $now, $nowTime, $scheduleStart, $checkoutWindowStart, $forceCheckout, $verificationMethod, $verificationFacePath) {
            $attendance = Attendance::query()
                ->where('student_id', $student->student_id)
                ->where('schedule_id', $scheduleId)
                ->whereDate('date', $today)
                ->lockForUpdate()
                ->first();

            $lastSequence = (int) DB::table('attendance_logs')
                ->where('attendance_id', $attendanceSession->attendance_id)
                ->where('student_id', $student->student_id)
                ->max('tap_sequence_number');
            $sequence = $lastSequence + 1;

            if (! $attendance) {
                if ($forceCheckout) {
                    $logId = $this->insertInvalidAttendanceTapLog($attendanceSession->attendance_id, $student, $scheduleId, $now, 'Invalid Tap', $sequence, $validated['room'], 'Dismiss Class checkout was requested, but the student has no check-in for this class.');

                    return [null, $logId, 'invalid_tap', 'Invalid Tap', false, 'Student has no check-in record to check out from this dismissed class.', false];
                }

                $lateThreshold = SystemSetting::integer(SystemSetting::ATTENDANCE_LATE_THRESHOLD_MINUTES, 15);
                $checkInStatus = $scheduleStart && $now->greaterThan($scheduleStart->copy()->addMinutes($lateThreshold)) ? 'late' : 'present';

                $attendance = Attendance::query()->create([
                    'student_id' => $student->student_id,
                    'schedule_id' => $scheduleId,
                    'date' => $today,
                    'time_start' => $currentSchedule->time_start,
                    'time_end' => $currentSchedule->time_end,
                    'time_in' => $nowTime,
                    'time_out' => null,
                    'check_in_status' => $checkInStatus,
                    'status' => 'pending',
                    'room_status' => 'inside',
                    'total_taps' => 1,
                    'remarks' => $checkInStatus === 'late'
                        ? 'Checked in after the 15-minute grace period.'
                        : 'Checked in within the 15-minute grace period.',
                    'subject_code' => $subjectCode,
                    'room' => $validated['room'],
                ]);

                $logId = $this->insertAttendanceTapLog($attendanceSession->attendance_id, $attendance, $student, $scheduleId, $now, 'Check-in', $sequence, $validated['room'], 'valid', 'Official check-in recorded.', $verificationMethod, $verificationFacePath);

                return [$attendance, $logId, 'check_in', 'Check-in', true, 'Official check-in recorded.', false];
            }

            if ($attendance->time_out) {
                $logId = $this->insertAttendanceTapLog($attendanceSession->attendance_id, $attendance, $student, $scheduleId, $now, 'Ignored Tap', $sequence, $validated['room'], 'ignored', 'Official check-out already exists.');

                return [$attendance, $logId, 'ignored_tap', 'Ignored Tap', false, 'Attendance is already completed for this class.', false];
            }

            $fallbackVerificationMethods = [
                'captured_aws_unavailable',
                'camera_session_override',
                'face_recognition_disabled',
                'instructor_rfid',
            ];
            $isFallbackVerificationCheckout = in_array($verificationMethod, $fallbackVerificationMethods, true);
            $isCheckoutTap = $forceCheckout
                || $isFallbackVerificationCheckout
                || ($checkoutWindowStart ? $now->greaterThanOrEqualTo($checkoutWindowStart) : false);

            if ($isCheckoutTap) {
                $finalStatus = $attendance->check_in_status === 'late' ? 'late' : 'present';
                $remarks = $forceCheckout
                    ? 'Official check-out recorded while the instructor Dismiss Class mode was active.'
                    : 'Official check-out recorded.';

                $attendance->update([
                    'time_out' => $nowTime,
                    'status' => $finalStatus,
                    'room_status' => 'outside',
                    'total_taps' => $sequence,
                    'remarks' => $remarks,
                ]);

                $logId = $this->insertAttendanceTapLog($attendanceSession->attendance_id, $attendance->fresh(), $student, $scheduleId, $now, 'Check-out', $sequence, $validated['room'], 'valid', $remarks, $verificationMethod, $verificationFacePath);

                DB::table('attendance_logs')
                    ->where('attendance_id', $attendanceSession->attendance_id)
                    ->where('student_id', $student->student_id)
                    ->where('tap_type', 'Check-in')
                    ->orderBy('tap_sequence_number')
                    ->limit(1)
                    ->update([
                        'time_out' => $nowTime,
                        'time_out_face_path' => $verificationFacePath,
                        'completion_reason' => 'time_out',
                        'updated_at' => now(),
                    ]);

                return [$attendance->fresh(), $logId, 'check_out', 'Check-out', true, $remarks, false];
            }

            if (! $this->instructorRfidAuthorizesTemporaryMovement($currentSchedule, $validated['temporary_movement_instructor_rfid'] ?? null)) {
                return [$attendance, null, 'temporary_authorization_required', 'Temporary Movement', false, 'Instructor RFID is required before recording Temporary Exit or Temporary Return.', true];
            }

            $tapType = $attendance->room_status === 'outside' ? 'Temporary Return' : 'Temporary Exit';
            $nextRoomStatus = $tapType === 'Temporary Return' ? 'inside' : 'outside';

            $attendance->update([
                'status' => 'pending',
                'room_status' => $nextRoomStatus,
                'total_taps' => $sequence,
                'remarks' => $tapType.' recorded before the official check-out window.',
            ]);

            $logId = $this->insertAttendanceTapLog($attendanceSession->attendance_id, $attendance->fresh(), $student, $scheduleId, $now, $tapType, $sequence, $validated['room'], 'valid', $tapType.' recorded before the official check-out window.', $verificationMethod, $verificationFacePath);

            return [$attendance->fresh(), $logId, Str::snake($tapType), $tapType, true, $tapType.' recorded.', false];
        });

        [$attendance, $attendanceLogId, $action, $tapType, $accepted, $message, $requiresTemporaryMovementInstructor] = $result;

        if ($requiresTemporaryMovementInstructor) {
            return response()->json([
                'ok' => false,
                'requires_temporary_movement_instructor' => true,
                'action' => $action,
                'tap_type' => $tapType,
                'message' => $message,
            ], 428);
        }

        $request->session()->forget($verificationKey);

        $displayStatus = $attendance ? $this->attendanceDisplayStatus($attendance, $attendanceSession) : 'Invalid Tap';

        $responseAction = match ($action) {
            'check_in' => 'time_in',
            'check_out' => 'time_out',
            default => $action,
        };
        $responseStatus = $tapType === 'Check-in'
            ? 'Checked In'
            : $displayStatus;

        return response()->json([
            'ok' => true,
            'accepted' => $accepted,
            'action' => $responseAction,
            'tap_type' => $tapType,
            'message' => $message,
            'record' => [
                'id' => $attendanceLogId,
                'attendance_id' => $attendance?->attendance_id,
                'rfid' => $student->rfid_tag,
                'name' => trim($student->first_name.' '.$student->last_name),
                'course' => $student->strand?->strand_code,
                'section' => $student->year_level.' - '.($student->section?->section_name ?? ''),
                'time' => date('g:i A', strtotime($nowTime)),
                'time_in' => $attendance?->time_in ? date('g:i A', strtotime((string) $attendance->time_in)) : null,
                'time_out' => $attendance?->time_out ? date('g:i A', strtotime((string) $attendance->time_out)) : null,
                'tap_type' => $tapType,
                'tap_sequence_number' => $attendance?->total_taps ?: null,
                'room_status' => ucfirst((string) ($attendance?->room_status ?? 'outside')),
                'status' => $responseStatus,
                'remarks' => $message,
            ],
        ]);
    }

    public function studentFaceCheck(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rfid' => ['required', 'string', 'max:255'],
            'room' => ['required', 'string', 'max:255'],
            'subject_code' => ['nullable', 'string', 'max:255'],
            'schedule_id' => ['nullable', 'integer'],
            'image' => ['nullable', 'string'],
            'instructor_rfid' => ['nullable', 'string', 'max:255'],
            'camera_unavailable' => ['nullable', 'boolean'],
        ]);

        $rfid = strtolower(trim($validated['rfid']));
        $student = Students::query()
            ->with(['strand', 'section'])
            ->whereRaw('LOWER(rfid_tag) = ?', [$rfid])
            ->first();

        if (! $student) {
            return response()->json([
                'ok' => false,
                'message' => 'Student not found for this RFID.',
            ], 404);
        }

        $attendanceSession = DB::table('attendance_sessions')
            ->where('room', $validated['room'])
            ->whereDate('date', now()->toDateString())
            ->where('status', 'attendance')
            ->orderByDesc('attendance_id')
            ->first();

        if (! $attendanceSession) {
            return response()->json([
                'ok' => false,
                'message' => 'No active attendance session was found for this room.',
            ], 422);
        }

        $scheduleId = $validated['schedule_id'] ?? $attendanceSession->schedule_id;
        $subjectCode = $validated['subject_code'] ?? $attendanceSession->subject_code;
        $currentSchedule = $scheduleId ? Schedule::query()->find($scheduleId) : null;

        if (! $currentSchedule) {
            return response()->json([
                'ok' => false,
                'message' => 'Current class schedule could not be resolved.',
            ], 422);
        }

        if ((int) $student->section_id !== (int) $currentSchedule->section_id) {
            return response()->json([
                'ok' => false,
                'message' => 'Student is not enrolled in this class section.',
            ], 422);
        }

        $currentSubject = null;
        if ($subjectCode) {
            $currentSubject = Subject::query()
                ->where('subject_code', $subjectCode)
                ->where('section_id', $currentSchedule->section_id)
                ->first();
        }

        if ($currentSubject && ! is_null($currentSubject->year_level) && (int) $student->year_level !== (int) $currentSubject->year_level) {
            return response()->json([
                'ok' => false,
                'message' => 'Student year level does not match this class.',
            ], 422);
        }

        $faceImages = array_values(array_filter($student->face_images ?? []));

        if ($request->session()->get($this->cameraBypassKey((int) $attendanceSession->attendance_id)) === true) {
            $this->grantAttendanceVerification($request, $attendanceSession, $student, 'camera_session_override');

            return response()->json([
                'ok' => true,
                'verified' => true,
                'camera_session_override' => true,
                'provider' => 'instructor_rfid',
                'message' => 'Camera bypass is active for this class session.',
            ]);
        }

        if ((bool) ($validated['camera_unavailable'] ?? false)) {
            $instructorRfid = strtolower(trim((string) ($validated['instructor_rfid'] ?? '')));
            if (! $this->matchesScheduleInstructorRfid($currentSchedule, $instructorRfid)) {
                return response()->json([
                    'ok' => false,
                    'requires_instructor_rfid' => true,
                    'camera_unavailable' => true,
                    'message' => 'Active instructor RFID is required to bypass the unavailable camera.',
                ], 422);
            }

            $request->session()->put($this->cameraBypassKey((int) $attendanceSession->attendance_id), true);
            $this->grantAttendanceVerification($request, $attendanceSession, $student, 'camera_session_override');

            return response()->json([
                'ok' => true,
                'verified' => true,
                'camera_session_override' => true,
                'provider' => 'instructor_rfid',
                'message' => 'Instructor RFID verified. Camera bypass is active until this class session ends or the panel logs out.',
            ]);
        }

        if (count($faceImages) === 0) {
            $instructorRfid = strtolower(trim((string) ($validated['instructor_rfid'] ?? '')));
            if ($instructorRfid === '') {
                return response()->json([
                    'ok' => false,
                    'requires_instructor_rfid' => true,
                    'message' => 'This student has no registered face image. The active instructor must scan their RFID card to approve attendance.',
                ], 422);
            }

            if (! $this->matchesScheduleInstructorRfid($currentSchedule, $instructorRfid)) {
                return response()->json([
                    'ok' => false,
                    'requires_instructor_rfid' => true,
                    'message' => 'Instructor RFID verification failed. Attendance was not recorded.',
                ], 422);
            }

            $this->grantAttendanceVerification($request, $attendanceSession, $student, 'instructor_rfid');

            return response()->json([
                'ok' => true,
                'verified' => true,
                'instructor_override' => true,
                'provider' => 'instructor_rfid',
                'message' => 'Active instructor RFID verified. Attendance can continue.',
            ]);
        }

        if (! SystemSetting::boolean(SystemSetting::FACE_RECOGNITION_ENABLED, true)) {
            $instructorRfid = strtolower(trim((string) ($validated['instructor_rfid'] ?? '')));
            if ($instructorRfid === '') {
                return response()->json([
                    'ok' => false,
                    'verified' => false,
                    'requires_instructor_rfid' => true,
                    'message' => 'Face recognition is disabled. The active instructor must scan their RFID card to approve attendance.',
                ], 422);
            }

            if (! $this->matchesScheduleInstructorRfid($currentSchedule, $instructorRfid)) {
                return response()->json([
                    'ok' => false,
                    'verified' => false,
                    'requires_instructor_rfid' => true,
                    'message' => 'Instructor RFID verification failed. Attendance was not recorded.',
                ], 422);
            }

            $this->grantAttendanceVerification($request, $attendanceSession, $student, 'face_recognition_disabled');

            return response()->json([
                'ok' => true,
                'verified' => true,
                'instructor_override' => true,
                'provider' => 'face_recognition_disabled',
                'message' => 'Active instructor RFID verified. Attendance can continue while face recognition is disabled.',
            ]);
        }

        if (blank($validated['image'] ?? null)) {
            return response()->json([
                'ok' => false,
                'verified' => false,
                'message' => 'Camera capture is required for face verification.',
            ], 422);
        }

        $faceService = new AwsFaceRecognitionService;
        $faceResult = null;
        $bestMismatch = null;

        foreach ($faceImages as $faceImage) {
            $comparison = $faceService->compareBase64WithStoredImage($validated['image'], $faceImage);
            if ($comparison === null) {
                continue;
            }

            if ($comparison['verified']) {
                $faceResult = $comparison;
                break;
            }

            if ($bestMismatch === null || $comparison['similarity'] > $bestMismatch['similarity']) {
                $bestMismatch = $comparison;
            }
        }

        $faceResult ??= $bestMismatch;

        if ($faceResult === null) {
            $capturePath = $this->storeAttendanceFaceCapture(
                (string) $validated['image'],
                (int) $attendanceSession->attendance_id,
                $student,
            );

            if (! $capturePath) {
                return response()->json([
                    'ok' => false,
                    'verified' => false,
                    'provider_unavailable' => true,
                    'message' => 'AWS face recognition is unavailable and the captured attendance photo could not be stored.',
                ], 503);
            }

            $this->grantAttendanceVerification($request, $attendanceSession, $student, 'captured_aws_unavailable', $capturePath);

            return response()->json([
                'ok' => true,
                'verified' => true,
                'provider' => 'aws_rekognition',
                'provider_unavailable' => true,
                'capture_recorded' => true,
                'message' => 'Warning: AWS face recognition is unavailable. The student photo was captured and attached to this attendance event.',
            ]);
        }

        if (! $faceResult['verified']) {
            return response()->json([
                'ok' => false,
                'verified' => false,
                'provider' => $faceResult['provider'],
                'similarity' => $faceResult['similarity'],
                'threshold' => $faceResult['threshold'],
                'message' => 'Face mismatch. Camera image does not match the enrolled student photo.',
            ], 422);
        }

        $capturePath = $this->storeAttendanceFaceCapture(
            (string) $validated['image'],
            (int) $attendanceSession->attendance_id,
            $student,
        );

        if (! $capturePath) {
            return response()->json([
                'ok' => false,
                'verified' => false,
                'message' => 'Face matched, but the attendance evidence image could not be stored. Attendance was not recorded.',
            ], 500);
        }

        $this->grantAttendanceVerification($request, $attendanceSession, $student, 'aws_rekognition', $capturePath);

        return response()->json([
            'ok' => true,
            'verified' => true,
            'provider' => $faceResult['provider'],
            'similarity' => $faceResult['similarity'],
            'threshold' => $faceResult['threshold'],
            'capture_recorded' => true,
            'message' => 'Face verified.',
        ]);
    }

    public function instructorFaceCheck(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'instructor_rfid' => ['required', 'string', 'max:255'],
            'active_instructor_user_id' => ['required', 'integer'],
            'student_rfid' => ['required', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'string'],
        ]);

        $rfid = strtolower(trim($validated['instructor_rfid']));
        $instructor = User::query()
            ->whereRaw('LOWER(rfid_tag) = ?', [$rfid])
            ->whereRaw('LOWER(role) = ?', ['instructor'])
            ->first();

        if (! $instructor) {
            return response()->json([
                'ok' => false,
                'verified' => false,
                'message' => 'Instructor RFID was not found.',
            ], 404);
        }

        if ((int) $instructor->user_id !== (int) $validated['active_instructor_user_id']) {
            return response()->json([
                'ok' => false,
                'verified' => false,
                'message' => 'Instructor RFID does not match the active class instructor.',
            ], 403);
        }

        $faceImages = array_values(array_filter($instructor->face_images ?? []));
        if (
            ! SystemSetting::boolean(SystemSetting::FACE_RECOGNITION_ENABLED, true)
            || count($faceImages) === 0
        ) {
            $this->logInstructorOverride($instructor, $validated['student_rfid'], (string) ($validated['reason'] ?? 'rfid_only'));

            return response()->json([
                'ok' => true,
                'verified' => true,
                'provider' => count($faceImages) === 0 ? 'instructor_rfid_only' : 'face_recognition_disabled',
                'message' => count($faceImages) === 0
                    ? 'Instructor RFID verified. No instructor face image is enrolled, so RFID authorization was accepted.'
                    : 'Instructor RFID verified. Face recognition is disabled, so RFID authorization was accepted.',
            ]);
        }

        if (empty($validated['image'])) {
            return response()->json([
                'ok' => false,
                'verified' => false,
                'message' => 'Instructor face capture is required.',
            ], 422);
        }

        $faceResult = (new AwsFaceRecognitionService)->compareBase64WithStoredImage(
            $validated['image'],
            $faceImages[0],
        );

        if ($faceResult === null) {
            return response()->json([
                'ok' => false,
                'verified' => false,
                'message' => 'AWS face recognition is unavailable or could not verify the instructor face.',
            ], 503);
        }

        if (! $faceResult['verified']) {
            return response()->json([
                'ok' => false,
                'verified' => false,
                'provider' => $faceResult['provider'],
                'similarity' => $faceResult['similarity'],
                'threshold' => $faceResult['threshold'],
                'message' => 'Instructor face mismatch. Attendance was not recorded.',
            ], 422);
        }

        $this->logInstructorOverride($instructor, $validated['student_rfid'], (string) ($validated['reason'] ?? 'face_verified'));

        return response()->json([
            'ok' => true,
            'verified' => true,
            'provider' => $faceResult['provider'],
            'similarity' => $faceResult['similarity'],
            'threshold' => $faceResult['threshold'],
            'message' => 'Instructor RFID and face verified. Attendance can continue.',
        ]);
    }

    private function attendanceVerificationKey(int $attendanceSessionId, int $studentId): string
    {
        return "attendance.verification.{$attendanceSessionId}.{$studentId}";
    }

    private function grantAttendanceVerification(Request $request, object $attendanceSession, Students $student, string $method, ?string $facePath = null): void
    {
        $request->session()->put($this->attendanceVerificationKey(
            (int) $attendanceSession->attendance_id,
            (int) $student->student_id,
        ), [
            'method' => $method,
            'face_path' => $facePath,
            'expires_at' => now()->addMinutes(2)->timestamp,
        ]);
    }

    private function cameraBypassKey(int $attendanceSessionId): string
    {
        return "attendance.camera_bypass.{$attendanceSessionId}";
    }

    private function matchesScheduleInstructorRfid(Schedule $schedule, string $rfid): bool
    {
        if ($rfid === '') {
            return false;
        }

        $schedule->loadMissing('instructor.user');

        return $schedule->instructor?->user
            && strtolower(trim((string) $schedule->instructor->user->rfid_tag)) === $rfid;
    }

    private function storeAttendanceFaceCapture(string $dataUrl, int $attendanceSessionId, Students $student): ?string
    {
        $base64 = preg_replace('/^data:[^;]+;base64,/', '', $dataUrl);
        $bytes = base64_decode((string) $base64, true);
        if ($bytes === false || $bytes === '') {
            return null;
        }

        $path = sprintf(
            'attendance_face_captures/%d/%s-%s.jpg',
            $attendanceSessionId,
            Str::slug((string) $student->student_number),
            now()->format('YmdHisv'),
        );

        return Storage::disk('public')->put($path, $bytes) ? $path : null;
    }

    private function finalizeCuttingStudents(object $attendanceSession): void
    {
        $openLogs = DB::table('attendance_logs')
            ->where('attendance_id', $attendanceSession->attendance_id)
            ->whereNull('time_out')
            ->get();

        foreach ($openLogs as $log) {
            DB::table('attendance_logs')->where('id', $log->id)->update([
                'status' => 'absent',
                'completion_reason' => 'cutting',
                'updated_at' => now(),
            ]);

            Attendance::query()
                ->where('student_id', $log->student_id)
                ->whereDate('date', $attendanceSession->date)
                ->where('room', $attendanceSession->room)
                ->where('subject_code', $attendanceSession->subject_code)
                ->whereNull('time_out')
                ->update(['status' => 'absent']);
        }
    }

    public function attendanceLogSnapshot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room' => ['required', 'string', 'max:255'],
            'subject_code' => ['nullable', 'string', 'max:255'],
            'schedule_id' => ['nullable', 'integer'],
        ]);

        $today = now()->toDateString();

        $attendanceSession = DB::table('attendance_sessions')
            ->where('room', $validated['room'])
            ->whereDate('date', $today)
            ->when($validated['schedule_id'] ?? null, function ($query, $scheduleId) {
                $query->where('schedule_id', $scheduleId);
            })
            ->when($validated['subject_code'] ?? null, function ($query, $subjectCode) {
                $query->where('subject_code', $subjectCode);
            })
            ->orderByDesc('attendance_id')
            ->first();

        if (! $attendanceSession) {
            return response()->json([
                'ok' => true,
                'records' => [],
            ]);
        }

        $records = DB::table('attendance_logs')
            ->join('students', 'students.student_id', '=', 'attendance_logs.student_id')
            ->leftJoin('attendances', 'attendances.attendance_id', '=', 'attendance_logs.main_attendance_id')
            ->leftJoin('strands', 'strands.strand_id', '=', 'students.strand_id')
            ->leftJoin('sections', 'sections.section_id', '=', 'students.section_id')
            ->where('attendance_logs.attendance_id', $attendanceSession->attendance_id)
            ->orderByDesc('attendance_logs.tap_datetime')
            ->orderByDesc('attendance_logs.id')
            ->select([
                'attendance_logs.id',
                'attendance_logs.time_in',
                'attendance_logs.time_out',
                'attendance_logs.status',
                'attendance_logs.time_in_face_path',
                'attendance_logs.time_out_face_path',
                'attendance_logs.verification_method',
                'attendance_logs.tap_datetime',
                'attendance_logs.tap_type',
                'attendance_logs.tap_sequence_number',
                'attendance_logs.validation_result',
                'attendance_logs.remarks as log_remarks',
                'attendances.attendance_id as main_attendance_id',
                'attendances.time_in as attendance_time_in',
                'attendances.time_end as attendance_scheduled_end',
                'attendances.time_out as attendance_time_out',
                'attendances.status as attendance_status',
                'attendances.room_status',
                'attendances.total_taps',
                'students.rfid_tag',
                'students.first_name',
                'students.last_name',
                'students.year_level',
                'strands.strand_code',
                'sections.section_name',
            ])
            ->get()
            ->map(function ($record) {
                return [
                    'id' => $record->id,
                    'rfid' => $record->rfid_tag,
                    'name' => trim(($record->first_name ?? '').' '.($record->last_name ?? '')),
                    'course' => $record->strand_code,
                    'section' => trim(($record->year_level ? $record->year_level.' - ' : '').($record->section_name ?? '')),
                    'time' => $record->tap_datetime ? Carbon::parse($record->tap_datetime)->format('g:i A') : ($record->time_in ? date('g:i A', strtotime((string) $record->time_in)) : null),
                    'time_in' => $record->attendance_time_in ? date('g:i A', strtotime((string) $record->attendance_time_in)) : null,
                    'time_out' => $record->attendance_time_out ? date('g:i A', strtotime((string) $record->attendance_time_out)) : null,
                    'tap_type' => $record->tap_type ?? 'Check-in',
                    'tap_sequence_number' => $record->tap_sequence_number,
                    'room_status' => ucfirst((string) ($record->room_status ?? 'outside')),
                    'status' => $this->attendanceDisplayStatus($record, null),
                    'validation_result' => ucfirst((string) ($record->validation_result ?? 'valid')),
                    'remarks' => $record->log_remarks,
                    'time_in_image_url' => $record->time_in_face_path ? route('attendance.evidence', ['attendanceLog' => $record->id, 'moment' => 'time-in']) : null,
                    'time_out_image_url' => $record->time_out_face_path ? route('attendance.evidence', ['attendanceLog' => $record->id, 'moment' => 'time-out']) : null,
                    'verification_method' => $record->verification_method,
                ];
            })
            ->values();

        return response()->json([
            'ok' => true,
            'records' => $records,
        ]);
    }

    public function lookupRfid(Request $request): JsonResponse
    {
        $rfid = strtolower(trim((string) $request->input('rfid', '')));
        $room = (string) $request->input('room', '');

        if ($rfid === '') {
            return response()->json([
                'found' => false,
                'message' => 'RFID is required.',
            ], 422);
        }

        // Priority: instructors in users table first, then students table.
        $instructor = User::query()
            ->whereRaw('LOWER(rfid_tag) = ?', [$rfid])
            ->whereRaw('LOWER(role) = ?', ['instructor'])
            ->first();

        if ($instructor) {
            $instructorProfileId = Instructor::query()
                ->where('user_id', $instructor->user_id)
                ->value('instructor_id');

            // Validate schedule by room/time/day and instructor ownership in one query.
            $now = now();
            $currentTime = $now->format('H:i:s');
            $weekday = $now->format('D');
            $weekdayFull = $now->format('l');
            $normalizedRoom = strtolower(trim($room));

            $schedule = null;
            $hasValidSchedule = false;
            $scheduleLabel = 'No schedule assigned';
            $scheduleTimeStart = null;
            $scheduleTimeEnd = null;
            $scheduleWeekdays = null;

            if ($room) {
                $matchingSchedules = Schedule::query()
                    ->leftJoin('subjects', function ($join) {
                        $join->on('subjects.subject_code', '=', 'schedules.subject_code')
                            ->on('subjects.section_id', '=', 'schedules.section_id');
                    })
                    ->leftJoin('sections', 'sections.section_id', '=', 'schedules.section_id')
                    ->leftJoin('strands', 'strands.strand_id', '=', 'sections.strand_id')
                    ->whereRaw('LOWER(TRIM(schedules.room)) = ?', [$normalizedRoom])
                    ->whereRaw('TIME(?) >= schedules.time_start AND TIME(?) < schedules.time_end', [$currentTime, $currentTime])
                    ->where(function ($query) use ($instructorProfileId, $instructor) {
                        if ($instructorProfileId) {
                            $query->where('schedules.instructor_id', '=', $instructorProfileId);
                        }

                        // Backward compatibility for legacy rows that don't have instructor_id.
                        $query->orWhere('subjects.user_id', '=', $instructor->user_id);
                    })
                    ->select([
                        'schedules.*',
                        'subjects.subject_name as matched_subject_name',
                        'subjects.subject_code as matched_subject_code',
                        'subjects.section_id as matched_section_id',
                        'subjects.year_level as matched_subject_year_level',
                        'sections.section_name as matched_section_name',
                        'sections.year_level as matched_year_level',
                        'strands.strand_code as matched_strand_code',
                        'strands.strand_name as matched_strand_name',
                    ])
                    ->get();

                $schedule = $matchingSchedules->first(function ($candidate) use ($weekday, $weekdayFull) {
                    return $this->matchesWeekday((string) ($candidate->weekdays ?? ''), $weekday, $weekdayFull);
                });

                if ($schedule) {
                    $hasValidSchedule = true;
                    $scheduleWeekdays = $schedule->weekdays;
                    $scheduleTimeStart = $schedule->time_start;
                    $scheduleTimeEnd = $schedule->time_end;

                    $formattedStart = $schedule->time_start
                        ? date('g:i A', strtotime((string) $schedule->time_start))
                        : null;
                    $formattedEnd = $schedule->time_end
                        ? date('g:i A', strtotime((string) $schedule->time_end))
                        : null;

                    if ($formattedStart && $formattedEnd) {
                        $scheduleLabel = trim(($schedule->weekdays ?? 'Scheduled').' '.$formattedStart.' - '.$formattedEnd);
                    }
                } else {
                    $matchingByRoomDay = Schedule::query()
                        ->whereRaw('LOWER(TRIM(room)) = ?', [$normalizedRoom])
                        ->get(['weekdays'])
                        ->filter(fn ($s) => $this->matchesWeekday((string) ($s->weekdays ?? ''), $weekday, $weekdayFull))
                        ->count();

                    $matchingByInstructorDayTime = Schedule::query()
                        ->join('subjects', function ($join) use ($instructor) {
                            $join->on('subjects.subject_code', '=', 'schedules.subject_code')
                                ->on('subjects.section_id', '=', 'schedules.section_id')
                                ->where('subjects.user_id', '=', $instructor->user_id);
                        })
                        ->whereRaw('TIME(?) >= schedules.time_start AND TIME(?) < schedules.time_end', [$currentTime, $currentTime])
                        ->get(['schedules.weekdays'])
                        ->filter(fn ($s) => $this->matchesWeekday((string) ($s->weekdays ?? ''), $weekday, $weekdayFull))
                        ->count();

                    Log::warning('RFID schedule validation failed', [
                        'rfid' => $rfid,
                        'instructor_user_id' => $instructor->user_id,
                        'requested_room' => $room,
                        'normalized_room' => $normalizedRoom,
                        'weekday' => $weekday,
                        'current_time' => $currentTime,
                        'app_timezone' => config('app.timezone'),
                        'matching_room_day_count' => $matchingByRoomDay,
                        'matching_instructor_day_time_count' => $matchingByInstructorDayTime,
                    ]);
                }
            }

            return response()->json([
                'found' => true,
                'type' => 'instructor',
                'has_valid_schedule' => $hasValidSchedule,
                'mode' => $hasValidSchedule ? 'room' : null,
                'status' => $hasValidSchedule ? 'attendance' : 'idle',
                'profile' => [
                    'user_id' => $instructor->user_id,
                    'id' => $instructor->user_id,
                    'section' => $schedule?->matched_section_name
                        ? trim(($schedule->matched_year_level ? $schedule->matched_year_level.' - ' : '').$schedule->matched_section_name)
                        : 'Unassigned Section',
                    'course' => $schedule?->matched_strand_code ?? $schedule?->matched_strand_name ?? 'Unassigned Strand',
                    'name' => $instructor->name,
                    'rfid' => $instructor->rfid_tag,
                    'role' => 'instructor',
                    'subject' => $schedule?->matched_subject_name ?? 'Unassigned Subject',
                    'subject_code' => $schedule?->matched_subject_code ?? $schedule?->subject_code,
                    'section_id' => $schedule?->matched_section_id ?? $schedule?->section_id,
                    'year_level' => $schedule?->matched_subject_year_level ?? $schedule?->matched_year_level,
                    'schedule_id' => $schedule?->scheduled_id,
                    'schedule' => $scheduleLabel,
                    'schedule_days' => $scheduleWeekdays,
                    'schedule_time_start' => $scheduleTimeStart,
                    'schedule_time_end' => $scheduleTimeEnd,
                    'room' => $room ?: null,
                ],
            ]);
        }

        $student = Students::query()
            ->whereRaw('LOWER(rfid_tag) = ?', [$rfid])
            ->first();

        if ($student) {
            $student->loadMissing(['strand', 'section']);

            $nameParts = [
                (string) $student->first_name,
                (string) ($student->middle_name ?? ''),
                (string) $student->last_name,
            ];

            $fullName = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter($nameParts))));

            return response()->json([
                'found' => true,
                'type' => 'student',
                'profile' => [
                    'user_id' => $student->student_id,
                    'id' => $student->student_id,
                    'studentId' => $student->student_number,
                    'name' => $fullName,
                    'rfid' => $student->rfid_tag,
                    'year' => $student->year_level.' Year',
                    'course' => $student->strand?->strand_code ?? $student->section?->strand?->strand_code,
                    'strand' => $student->strand?->strand_code ?? $student->section?->strand?->strand_code,
                    'section' => $student->section?->section_name,
                    'avatarSeed' => $fullName,
                    'hasFaceImage' => count($student->face_images ?? []) > 0,
                    'faceImageCount' => count($student->face_images ?? []),
                ],
            ]);
        }

        $user = User::query()
            ->whereRaw('LOWER(rfid_tag) = ?', [$rfid])
            ->first();

        if ($user) {
            return response()->json([
                'found' => true,
                'type' => 'user',
                'profile' => [
                    'user_id' => $user->user_id,
                    'id' => $user->user_id,
                    'studentId' => 'USR-'.$user->user_id,
                    'name' => $user->name ?? 'Unknown User',
                    'rfid' => $user->rfid_tag,
                    'year' => 'N/A',
                    'course' => 'Faculty',
                    'section' => 'N/A',
                    'role' => ucfirst((string) ($user->role ?? 'User')),
                    'avatarSeed' => $user->name ?? 'user',
                ],
            ]);
        }

        return response()->json([
            'found' => false,
            'message' => 'RFID not found in users or students.',
        ]);
    }

    /**
     * Face verification endpoint.
     * Accepts a base64 image from the webcam and returns whether the recognized
     * subject matches the given student_number (RFID owner).
     */
    public function verifyFace(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'string'],      // base64 data URL
            'student_number' => ['required', 'string', 'max:255'],
        ]);

        if (! SystemSetting::boolean(SystemSetting::FACE_RECOGNITION_ENABLED, true)) {
            return response()->json([
                'ok' => true,
                'verified' => true,
                'provider' => 'disabled',
                'face_recognition_disabled' => true,
                'message' => 'Face recognition is disabled.',
            ]);
        }

        $result = (new CompreFaceService)->recognizeBase64($validated['image']);

        if ($result === null) {
            return response()->json([
                'ok' => false,
                'verified' => false,
                'message' => 'CompreFace could not detect or match a face.',
            ]);
        }

        $verified = strtolower(trim($result['subject'])) === strtolower(trim($validated['student_number']));
        $similarity = $result['similarity'];

        return response()->json([
            'ok' => true,
            'verified' => $verified,
            'similarity' => $similarity,
            'matched' => $result['subject'],
            'message' => $verified
                ? "Face verified ({$similarity})."
                : "Face does not match the RFID card holder (matched: {$result['subject']}, expected: {$validated['student_number']}).",
        ]);
    }

    public function verifyStudentFace(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'string'],
            'student_number' => ['required', 'string', 'max:255'],
        ]);

        if (! SystemSetting::boolean(SystemSetting::FACE_RECOGNITION_ENABLED, true)) {
            return response()->json([
                'ok' => true,
                'verified' => true,
                'provider' => 'disabled',
                'face_recognition_disabled' => true,
                'message' => 'Face recognition is disabled.',
            ]);
        }

        $student = Students::query()
            ->where('student_number', $validated['student_number'])
            ->first();

        if (! $student) {
            return response()->json([
                'ok' => false,
                'verified' => false,
                'message' => 'Student not found.',
            ], 404);
        }

        $faceImages = array_values(array_filter($student->face_images ?? []));

        if (count($faceImages) === 0) {
            return response()->json([
                'ok' => false,
                'verified' => false,
                'message' => 'Student has no saved face image.',
            ], 422);
        }

        $faceResult = (new AwsFaceRecognitionService)->compareBase64WithStoredImage(
            $validated['image'],
            $faceImages[0],
        );

        if ($faceResult === null) {
            return response()->json([
                'ok' => false,
                'verified' => false,
                'message' => 'AWS face recognition is unavailable or could not compare the images.',
            ], 503);
        }

        if (! $faceResult['verified']) {
            return response()->json([
                'ok' => false,
                'verified' => false,
                'provider' => $faceResult['provider'],
                'similarity' => $faceResult['similarity'],
                'threshold' => $faceResult['threshold'],
                'message' => 'Face mismatch.',
            ], 422);
        }

        // TODO: Add the action you want after successful facial recognition here.
        // Example: mark a gate entry, unlock a device, create an audit log, or redirect the frontend.

        return response()->json([
            'ok' => true,
            'verified' => true,
            'provider' => $faceResult['provider'],
            'similarity' => $faceResult['similarity'],
            'threshold' => $faceResult['threshold'],
            'message' => 'Face verified.',
        ]);
    }

    public function controlPanel()
    {
        $panelRoom = $this->currentPanelRoom();

        if (! $panelRoom) {
            return redirect()->route('attendanceControlPanel.login');
        }

        return Inertia::render('AttendanceControlPanel', [
            ...$this->panelPayload(),
            'panelRoom' => $panelRoom,
        ]);
    }

    public function panelLogin()
    {
        $isConsole = strtolower(trim((string) Auth::user()?->role)) === 'console';
        $panelRoom = $this->currentPanelRoom();

        if ($isConsole && $panelRoom) {
            return redirect()->route('attendanceControlPanel');
        }

        return Inertia::render('AttendancePanelLogin', [
            'rooms' => $this->panelRooms(),
            'alreadyVerified' => $isConsole,
        ]);
    }

    public function verifyPanelPin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pin' => ['required', 'string'],
            'room' => ['nullable', 'string', 'max:255'],
        ]);

        $pinHash = '';
        $room = trim((string) ($validated['room'] ?? ''));
        if ($room !== '') {
            $assignedDevice = PanelDevice::query()
                ->whereHas('laboratory', fn ($query) => $query->where('name', $room))
                ->first();
            if ($assignedDevice && ! $assignedDevice->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'The attendance device assigned to this laboratory is disabled.',
                ], 403);
            }
            $latestSession = RfidPanelSession::query()
                ->where('room', $room)
                ->orderByDesc('panel_session_id')
                ->first();
            $panelLabel = trim((string) ($assignedDevice?->label ?? $latestSession?->panel_id ?? ''));

            if ($panelLabel !== '') {
                $pinHash = (string) PanelDevice::query()
                    ->where('label', $panelLabel)
                    ->where('is_active', true)
                    ->value('pin_hash');
            }
        }

        if ($pinHash === '') {
            $pinHash = SystemSetting::string(SystemSetting::PANEL_PIN_HASH, '');
        }

        $pinMatches = $pinHash !== ''
            ? Hash::check((string) $validated['pin'], $pinHash)
            : (string) $validated['pin'] === (string) config('panel.pin', '1234');

        if (! $pinMatches) {
            return response()->json([
                'success' => false,
                'message' => 'Incorrect PIN. Please try again.',
            ], 401);
        }

        $consoleUser = User::withTrashed()->updateOrCreate(
            ['email' => 'console@rfid-panel.local'],
            [
                'name' => SystemSetting::string(SystemSetting::PANEL_DEVICE_LABEL, 'Attendance Console'),
                'password' => Hash::make(Str::random(40)),
                'role' => 'console',
            ],
        );

        if (method_exists($consoleUser, 'restore') && $consoleUser->trashed()) {
            $consoleUser->restore();
        }

        Auth::login($consoleUser);
        $request->session()->regenerate();

        return response()->json(['success' => true]);
    }

    public function selectPanelRoom(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room' => ['required', 'string', 'max:255'],
        ]);

        session(['panel.room' => $validated['room']]);
        $this->openPanelRoomSession($validated['room'], $request->user());

        return response()->json(['success' => true]);
    }

    public function panelLogout(Request $request): JsonResponse
    {
        $room = trim((string) ($request->input('room') ?? session('panel.room')));

        if ($room !== '') {
            $panelSession = RfidPanelSession::query()
                ->where('room', $room)
                ->whereNull('ended_at')
                ->latest('panel_session_id')
                ->first();

            if ($panelSession) {
                $panelSession->forceFill([
                    'status' => 'offline',
                    'is_listening' => false,
                    'ended_at' => now(),
                ])->save();
            }

            $attendanceSession = DB::table('attendance_sessions')
                ->where('room', $room)
                ->whereDate('date', now()->toDateString())
                ->whereNull('time_end')
                ->orderByDesc('attendance_id')
                ->first();

            if ($attendanceSession) {
                if ($attendanceSession->status === 'attendance') {
                    $this->finalizeCuttingStudents($attendanceSession);
                }

                DB::table('attendance_sessions')
                    ->where('attendance_id', $attendanceSession->attendance_id)
                    ->update([
                        'status' => 'offline',
                        'time_end' => now()->format('H:i:s'),
                        'updated_at' => now(),
                    ]);
            }
        }

        Auth::logout();
        $request->session()->forget('panel.room');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'redirect' => route('attendanceControlPanel.login'),
        ]);
    }

    public function panelStatus(Request $request): JsonResponse
    {
        $room = trim((string) ($request->input('room') ?? $this->currentPanelRoom()));

        if ($room === '') {
            return response()->json([
                'ok' => true,
                'logout_required' => true,
                'message' => 'No panel room is assigned.',
            ]);
        }

        $session = RfidPanelSession::query()
            ->where('room', $room)
            ->orderByDesc('panel_session_id')
            ->first();

        $logoutRequired = ! $session
            || $session->ended_at !== null
            || strtolower((string) $session->status) === 'offline';

        return response()->json([
            'ok' => true,
            'logout_required' => $logoutRequired,
            'status' => $session?->status ?? 'offline',
            'featureSettings' => SystemSetting::featureFlags(),
            'demoAttendancePanel' => SystemSetting::demoAttendancePanelSettings(),
            'message' => $logoutRequired ? 'This panel was logged out by an administrator.' : null,
        ]);
    }

    private function currentPanelRoom(): ?string
    {
        $room = trim((string) session('panel.room', ''));

        if ($room !== '') {
            return $room;
        }

        $user = Auth::user();
        if (strtolower(trim((string) $user?->role)) !== 'console') {
            return null;
        }

        $query = RfidPanelSession::query()
            ->whereNull('ended_at')
            ->where('status', '!=', 'offline')
            ->latest('panel_session_id');

        $session = (clone $query)
            ->where('opened_by_user_id', $user?->user_id)
            ->first()
            ?? $query->first();

        $room = trim((string) ($session?->room ?? ''));
        if ($room === '') {
            return null;
        }

        session(['panel.room' => $room]);

        return $room;
    }

    private function openPanelRoomSession(string $room, ?User $user): void
    {
        $session = RfidPanelSession::query()
            ->where('room', $room)
            ->whereNull('ended_at')
            ->latest('panel_session_id')
            ->first();

        if (! $session) {
            $session = new RfidPanelSession;
            $session->room = $room;
            $session->panel_id = $this->panelLabelForRoom($room);
        }

        $session->status = 'online';
        $session->opened_by_user_id = $user?->user_id;
        $session->is_listening = true;
        $session->listening_started_at ??= now();
        $session->paused_at = null;
        $session->ended_at = null;
        $session->save();
    }

    private function panelPayload(): array
    {
        return [
            'rooms' => $this->panelRooms(),
            'panelDeviceLabel' => SystemSetting::string(SystemSetting::PANEL_DEVICE_LABEL, 'Attendance Console'),
            'demoAttendancePanel' => SystemSetting::demoAttendancePanelSettings(),
            'demoInstructorRfids' => User::query()
                ->whereRaw('LOWER(role) = ?', ['instructor'])
                ->whereNotNull('rfid_tag')
                ->where('rfid_tag', '!=', '')
                ->orderByRaw('CASE WHEN email = ? THEN 0 ELSE 1 END', ['instructor@sample.com'])
                ->orderBy('name')
                ->pluck('rfid_tag')
                ->values()
                ->all(),
            'demoStudentRfids' => Students::query()
                ->whereNotNull('rfid_tag')
                ->where('rfid_tag', '!=', '')
                ->where('status', 'active')
                ->orderBy('student_number')
                ->pluck('rfid_tag')
                ->values()
                ->all(),
            'borrowItemsCatalog' => Item::query()
                ->whereNotNull('barcode')
                ->select(['item_id', 'name', 'sku', 'description', 'barcode', 'status'])
                ->orderBy('name')
                ->get()
                ->map(function ($device) {
                    return [
                        'name' => $device->name,
                        'id' => $device->sku ?? ('ITEM-'.$device->item_id),
                        'type' => $device->description ?? 'Device',
                        'barcode' => (string) $device->barcode,
                        'status' => $device->status,
                    ];
                })
                ->values()
                ->all(),
            'borrowItemsByRfid' => $this->buildBorrowItemsByRfid(),
            'featureSettings' => SystemSetting::featureFlags(),
            'emergencyTypes' => EmergencyType::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['emergency_type_id', 'name', 'category', 'default_message'])
                ->map(fn (EmergencyType $type) => [
                    'emergency_type_id' => $type->emergency_type_id,
                    'name' => $type->name,
                    'category' => $type->category,
                    'default_message' => $type->default_message,
                ])
                ->values()
                ->all(),
            'emergencyHotlines' => EmergencyHotline::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['emergency_hotline_id', 'name', 'category', 'phone_number', 'contact_person', 'sms_enabled', 'notes'])
                ->map(fn (EmergencyHotline $hotline) => [
                    'emergency_hotline_id' => $hotline->emergency_hotline_id,
                    'name' => $hotline->name,
                    'category' => $hotline->category,
                    'phone_number' => $hotline->phone_number,
                    'contact_person' => $hotline->contact_person,
                    'sms_enabled' => $hotline->sms_enabled,
                    'notes' => $hotline->notes,
                ])
                ->values()
                ->all(),
            'studentToastSeconds' => config('panel.student_toast_seconds', 15),
            'studentInfoVisibleSeconds' => config('panel.student_info_visible_seconds', 10),
        ];
    }

    private function panelLabelForRoom(string $room): string
    {
        return (string) (PanelDevice::query()
            ->whereHas('laboratory', fn ($query) => $query->where('name', $room))
            ->value('label')
            ?: SystemSetting::string(SystemSetting::PANEL_DEVICE_LABEL, 'Attendance Console'));
    }

    private function panelRooms(): array
    {
        $latestSessionsByRoom = RfidPanelSession::query()
            ->orderByDesc('created_at')
            ->get()
            ->unique('room')
            ->keyBy('room');

        $rooms = Schedule::query()
            ->select('room')
            ->whereNotNull('room')
            ->distinct()
            ->orderBy('room')
            ->pluck('room')
            ->merge(
                RfidPanelSession::query()
                    ->select('room')
                    ->whereNotNull('room')
                    ->distinct()
                    ->pluck('room')
            )
            ->merge(
                DB::table('laboratories')
                    ->select('name')
                    ->whereNotNull('name')
                    ->pluck('name')
            )
            ->map(fn ($room) => trim((string) $room))
            ->filter(fn (string $room) => $room !== '')
            ->unique()
            ->sort()
            ->values()
            ->map(function (string $room) use ($latestSessionsByRoom) {
                $latestSession = $latestSessionsByRoom->get($room);
                $status = $latestSession?->status ?? 'offline';

                return [
                    'name' => $room,
                    'status' => $status,
                    'label' => $status === 'offline' ? 'Not in use' : str_replace('_', ' ', $status),
                ];
            })
            ->values()
            ->all();

        return $rooms;
    }

    private function storeStudentFaceCapture(string $dataUrl, Students $student): ?string
    {
        $base64 = preg_replace('/^data:[^;]+;base64,/', '', $dataUrl);
        $bytes = base64_decode((string) $base64, true);

        if ($bytes === false || strlen($bytes) === 0) {
            return null;
        }

        $fileName = sprintf(
            'student_faces/%s-panel-%s.jpg',
            Str::slug((string) $student->student_number),
            now()->format('YmdHis')
        );

        return Storage::disk('public')->put($fileName, $bytes) ? $fileName : null;
    }

    private function logInstructorOverride(User $instructor, string $studentRfid, string $reason): void
    {
        ActivityLog::query()->create([
            'user_id' => $instructor->user_id,
            'action' => 'verify',
            'table_name' => 'attendance_logs',
            'description' => sprintf(
                'Instructor override authorized attendance for student RFID %s because %s.',
                $studentRfid,
                str_replace('_', ' ', $reason),
            ),
        ]);
    }

    private function buildBorrowItemsByRfid(): array
    {
        $map = [];

        $borrowings = Borrowing::with(['student', 'instructor', 'items.item'])
            ->whereIn('status', ['active', 'overdue'])
            ->get();

        foreach ($borrowings as $borrowing) {
            $rfid = $borrowing->borrower_type === 'student'
                ? ($borrowing->student->rfid_tag ?? null)
                : ($borrowing->instructor->rfid_tag ?? null);

            $rfidKey = strtolower(trim((string) $rfid));
            if ($rfidKey === '') {
                continue;
            }

            if (! isset($map[$rfidKey])) {
                $map[$rfidKey] = [
                    'hasActiveBorrowing' => false,
                    'items' => [],
                ];
            }

            $borrowingStatus = strtolower((string) ($borrowing->status ?? 'active'));
            if (in_array($borrowingStatus, ['active', 'overdue'], true)) {
                $map[$rfidKey]['hasActiveBorrowing'] = true;
            }

            foreach ($borrowing->items as $item) {
                $borrowedItem = $item->item;
                if (! $borrowedItem || empty($borrowedItem->barcode)) {
                    continue;
                }

                $barcode = (string) $borrowedItem->barcode;
                $map[$rfidKey]['items'][$barcode] = [
                    'name' => $borrowedItem->name,
                    'id' => $borrowedItem->sku ?? ('ITEM-'.$borrowedItem->item_id),
                    'type' => $borrowedItem->description ?? 'Device',
                    'barcode' => $barcode,
                    'status' => (strtolower((string) ($item->status ?? 'borrowed')) === 'borrowed') ? 'Borrowed' : ucfirst((string) $item->status),
                ];
            }
        }

        foreach ($map as $key => $entry) {
            $map[$key]['items'] = array_values($entry['items']);
        }

        return $map;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = request()->user();
        $role = strtolower(trim((string) $user?->role));
        $isInstructor = $role === 'instructor';
        $instructorId = $isInstructor
            ? Instructor::query()->where('user_id', $user?->user_id)->value('instructor_id')
            : null;
        $handledSectionIds = $isInstructor
            ? Schedule::query()
                ->where('instructor_id', $instructorId ?: 0)
                ->pluck('section_id')
                ->unique()
                ->values()
            : collect();

        $currentSchedule = Schedule::query()
            ->with(['subject.user', 'section.strand', 'instructor.user'])
            ->when($isInstructor, fn ($scheduleQuery) => $scheduleQuery->where('instructor_id', $instructorId ?: 0))
            ->orderByDesc('scheduled_id')
            ->first();

        $recentScans = AttendanceLog::query()
            ->with(['student.section.strand', 'student.strand', 'attendance.schedule.subject.user', 'attendance.schedule.instructor.user'])
            ->when($isInstructor, function ($attendanceLogQuery) use ($instructorId) {
                $attendanceLogQuery->whereHas('attendance.schedule', fn ($scheduleQuery) => $scheduleQuery->where('instructor_id', $instructorId ?: 0));
            })
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->map(function (AttendanceLog $log) {
                $student = $log->student;
                $attendance = $log->attendance;
                $schedule = $attendance?->schedule;
                $subject = $schedule?->subject ?? $attendance?->subjectRecord;

                return [
                    'id' => $log->id,
                    'student' => trim(($student?->first_name ?? '').' '.($student?->last_name ?? '')),
                    'studentNumber' => $student?->student_number,
                    'subject' => $subject?->subject_name ?? $attendance?->subject,
                    'section' => $student?->section?->section_name,
                    'strand' => $student->strand?->strand_code ?? $student->section?->strand?->strand_code,
                    'time' => $this->formatTime($log->time_in) ?? $this->formatTime($attendance?->time_in),
                    'status' => ucfirst((string) ($log->status ?? $attendance?->status ?? 'pending')),
                ];
            })
            ->values();

        $registeredStudents = Students::query()
            ->with(['section.strand', 'strand'])
            ->whereNotNull('rfid_tag')
            ->when($isInstructor, fn ($studentQuery) => $studentQuery->whereIn('section_id', $handledSectionIds->all()))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function (Students $student) {
                return [
                    'rfid' => (string) $student->rfid_tag,
                    'name' => trim($student->first_name.' '.$student->last_name),
                    'studentId' => $student->student_number,
                    'section' => $student->section?->section_name,
                    'strand' => $student->strand?->strand_code,
                ];
            })
            ->values();

        return Inertia::render('AttendanceScanner', [
            'session' => $currentSchedule ? [
                'instructor' => $currentSchedule->instructor?->user?->name ?? $currentSchedule->subject?->user?->name ?? 'Unassigned Instructor',
                'subject' => $currentSchedule->subject?->subject_name ?? 'Unassigned Subject',
                'subjectCode' => $currentSchedule->subject?->subject_code,
                'section' => $currentSchedule->section?->section_name ?? 'Unassigned Section',
                'strand' => $currentSchedule->section?->strand?->strand_code,
                'room' => $currentSchedule->room,
                'weekdays' => $currentSchedule->weekdays,
                'time' => trim(($this->formatTime($currentSchedule->time_start) ?? 'N/A').' - '.($this->formatTime($currentSchedule->time_end) ?? 'N/A')),
            ] : null,
            'recentScans' => $recentScans,
            'registeredStudents' => $registeredStudents,
            'currentUserRole' => $role,
        ]);
    }

    public function scanner()
    {
        return $this->index();
    }

    public function logs(Request $request)
    {
        $filters = [
            'attendance_id' => trim((string) $request->input('attendance_id', '')),
            'date' => trim((string) $request->input('date', '')),
            'subject' => trim((string) $request->input('subject', '')),
            'instructor' => trim((string) $request->input('instructor', '')),
            'instructor_rfid' => trim((string) $request->input('instructor_rfid', '')),
        ];

        $user = $request->user();
        $role = strtolower(trim((string) $user?->role));
        $isAdmin = $role === 'admin';
        $isInstructor = $role === 'instructor';
        $instructorId = $isInstructor
            ? Instructor::query()->where('user_id', $user?->user_id)->value('instructor_id')
            : null;

        if (! $isAdmin) {
            $filters['instructor'] = '';
            $filters['instructor_rfid'] = '';
        }

        if ($isAdmin && $filters['instructor_rfid'] !== '') {
            $rfidInstructorUserId = User::query()
                ->whereRaw('LOWER(rfid_tag) = ?', [strtolower($filters['instructor_rfid'])])
                ->whereRaw('LOWER(role) = ?', ['instructor'])
                ->value('user_id');

            $filters['instructor'] = $rfidInstructorUserId ? (string) $rfidInstructorUserId : '__not_found__';
        }

        $absentDefaultDays = SystemSetting::integer(SystemSetting::ATTENDANCE_ABSENT_DEFAULT_DAYS, 15);

        $applySessionScope = function ($query) use ($isInstructor, $instructorId, $filters) {
            if ($isInstructor) {
                $query->where('schedules.instructor_id', $instructorId ?: 0);
            }

            if ($filters['attendance_id'] !== '') {
                $query->where('attendance_sessions.attendance_id', $filters['attendance_id']);
            }

            if ($filters['date'] !== '') {
                $query->whereDate('attendance_sessions.date', $filters['date']);
            }

            if ($filters['subject'] !== '') {
                $query->where('subjects.subject_id', $filters['subject']);
            }

            if ($filters['instructor'] !== '') {
                if ($filters['instructor'] === '__not_found__') {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->where('instructor_users.user_id', $filters['instructor']);
                }
            }
        };

        $sessionJoin = function ($query) {
            $query
                ->leftJoin('schedules', 'schedules.scheduled_id', '=', 'attendance_sessions.schedule_id')
                ->leftJoin('instructors', 'instructors.instructor_id', '=', 'schedules.instructor_id')
                ->leftJoin('users as instructor_users', 'instructor_users.user_id', '=', 'instructors.user_id')
                ->leftJoin('sections', 'sections.section_id', '=', 'schedules.section_id')
                ->leftJoin('subjects', function ($join) {
                    $join->on('subjects.subject_code', '=', 'attendance_sessions.subject_code')
                        ->on('subjects.section_id', '=', 'schedules.section_id');
                });
        };

        $query = DB::table('attendance_logs')
            ->join('attendance_sessions', 'attendance_sessions.attendance_id', '=', 'attendance_logs.attendance_id')
            ->leftJoin('attendances', 'attendances.attendance_id', '=', 'attendance_logs.main_attendance_id')
            ->leftJoin('students', 'students.student_id', '=', 'attendance_logs.student_id')
            ->leftJoin('sections as student_sections', 'student_sections.section_id', '=', 'students.section_id')
            ->leftJoin('strands', 'strands.strand_id', '=', 'students.strand_id');

        $sessionJoin($query);

        $applySessionScope($query);

        $logs = $query
            ->orderByDesc('attendance_sessions.date')
            ->orderByDesc('attendance_sessions.time_start')
            ->orderByDesc('attendance_logs.id')
            ->select([
                'attendance_logs.id',
                'attendance_logs.student_id',
                'attendance_logs.schedule_id',
                'attendance_logs.time_in',
                'attendance_logs.time_out',
                'attendance_logs.status',
                'attendance_logs.time_in_face_path',
                'attendance_logs.time_out_face_path',
                'attendance_logs.verification_method',
                'attendance_logs.tap_datetime',
                'attendance_logs.tap_type',
                'attendance_logs.tap_sequence_number',
                'attendance_logs.validation_result',
                'attendance_logs.remarks as log_remarks',
                'attendances.attendance_id as main_attendance_id',
                'attendances.time_in as attendance_time_in',
                'attendances.time_end as attendance_scheduled_end',
                'attendances.time_out as attendance_time_out',
                'attendances.status as attendance_status',
                'attendances.room_status',
                'attendances.total_taps',
                'attendances.remarks as attendance_remarks',
                'attendance_sessions.attendance_id as session_id',
                'attendance_sessions.date',
                'attendance_sessions.room',
                'attendance_sessions.time_start',
                'attendance_sessions.time_end',
                'attendance_sessions.status as session_status',
                'subjects.subject_name',
                'subjects.subject_id',
                'sections.section_name as schedule_section_name',
                'sections.school_year as schedule_school_year',
                'instructor_users.user_id as instructor_id',
                'instructor_users.name as instructor_name',
                'instructor_users.rfid_tag as instructor_rfid',
                'students.first_name',
                'students.last_name',
                'students.student_number',
                'students.school_year as student_school_year',
                'student_sections.section_name as student_section_name',
                'strands.strand_code',
            ])
            ->get()
            ->map(function ($log) use ($isInstructor, $absentDefaultDays) {
                return [
                    'id' => $log->id,
                    'session_id' => $log->session_id,
                    'main_attendance_id' => $log->main_attendance_id,
                    'student_id' => $log->student_id,
                    'schedule_id' => $log->schedule_id,
                    'student' => trim(($log->first_name ?? '').' '.($log->last_name ?? '')) ?: 'Unknown Student',
                    'student_number' => $log->student_number,
                    'subject' => $log->subject_name ?? 'N/A',
                    'section' => $log->student_section_name ?? $log->schedule_section_name ?? 'N/A',
                    'school_year' => $log->student_school_year ?? $log->schedule_school_year,
                    'instructor' => $log->instructor_name ?? 'Unassigned Instructor',
                    'instructor_id' => $log->instructor_id,
                    'instructor_rfid' => $log->instructor_rfid,
                    'room' => $log->room ?? 'N/A',
                    'date' => $log->date,
                    'session_time' => trim(($this->formatTime($log->time_start) ?? 'N/A').' - '.($this->formatTime($log->time_end) ?? 'N/A')),
                    'time' => $log->tap_datetime ? Carbon::parse($log->tap_datetime)->format('g:i A') : ($this->formatTime($log->time_in) ?? 'N/A'),
                    'time_in' => $this->formatTime($log->attendance_time_in) ?? $this->formatTime($log->time_in),
                    'time_out' => $this->formatTime($log->attendance_time_out) ?? $this->formatTime($log->time_out),
                    'tap_type' => $log->tap_type ?? 'Check-in',
                    'tap_sequence_number' => $log->tap_sequence_number,
                    'room_status' => ucfirst((string) ($log->room_status ?? 'outside')),
                    'validation_result' => ucfirst((string) ($log->validation_result ?? 'valid')),
                    'remarks' => $log->log_remarks ?? $log->attendance_remarks,
                    'status' => $this->attendanceDisplayStatus($log, $log),
                    'time_in_image_url' => $log->time_in_face_path ? route('attendance.evidence', ['attendanceLog' => $log->id, 'moment' => 'time-in']) : null,
                    'time_out_image_url' => $log->time_out_face_path ? route('attendance.evidence', ['attendanceLog' => $log->id, 'moment' => 'time-out']) : null,
                    'verification_method' => $log->verification_method,
                    'editable' => $isInstructor && Carbon::parse($log->date)->betweenIncluded(
                        now()->subDays(max(1, min(365, $absentDefaultDays)) - 1)->startOfDay(),
                        now()->endOfDay()
                    ),
                ];
            })
            ->values();

        $logs = $this->appendAbsentAttendanceLogs($logs, $filters, $isAdmin, $isInstructor, $instructorId, $absentDefaultDays);
        $logs = $this->combineAttendanceLogRows($logs);

        $sessionOptionsQuery = DB::table('attendance_sessions');
        $sessionJoin($sessionOptionsQuery);

        $sessionOptions = $sessionOptionsQuery
            ->when($isInstructor, fn ($sessionQuery) => $sessionQuery->where('schedules.instructor_id', $instructorId ?: 0))
            ->when($isAdmin && $filters['instructor'] !== '' && $filters['instructor'] !== '__not_found__', fn ($sessionQuery) => $sessionQuery->where('instructor_users.user_id', $filters['instructor']))
            ->when($filters['date'] !== '', fn ($sessionQuery) => $sessionQuery->whereDate('attendance_sessions.date', $filters['date']))
            ->when($filters['subject'] !== '', fn ($sessionQuery) => $sessionQuery->where('subjects.subject_id', $filters['subject']))
            ->orderByDesc('attendance_sessions.date')
            ->orderByDesc('attendance_sessions.time_start')
            ->select([
                'attendance_sessions.attendance_id',
                'attendance_sessions.date',
                'attendance_sessions.room',
                'attendance_sessions.time_start',
                'attendance_sessions.time_end',
                'subjects.subject_name',
                'subjects.year_level',
                'sections.section_name',
                'instructor_users.name as instructor_name',
            ])
            ->get()
            ->map(fn ($session) => [
                'value' => $session->attendance_id,
                'label' => trim(implode(' | ', array_filter([
                    $session->date,
                    trim(implode(' ', array_filter([
                        $session->subject_name,
                        trim(implode('-', array_filter([$session->year_level, $session->section_name]))),
                    ]))),
                    $session->instructor_name,
                    trim(($this->formatTime($session->time_start) ?? '').' - '.($this->formatTime($session->time_end) ?? '')),
                ]))),
            ])
            ->values();

        $dateOptionsQuery = DB::table('attendance_sessions');
        $sessionJoin($dateOptionsQuery);

        $dateOptions = $dateOptionsQuery
            ->when($isInstructor, fn ($dateQuery) => $dateQuery->where('schedules.instructor_id', $instructorId ?: 0))
            ->when($isAdmin && $filters['instructor'] !== '' && $filters['instructor'] !== '__not_found__', fn ($dateQuery) => $dateQuery->where('instructor_users.user_id', $filters['instructor']))
            ->when($filters['subject'] !== '', fn ($dateQuery) => $dateQuery->where('subjects.subject_id', $filters['subject']))
            ->whereNotNull('attendance_sessions.date')
            ->distinct()
            ->orderByDesc('attendance_sessions.date')
            ->pluck('attendance_sessions.date')
            ->map(fn ($date) => [
                'value' => (string) $date,
                'label' => Carbon::parse($date)->format('m/d/Y'),
            ])
            ->values();

        $subjectOptionsQuery = DB::table('subjects')
            ->leftJoin('sections', 'sections.section_id', '=', 'subjects.section_id')
            ->leftJoin('schedules', function ($join) {
                $join->on('schedules.section_id', '=', 'subjects.section_id')
                    ->on('schedules.subject_code', '=', 'subjects.subject_code');
            })
            ->leftJoin('instructors', 'instructors.instructor_id', '=', 'schedules.instructor_id')
            ->leftJoin('users as subject_instructor_users', 'subject_instructor_users.user_id', '=', 'instructors.user_id');

        return Inertia::render('AttendanceLogs', [
            'logs' => $logs,
            'filters' => $filters,
            'currentUserRole' => $role,
            'canInspectAllAttendance' => $isAdmin,
            'absentDefaultDays' => $absentDefaultDays,
            'canEditAttendance' => $isInstructor,
            'attendanceSessionOptions' => $sessionOptions,
            'subjectOptions' => $subjectOptionsQuery
                ->when($isInstructor, fn ($subjectQuery) => $subjectQuery->where('schedules.instructor_id', $instructorId ?: 0))
                ->when($isAdmin && $filters['instructor'] !== '' && $filters['instructor'] !== '__not_found__', fn ($subjectQuery) => $subjectQuery->where('subject_instructor_users.user_id', $filters['instructor']))
                ->select([
                    'subjects.subject_id',
                    'subjects.subject_name',
                    'subjects.year_level',
                    'sections.section_name',
                ])
                ->distinct()
                ->orderBy('subjects.subject_name')
                ->orderBy('subjects.year_level')
                ->orderBy('sections.section_name')
                ->get()
                ->map(fn ($subject) => [
                    'value' => $subject->subject_id,
                    'label' => trim(implode(' ', array_filter([
                        $subject->subject_name,
                        trim(implode('-', array_filter([$subject->year_level, $subject->section_name]))),
                    ]))),
                ])
                ->values(),
            'dateOptions' => $dateOptions,
            'instructorOptions' => $isAdmin ? User::query()
                ->whereIn('user_id', Instructor::query()->whereNotNull('user_id')->pluck('user_id')->unique())
                ->orderBy('name')
                ->get(['user_id', 'name', 'rfid_tag'])
                ->map(fn (User $user) => [
                    'value' => $user->user_id,
                    'label' => $user->name,
                    'rfid' => $user->rfid_tag,
                ])
                ->values() : [],
        ]);
    }

    public function updateAttendanceStatus(Request $request)
    {
        $validated = $request->validate([
            'session_id' => ['required', 'integer'],
            'student_id' => ['required', 'integer', 'exists:students,student_id'],
            'status' => ['required', 'in:present,late,absent,excused'],
            'remarks' => ['nullable', 'string', 'max:1000', 'required_if:status,excused'],
        ]);

        $user = $request->user();
        abort_unless(strtolower(trim((string) $user?->role)) === 'instructor', 403);

        $instructorId = Instructor::query()->where('user_id', $user->user_id)->value('instructor_id');
        abort_unless($instructorId, 403, 'No instructor profile is linked to this account.');

        $session = DB::table('attendance_sessions')
            ->join('schedules', 'schedules.scheduled_id', '=', 'attendance_sessions.schedule_id')
            ->where('attendance_sessions.attendance_id', $validated['session_id'])
            ->where('schedules.instructor_id', $instructorId)
            ->select([
                'attendance_sessions.attendance_id',
                'attendance_sessions.schedule_id',
                'attendance_sessions.subject_code',
                'attendance_sessions.date',
                'attendance_sessions.room',
                'attendance_sessions.time_start',
                'attendance_sessions.time_end',
                'schedules.section_id',
            ])
            ->first();

        abort_unless($session, 403, 'You may only edit attendance for your assigned sessions.');

        $student = Students::query()
            ->whereKey($validated['student_id'])
            ->where('section_id', $session->section_id)
            ->firstOrFail();

        $editDays = max(1, min(365, SystemSetting::integer(SystemSetting::ATTENDANCE_ABSENT_DEFAULT_DAYS, 15)));
        $sessionDate = Carbon::parse($session->date)->startOfDay();
        $earliestEditableDate = now()->subDays($editDays - 1)->startOfDay();
        abort_if(
            $sessionDate->lt($earliestEditableDate) || $sessionDate->gt(now()->endOfDay()),
            422,
            "Attendance can only be edited within the latest {$editDays} day(s)."
        );

        DB::transaction(function () use ($request, $validated, $session, $student, $user) {
            $attendance = Attendance::query()
                ->where('student_id', $student->student_id)
                ->where('schedule_id', $session->schedule_id)
                ->whereDate('date', $session->date)
                ->first();

            $oldStatus = strtolower((string) ($attendance?->status ?: 'absent'));
            $remarks = trim((string) ($validated['remarks'] ?? ''));
            $auditRemark = sprintf(
                'Instructor %s changed attendance from %s to %s.%s',
                $user->name,
                ucfirst($oldStatus),
                ucfirst($validated['status']),
                $remarks !== '' ? ' Note: '.$remarks : ''
            );

            if (! $attendance) {
                $attendance = Attendance::query()->create([
                    'student_id' => $student->student_id,
                    'schedule_id' => $session->schedule_id,
                    'date' => $session->date,
                    'time_start' => $session->time_start,
                    'time_end' => $session->time_end,
                    'time_in' => null,
                    'time_out' => null,
                    'check_in_status' => in_array($validated['status'], ['present', 'late'], true) ? $validated['status'] : null,
                    'status' => $validated['status'],
                    'room_status' => 'outside',
                    'total_taps' => 0,
                    'remarks' => $remarks !== '' ? $remarks : $auditRemark,
                    'subject_code' => $session->subject_code,
                    'room' => $session->room,
                ]);
            } else {
                $attendance->update([
                    'status' => $validated['status'],
                    'check_in_status' => in_array($validated['status'], ['present', 'late'], true) ? $validated['status'] : null,
                    'remarks' => $remarks !== '' ? $remarks : $auditRemark,
                ]);
            }

            AttendanceLog::query()->create([
                'attendance_id' => $session->attendance_id,
                'main_attendance_id' => $attendance->attendance_id,
                'student_id' => $student->student_id,
                'schedule_id' => $session->schedule_id,
                'status' => $validated['status'],
                'verification_method' => 'instructor_manual_edit',
                'is_late' => $validated['status'] === 'late',
                'tap_datetime' => now(),
                'tap_type' => 'Manual Edit',
                'device_scanner_id' => 'Instructor Portal',
                'location' => $session->room,
                'validation_result' => 'Manual Override',
                'remarks' => $auditRemark,
            ]);

            ActivityLog::query()->create([
                'event_id' => (string) Str::uuid(),
                'user_id' => $user->user_id,
                'user_name' => $user->name,
                'user_role' => $user->role,
                'action' => 'attendance_status_changed',
                'table_name' => 'attendances',
                'module' => 'attendance',
                'outcome' => 'success',
                'severity' => 'info',
                'subject_type' => 'attendance',
                'subject_id' => (string) $attendance->attendance_id,
                'route_name' => $request->route()?->getName(),
                'http_method' => $request->method(),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
                'status_code' => 200,
                'description' => $auditRemark.' Student: '.$student->student_number.'.',
                'created_at' => now(),
            ]);
        });

        return back()->with('success', 'Attendance status updated and logged.');
    }

    public function evidence(Request $request, AttendanceLog $attendanceLog, string $moment): StreamedResponse
    {
        abort_unless(in_array($moment, ['time-in', 'time-out'], true), 404);

        $record = DB::table('attendance_logs')
            ->join('attendance_sessions', 'attendance_sessions.attendance_id', '=', 'attendance_logs.attendance_id')
            ->leftJoin('schedules', 'schedules.scheduled_id', '=', 'attendance_sessions.schedule_id')
            ->where('attendance_logs.id', $attendanceLog->id)
            ->select([
                'attendance_logs.student_id',
                'attendance_logs.time_in_face_path',
                'attendance_logs.time_out_face_path',
                'schedules.instructor_id',
            ])
            ->firstOrFail();

        $user = $request->user();
        $role = strtolower((string) $user?->role);
        $authorized = $role === 'admin';

        if ($role === 'instructor') {
            $authorized = (int) Instructor::query()->where('user_id', $user->user_id)->value('instructor_id') === (int) $record->instructor_id;
        } elseif ($role === 'student') {
            $authorized = (int) Students::query()->where('email', $user->email)->value('student_id') === (int) $record->student_id;
        } elseif ($role === 'parent') {
            $authorized = $user->linkedStudents()->where('students.student_id', $record->student_id)->exists();
        }

        abort_unless($authorized, 403);

        $path = $moment === 'time-in' ? $record->time_in_face_path : $record->time_out_face_path;
        abort_unless($path && Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path);
    }

    public function scan(Request $request)
    {
        $validated = $request->validate([
            'rfid_tag' => 'required|string',
        ]);

        $schedule = Schedule::query()
            ->with(['subject.user', 'section.strand'])
            ->orderByDesc('scheduled_id')
            ->first();

        if (! $schedule || ! $schedule->subject) {
            return response()->json([
                'success' => false,
                'message' => 'No active subject schedule is available.',
            ], 422);
        }

        $student = Students::query()
            ->with(['section.strand', 'strand'])
            ->where('rfid_tag', $validated['rfid_tag'])
            ->first();

        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => 'RFID tag is not assigned to any student.',
            ], 404);
        }

        $now = Carbon::now();
        $status = $schedule->time_start && $now->format('H:i:s') > $schedule->time_start ? 'late' : 'present';

        $attendance = Attendance::query()->firstOrNew([
            'student_id' => $student->student_id,
            'subject_id' => $schedule->subject->subject_id,
            'schedule_id' => $schedule->scheduled_id,
            'date' => $now->toDateString(),
        ]);

        $action = 'time_in';
        $message = 'Attendance recorded for '.trim($student->first_name.' '.$student->last_name).'.';

        if (! $attendance->exists) {
            $attendance->fill([
                'time_start' => $schedule->time_start,
                'time_end' => $schedule->time_end,
                'time_in' => $now->format('H:i:s'),
                'status' => $status,
                'subject' => $schedule->subject->subject_name,
                'room' => $schedule->room,
            ]);
            $attendance->save();

            AttendanceLog::create([
                'attendance_id' => $attendance->attendance_id,
                'main_attendance_id' => $attendance->attendance_id,
                'student_id' => $student->student_id,
                'schedule_id' => $schedule->scheduled_id,
                'time_in' => $now->format('H:i:s'),
                'status' => $status,
                'tap_datetime' => $now->toDateTimeString(),
                'tap_type' => 'Check-in',
                'tap_sequence_number' => 1,
                'location' => $schedule->room,
                'validation_result' => 'valid',
            ]);
        } elseif (! $attendance->time_out) {
            $attendance->update([
                'time_out' => $now->format('H:i:s'),
            ]);

            $latestLog = AttendanceLog::query()
                ->where('attendance_id', $attendance->attendance_id)
                ->where('student_id', $student->student_id)
                ->latest('id')
                ->first();

            if ($latestLog && ! $latestLog->time_out) {
                $latestLog->update([
                    'time_out' => $now->format('H:i:s'),
                ]);
            } else {
                AttendanceLog::create([
                    'attendance_id' => $attendance->attendance_id,
                    'main_attendance_id' => $attendance->attendance_id,
                    'student_id' => $student->student_id,
                    'schedule_id' => $schedule->scheduled_id,
                    'time_out' => $now->format('H:i:s'),
                    'status' => $attendance->status,
                    'tap_datetime' => $now->toDateTimeString(),
                    'tap_type' => 'Check-out',
                    'tap_sequence_number' => 2,
                    'location' => $schedule->room,
                    'validation_result' => 'valid',
                ]);
            }

            $action = 'time_out';
            $message = 'Time out recorded for '.trim($student->first_name.' '.$student->last_name).'.';
        } else {
            $action = 'repeat_scan';
            $message = 'Attendance for this student is already completed today.';
        }

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'attendance_'.$action,
            'table_name' => 'attendance_logs',
            'description' => $message,
        ]);

        return response()->json([
            'success' => true,
            'message' => $message,
            'entry' => [
                'id' => $attendance->attendance_id.'-'.$now->timestamp,
                'student' => trim($student->first_name.' '.$student->last_name),
                'studentNumber' => $student->student_number,
                'subject' => $schedule->subject->subject_name,
                'section' => $student->section?->section_name,
                'time' => $now->format('g:i A'),
                'status' => ucfirst((string) $attendance->status),
            ],
        ]);
    }

    private function insertAttendanceTapLog(int $sessionId, Attendance $attendance, Students $student, ?int $scheduleId, \Carbon\CarbonInterface $tapTime, string $tapType, int $sequence, string $room, string $validationResult, ?string $remarks = null, ?string $verificationMethod = null, ?string $verificationFacePath = null): int
    {
        $isCheckout = $tapType === 'Check-out';

        return (int) DB::table('attendance_logs')->insertGetId([
            'attendance_id' => $sessionId,
            'main_attendance_id' => $attendance->attendance_id,
            'student_id' => $student->student_id,
            'schedule_id' => $scheduleId,
            'time_in' => $tapTime->format('H:i:s'),
            'time_out' => $isCheckout ? $tapTime->format('H:i:s') : null,
            'status' => $attendance->status,
            'verification_method' => $verificationMethod,
            'time_in_face_path' => $isCheckout ? null : $verificationFacePath,
            'time_out_face_path' => $isCheckout ? $verificationFacePath : null,
            'is_late' => $attendance->check_in_status === 'late',
            'completion_reason' => $isCheckout ? 'time_out' : null,
            'tap_datetime' => $tapTime->toDateTimeString(),
            'tap_type' => $tapType,
            'tap_sequence_number' => $sequence,
            'device_scanner_id' => SystemSetting::string(SystemSetting::PANEL_DEVICE_LABEL, 'Attendance Console'),
            'location' => $room,
            'validation_result' => $validationResult,
            'remarks' => $remarks,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertInvalidAttendanceTapLog(int $sessionId, Students $student, ?int $scheduleId, \Carbon\CarbonInterface $tapTime, string $tapType, int $sequence, string $room, string $remarks): int
    {
        return (int) DB::table('attendance_logs')->insertGetId([
            'attendance_id' => $sessionId,
            'main_attendance_id' => null,
            'student_id' => $student->student_id,
            'schedule_id' => $scheduleId,
            'time_in' => $tapTime->format('H:i:s'),
            'time_out' => null,
            'status' => 'invalid',
            'tap_datetime' => $tapTime->toDateTimeString(),
            'tap_type' => $tapType,
            'tap_sequence_number' => $sequence,
            'device_scanner_id' => SystemSetting::string(SystemSetting::PANEL_DEVICE_LABEL, 'Attendance Console'),
            'location' => $room,
            'validation_result' => 'invalid',
            'remarks' => $remarks,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function instructorRfidAuthorizesTemporaryMovement(?Schedule $schedule, ?string $instructorRfid): bool
    {
        $rfid = strtolower(trim((string) $instructorRfid));

        if (! $schedule || ! $schedule->instructor_id || $rfid === '') {
            return false;
        }

        return DB::table('instructors')
            ->join('users', 'users.user_id', '=', 'instructors.user_id')
            ->where('instructors.instructor_id', $schedule->instructor_id)
            ->whereRaw('LOWER(users.rfid_tag) = ?', [$rfid])
            ->whereRaw('LOWER(users.role) = ?', ['instructor'])
            ->exists();
    }

    private function attendanceDisplayStatus(object $attendance, ?object $session = null): string
    {
        $status = strtolower((string) ($attendance->attendance_status ?? $attendance->status ?? 'pending'));
        $timeIn = $attendance->attendance_time_in ?? $attendance->time_in ?? null;
        $timeOut = $attendance->attendance_time_out ?? $attendance->time_out ?? null;

        if ($status === 'absent') {
            return 'Absent';
        }

        if ($status === 'excused') {
            return 'Excused';
        }

        if ($timeIn && ! $timeOut) {
            return $this->attendanceSessionHasEnded($attendance, $session) ? 'Incomplete Attendance' : 'Pending';
        }

        if ($timeOut) {
            return $status === 'late' ? 'Late' : 'Present';
        }

        return match ($status) {
            'late' => 'Late',
            'present' => 'Present',
            'incomplete_attendance', 'incomplete attendance', 'incomplete' => 'Incomplete Attendance',
            default => 'Pending',
        };
    }

    private function attendanceSessionHasEnded(object $attendance, ?object $session = null): bool
    {
        $sessionStatus = strtolower((string) ($session->session_status ?? $session->status ?? ''));
        $sessionTimeEnd = $session->time_end ?? null;

        if ($sessionTimeEnd || ($sessionStatus !== '' && $sessionStatus !== 'attendance')) {
            return true;
        }

        $date = (string) ($attendance->date ?? now()->toDateString());
        $endTime = $attendance->attendance_scheduled_end ?? $attendance->attendance_time_end ?? $attendance->time_end ?? $session->time_end ?? null;

        if (! $endTime) {
            return false;
        }

        $end = $this->scheduleDateTime($date, (string) $endTime);

        return $end ? now()->greaterThan($end) : false;
    }

    private function scheduleDateTime(string $date, ?string $time): ?Carbon
    {
        if (! $time) {
            return null;
        }

        try {
            return Carbon::parse($date.' '.$time);
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatTime(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? $value : date('g:i A', $timestamp);
    }

    private function appendAbsentAttendanceLogs($logs, array $filters, bool $isAdmin, bool $isInstructor, ?int $instructorId, int $absentDefaultDays)
    {
        $absentDefaultDays = max(1, min(365, $absentDefaultDays));
        $today = now()->toDateString();
        $startDate = now()->subDays($absentDefaultDays - 1)->toDateString();

        $sessionsQuery = DB::table('attendance_sessions')
            ->leftJoin('schedules', 'schedules.scheduled_id', '=', 'attendance_sessions.schedule_id')
            ->leftJoin('instructors', 'instructors.instructor_id', '=', 'schedules.instructor_id')
            ->leftJoin('users as instructor_users', 'instructor_users.user_id', '=', 'instructors.user_id')
            ->leftJoin('sections', 'sections.section_id', '=', 'schedules.section_id')
            ->leftJoin('subjects', function ($join) {
                $join->on('subjects.subject_code', '=', 'attendance_sessions.subject_code')
                    ->on('subjects.section_id', '=', 'schedules.section_id');
            })
            ->whereNotNull('schedules.section_id')
            ->where(function ($query) use ($today) {
                $query->whereDate('attendance_sessions.date', '<', $today)
                    ->orWhereNotNull('attendance_sessions.time_end');
            });

        if ($filters['attendance_id'] !== '') {
            $sessionsQuery->where('attendance_sessions.attendance_id', $filters['attendance_id']);
        }

        if ($filters['date'] !== '') {
            $sessionsQuery->whereDate('attendance_sessions.date', $filters['date']);
        } else {
            $sessionsQuery->whereBetween('attendance_sessions.date', [$startDate, $today]);
        }

        if ($filters['subject'] !== '') {
            $sessionsQuery->where('subjects.subject_id', $filters['subject']);
        }

        if ($isInstructor) {
            $sessionsQuery->where('schedules.instructor_id', $instructorId ?: 0);
        }

        if ($isAdmin && $filters['instructor'] !== '') {
            if ($filters['instructor'] === '__not_found__') {
                $sessionsQuery->whereRaw('1 = 0');
            } else {
                $sessionsQuery->where('instructor_users.user_id', $filters['instructor']);
            }
        }

        $sessions = $sessionsQuery
            ->orderByDesc('attendance_sessions.date')
            ->orderByDesc('attendance_sessions.time_start')
            ->select([
                'attendance_sessions.attendance_id as session_id',
                'attendance_sessions.schedule_id',
                'attendance_sessions.date',
                'attendance_sessions.room',
                'attendance_sessions.time_start',
                'attendance_sessions.time_end',
                'subjects.subject_name',
                'sections.section_id',
                'sections.section_name',
                'sections.school_year as schedule_school_year',
                'instructor_users.user_id as instructor_id',
                'instructor_users.name as instructor_name',
                'instructor_users.rfid_tag as instructor_rfid',
            ])
            ->get();

        if ($sessions->isEmpty()) {
            return $logs;
        }

        $absentLogs = collect();

        foreach ($sessions as $session) {
            $loggedStudentIds = DB::table('attendance_logs')
                ->where('attendance_id', $session->session_id)
                ->whereNotNull('student_id')
                ->pluck('student_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $students = Students::query()
                ->with(['section', 'strand'])
                ->where('section_id', $session->section_id)
                ->where('status', 'active')
                ->when($loggedStudentIds !== [], fn ($query) => $query->whereNotIn('student_id', $loggedStudentIds))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();

            foreach ($students as $student) {
                $absentLogs->push([
                    'id' => 'absent-'.$session->session_id.'-'.$student->student_id,
                    'session_id' => $session->session_id,
                    'main_attendance_id' => null,
                    'student_id' => $student->student_id,
                    'schedule_id' => $session->schedule_id,
                    'student' => trim(($student->first_name ?? '').' '.($student->last_name ?? '')) ?: 'Unknown Student',
                    'student_number' => $student->student_number,
                    'subject' => $session->subject_name ?? 'N/A',
                    'section' => $student->section?->section_name ?? $session->section_name ?? 'N/A',
                    'school_year' => $student->school_year ?? $session->schedule_school_year,
                    'instructor' => $session->instructor_name ?? 'Unassigned Instructor',
                    'instructor_id' => $session->instructor_id,
                    'instructor_rfid' => $session->instructor_rfid,
                    'room' => $session->room ?? 'N/A',
                    'date' => $session->date,
                    'session_time' => trim(($this->formatTime($session->time_start) ?? 'N/A').' - '.($this->formatTime($session->time_end) ?? 'N/A')),
                    'time' => 'Absent',
                    'time_in' => null,
                    'time_out' => null,
                    'tap_type' => 'No Tap',
                    'tap_sequence_number' => null,
                    'room_status' => 'Outside',
                    'validation_result' => 'Absent',
                    'remarks' => 'No valid check-in tap was recorded for this scheduled class.',
                    'status' => 'Absent',
                    'time_in_image_url' => null,
                    'time_out_image_url' => null,
                    'verification_method' => null,
                    'evidence_events' => [],
                    'editable' => $isInstructor && Carbon::parse($session->date)->betweenIncluded(
                        now()->subDays($absentDefaultDays - 1)->startOfDay(),
                        now()->endOfDay()
                    ),
                ]);
            }
        }

        return $logs->concat($absentLogs)->values();
    }

    private function combineAttendanceLogRows($logs)
    {
        return collect($logs)
            ->groupBy(function (array $log) {
                if ($log['main_attendance_id'] ?? null) {
                    return 'attendance-'.$log['main_attendance_id'];
                }

                if (($log['tap_type'] ?? null) !== 'No Tap') {
                    return implode('|', [
                        'session',
                        $log['session_id'] ?? '',
                        $log['student_number'] ?? $log['student'] ?? '',
                        $log['date'] ?? '',
                        $log['subject'] ?? '',
                    ]);
                }

                return 'log-'.$log['id'];
            })
            ->map(function ($items) {
                $orderedEvents = $items
                    ->sortBy(fn (array $item) => $item['tap_sequence_number'] ?? 999999)
                    ->values()
                    ->map(fn (array $item) => [
                        'id' => $item['id'],
                        'tap_type' => $item['tap_type'],
                        'tap_sequence_number' => $item['tap_sequence_number'],
                        'time' => $item['time'],
                        'room_status' => $item['room_status'],
                        'validation_result' => $item['validation_result'],
                        'remarks' => $item['remarks'],
                        'time_in_image_url' => $item['time_in_image_url'] ?? null,
                        'time_out_image_url' => $item['time_out_image_url'] ?? null,
                        'verification_method' => $item['verification_method'] ?? null,
                    ])
                    ->all();

                $summary = $items->firstWhere('tap_type', 'Check-out')
                    ?? $items->firstWhere('tap_type', 'Check-in')
                    ?? $items->first();

                $timeInEvent = collect($orderedEvents)->first(fn (array $event) => $event['tap_type'] === 'Check-in' && $event['time_in_image_url']);
                $timeOutEvent = collect($orderedEvents)->first(fn (array $event) => $event['tap_type'] === 'Check-out' && $event['time_out_image_url']);

                $summary['id'] = $summary['main_attendance_id'] ?? $summary['id'];
                $summary['tap_type'] = 'Attendance';
                $summary['tap_sequence_number'] = count($orderedEvents);
                $summary['time'] = count($orderedEvents).' tap'.(count($orderedEvents) === 1 ? '' : 's');
                $summary['time_in_image_url'] = $timeInEvent['time_in_image_url'] ?? null;
                $summary['time_out_image_url'] = $timeOutEvent['time_out_image_url'] ?? null;
                $summary['evidence_events'] = $orderedEvents;

                return $summary;
            })
            ->sortByDesc(fn (array $log) => trim(($log['date'] ?? '').' '.($log['time_in'] ?? '').' '.$log['id']))
            ->values();
    }

    private function matchesWeekday(string $weekdays, string $weekdayAbbr, string $weekdayFull): bool
    {
        $tokens = preg_split('/[,\-\/\s]+/', strtolower(trim($weekdays))) ?: [];
        $normalizedTokens = array_values(array_filter(array_map(function (string $token) {
            $token = strtolower(trim($token));
            if ($token === '') {
                return null;
            }

            return substr($token, 0, 3);
        }, $tokens)));

        $targetShort = strtolower(substr($weekdayAbbr, 0, 3));
        $targetFullShort = strtolower(substr($weekdayFull, 0, 3));

        return in_array($targetShort, $normalizedTokens, true)
            || in_array($targetFullShort, $normalizedTokens, true);
    }
}
