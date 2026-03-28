<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\Borrowing;
use App\Models\Item;
use App\Models\Instructor;
use App\Models\RfidPanelSession;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Students;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\AttendanceLog;
use Inertia\Inertia;

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

        if (!$session) {
            $session = new RfidPanelSession();
            $session->room = $validated['room'];
            $session->panel_id = 'attendance-control-panel';
        }

        $status = $validated['status'];

        $session->status = $status;
        $session->subject_code = $validated['subject_code'] ?? null;
        $session->schedule_id = $validated['schedule_id'] ?? null;
        $session->opened_by_user_id = $validated['opened_by_user_id'] ?? null;
        $session->is_listening = $validated['is_listening'] ?? true;
        $session->meta = $validated['meta'] ?? $session->meta;

        if ($session->is_listening && !$session->listening_started_at) {
            $session->listening_started_at = now();
        }

        if (!$session->is_listening) {
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
            if (!$attendanceSession) {
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
            $updateData = [
                'status' => $status,
                'updated_at' => now(),
            ];

            if (in_array($status, ['online', 'offline'], true) && !$attendanceSession->time_end) {
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
        ]);

        $rfid = strtolower(trim($validated['rfid']));
        $student = Students::query()
            ->with(['strand', 'section'])
            ->whereRaw('LOWER(rfid_tag) = ?', [$rfid])
            ->first();

        if (!$student) {
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

        if (!$attendanceSession) {
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

        if (!$currentSchedule) {
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
        if ($currentSubject && !is_null($currentSubject->year_level) && (int) $student->year_level !== (int) $currentSubject->year_level) {
            return response()->json([
                'ok' => false,
                'message' => 'Student year level does not match this class.',
            ], 422);
        }

        $latestOpenAttendance = Attendance::query()
            ->where('student_id', $student->student_id)
            ->whereDate('date', $today)
            ->where('room', $validated['room'])
            ->where('subject_code', $subjectCode)
            ->whereNull('time_out')
            ->latest('attendance_id')
            ->first();

        $latestLog = DB::table('attendance_logs')
            ->where('attendance_id', $attendanceSession->attendance_id)
            ->where('student_id', $student->student_id)
            ->orderByDesc('id')
            ->first();

        if ($latestLog && !$latestLog->time_out) {
            DB::table('attendance_logs')
                ->where('id', $latestLog->id)
                ->update([
                    'time_out' => $nowTime,
                    'status' => 'completed',
                    'updated_at' => now(),
                ]);

            if ($latestOpenAttendance) {
                $latestOpenAttendance->update([
                    'time_out' => $nowTime,
                ]);
            }

            return response()->json([
                'ok' => true,
                'action' => 'time_out',
                'record' => [
                    'id' => $latestLog->id,
                    'rfid' => $student->rfid_tag,
                    'name' => trim($student->first_name . ' ' . $student->last_name),
                    'course' => $student->strand?->strand_code,
                    'section' => $student->year_level . ' - ' . ($student->section?->section_name ?? ''),
                    'time_in' => $latestLog->time_in ? date('g:i A', strtotime((string) $latestLog->time_in)) : null,
                    'time_out' => date('g:i A', strtotime($nowTime)),
                    'status' => 'Completed',
                ],
            ]);
        }

        $attendance = Attendance::query()->create([
            'student_id' => $student->student_id,
            'schedule_id' => $scheduleId,
            'date' => $today,
            'time_in' => $nowTime,
            'status' => 'present',
            'subject_code' => $subjectCode,
            'room' => $validated['room'],
        ]);

        $attendanceLogId = DB::table('attendance_logs')->insertGetId([
            'attendance_id' => $attendanceSession->attendance_id,
            'student_id' => $student->student_id,
            'time_in' => $nowTime,
            'time_out' => null,
            'status' => 'present',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'action' => 'time_in',
            'record' => [
                'id' => $attendanceLogId,
                'rfid' => $student->rfid_tag,
                'name' => trim($student->first_name . ' ' . $student->last_name),
                'course' => $student->strand?->strand_code,
                'section' => $student->year_level . ' - ' . ($student->section?->section_name ?? ''),
                'time_in' => date('g:i A', strtotime((string) $attendance->time_in)),
                'time_out' => null,
                'status' => 'Present',
            ],
        ]);
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

        if (!$attendanceSession) {
            return response()->json([
                'ok' => true,
                'records' => [],
            ]);
        }

        $records = DB::table('attendance_logs')
            ->join('students', 'students.student_id', '=', 'attendance_logs.student_id')
            ->leftJoin('strands', 'strands.strand_id', '=', 'students.strand_id')
            ->leftJoin('sections', 'sections.section_id', '=', 'students.section_id')
            ->where('attendance_logs.attendance_id', $attendanceSession->attendance_id)
            ->orderByDesc('attendance_logs.time_in')
            ->select([
                'attendance_logs.id',
                'attendance_logs.time_in',
                'attendance_logs.time_out',
                'attendance_logs.status',
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
                    'name' => trim(($record->first_name ?? '') . ' ' . ($record->last_name ?? '')),
                    'course' => $record->strand_code,
                    'section' => trim(($record->year_level ? $record->year_level . ' - ' : '') . ($record->section_name ?? '')),
                    'time_in' => $record->time_in ? date('g:i A', strtotime((string) $record->time_in)) : null,
                    'time_out' => $record->time_out ? date('g:i A', strtotime((string) $record->time_out)) : null,
                    'status' => ucfirst((string) ($record->status ?? 'present')),
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
                        $scheduleLabel = trim(($schedule->weekdays ?? 'Scheduled') . ' ' . $formattedStart . ' - ' . $formattedEnd);
                    }
                } else {
                    $matchingByRoomDay = Schedule::query()
                        ->whereRaw('LOWER(TRIM(room)) = ?', [$normalizedRoom])
                        ->get(['weekdays'])
                        ->filter(fn($s) => $this->matchesWeekday((string) ($s->weekdays ?? ''), $weekday, $weekdayFull))
                        ->count();

                    $matchingByInstructorDayTime = Schedule::query()
                        ->join('subjects', function ($join) use ($instructor) {
                            $join->on('subjects.subject_code', '=', 'schedules.subject_code')
                                ->on('subjects.section_id', '=', 'schedules.section_id')
                                ->where('subjects.user_id', '=', $instructor->user_id);
                        })
                        ->whereRaw('TIME(?) >= schedules.time_start AND TIME(?) < schedules.time_end', [$currentTime, $currentTime])
                        ->get(['schedules.weekdays'])
                        ->filter(fn($s) => $this->matchesWeekday((string) ($s->weekdays ?? ''), $weekday, $weekdayFull))
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
                        ? trim(($schedule->matched_year_level ? $schedule->matched_year_level . ' - ' : '') . $schedule->matched_section_name)
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
                    'year' => $student->year_level . ' Year',
                    'course' => $student->strand?->strand_code ?? $student->section?->strand?->strand_code,
                    'strand' => $student->strand?->strand_code ?? $student->section?->strand?->strand_code,
                    'section' => $student->section?->section_name,
                    'avatarSeed' => $fullName,
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
                    'studentId' => 'USR-' . $user->user_id,
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

    public function controlPanel()
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
            ->map(fn($room) => trim((string) $room))
            ->filter(fn(string $room) => $room !== '')
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

        return Inertia::render('AttendanceControlPanel', [
            'rooms' => $rooms,
            'demoInstructorRfids' => User::query()
                ->whereRaw('LOWER(role) = ?', ['instructor'])
                ->whereNotNull('rfid_tag')
                ->where('rfid_tag', '!=', '')
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
                        'id' => $device->sku ?? ('ITEM-' . $device->item_id),
                        'type' => $device->description ?? 'Device',
                        'barcode' => (string) $device->barcode,
                        'status' => $device->status,
                    ];
                })
                ->values()
                ->all(),
            'borrowItemsByRfid' => $this->buildBorrowItemsByRfid(),
            'studentToastSeconds' => config('panel.student_toast_seconds', 15),
            'studentInfoVisibleSeconds' => config('panel.student_info_visible_seconds', 10),
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

            if (!isset($map[$rfidKey])) {
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
                if (!$borrowedItem || empty($borrowedItem->barcode)) {
                    continue;
                }

                $barcode = (string) $borrowedItem->barcode;
                $map[$rfidKey]['items'][$barcode] = [
                    'name' => $borrowedItem->name,
                    'id' => $borrowedItem->sku ?? ('ITEM-' . $borrowedItem->item_id),
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
        $currentSchedule = Schedule::query()
            ->with(['subject.user', 'section.strand'])
            ->orderByDesc('scheduled_id')
            ->first();

        $recentScans = AttendanceLog::query()
            ->with(['student.section.strand', 'student.strand', 'attendance.subjectRecord.user'])
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->map(function (AttendanceLog $log) {
                $student = $log->student;
                $attendance = $log->attendance;
                $subject = $attendance?->subjectRecord;

                return [
                    'id' => $log->id,
                    'student' => trim(($student?->first_name ?? '') . ' ' . ($student?->last_name ?? '')),
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
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function (Students $student) {
                return [
                    'rfid' => (string) $student->rfid_tag,
                    'name' => trim($student->first_name . ' ' . $student->last_name),
                    'studentId' => $student->student_number,
                    'section' => $student->section?->section_name,
                    'strand' => $student->strand?->strand_code,
                ];
            })
            ->values();

        return Inertia::render('AttendanceScanner', [
            'session' => $currentSchedule ? [
                'instructor' => $currentSchedule->subject?->user?->name ?? 'Unassigned Instructor',
                'subject' => $currentSchedule->subject?->subject_name ?? 'Unassigned Subject',
                'subjectCode' => $currentSchedule->subject?->subject_code,
                'section' => $currentSchedule->section?->section_name ?? 'Unassigned Section',
                'strand' => $currentSchedule->section?->strand?->strand_code,
                'room' => $currentSchedule->room,
                'weekdays' => $currentSchedule->weekdays,
                'time' => trim(($this->formatTime($currentSchedule->time_start) ?? 'N/A') . ' - ' . ($this->formatTime($currentSchedule->time_end) ?? 'N/A')),
            ] : null,
            'recentScans' => $recentScans,
            'registeredStudents' => $registeredStudents,
        ]);
    }

    public function scanner()
    {
        return $this->index();
    }

    public function logs(Request $request)
    {
        $filters = [
            'date' => trim((string) $request->input('date', '')),
            'subject' => trim((string) $request->input('subject', '')),
            'section' => trim((string) $request->input('section', '')),
            'instructor' => trim((string) $request->input('instructor', '')),
        ];

        $query = AttendanceLog::query()->with([
            'student.section.strand',
            'student.strand',
            'attendance.subjectRecord.user',
        ]);

        if ($filters['date'] !== '') {
            $query->whereHas('attendance', function ($attendanceQuery) use ($filters) {
                $attendanceQuery->whereDate('date', $filters['date']);
            });
        }

        if ($filters['subject'] !== '') {
            $query->whereHas('attendance.subjectRecord', function ($subjectQuery) use ($filters) {
                $subjectQuery->where('subject_id', $filters['subject']);
            });
        }

        if ($filters['section'] !== '') {
            $query->whereHas('student', function ($studentQuery) use ($filters) {
                $studentQuery->where('section_id', $filters['section']);
            });
        }

        if ($filters['instructor'] !== '') {
            $query->whereHas('attendance.subjectRecord.user', function ($userQuery) use ($filters) {
                $userQuery->where('user_id', $filters['instructor']);
            });
        }

        $logs = $query
            ->orderByDesc('id')
            ->get()
            ->map(function (AttendanceLog $log) {
                $student = $log->student;
                $attendance = $log->attendance;
                $subject = $attendance?->subjectRecord;

                return [
                    'id' => $log->id,
                    'student' => trim(($student?->first_name ?? '') . ' ' . ($student?->last_name ?? '')),
                    'subject' => $subject?->subject_name ?? $attendance?->subject ?? 'N/A',
                    'section' => $student?->section?->section_name ?? 'N/A',
                    'instructor' => $subject?->user?->name ?? 'Unassigned Instructor',
                    'date' => $attendance?->date?->format('Y-m-d') ?? null,
                    'time' => $this->formatTime($log->time_in) ?? $this->formatTime($attendance?->time_in) ?? 'N/A',
                    'status' => ucfirst((string) ($log->status ?? $attendance?->status ?? 'pending')),
                ];
            })
            ->values();

        return Inertia::render('AttendanceLogs', [
            'logs' => $logs,
            'filters' => $filters,
            'subjectOptions' => Subject::query()
                ->orderBy('subject_name')
                ->get(['subject_id', 'subject_name'])
                ->map(fn(Subject $subject) => [
                    'value' => $subject->subject_id,
                    'label' => $subject->subject_name,
                ])
                ->values(),
            'sectionOptions' => Section::query()
                ->orderBy('section_name')
                ->get(['section_id', 'section_name'])
                ->map(fn(Section $section) => [
                    'value' => $section->section_id,
                    'label' => $section->section_name,
                ])
                ->values(),
            'instructorOptions' => User::query()
                ->whereIn('user_id', Subject::query()->whereNotNull('user_id')->pluck('user_id')->unique())
                ->orderBy('name')
                ->get(['user_id', 'name'])
                ->map(fn(User $user) => [
                    'value' => $user->user_id,
                    'label' => $user->name,
                ])
                ->values(),
        ]);
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

        if (!$schedule || !$schedule->subject) {
            return response()->json([
                'success' => false,
                'message' => 'No active subject schedule is available.',
            ], 422);
        }

        $student = Students::query()
            ->with(['section.strand', 'strand'])
            ->where('rfid_tag', $validated['rfid_tag'])
            ->first();

        if (!$student) {
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
        $message = 'Attendance recorded for ' . trim($student->first_name . ' ' . $student->last_name) . '.';

        if (!$attendance->exists) {
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
                'student_id' => $student->student_id,
                'time_in' => $now->format('H:i:s'),
                'status' => $status,
            ]);
        } elseif (!$attendance->time_out) {
            $attendance->update([
                'time_out' => $now->format('H:i:s'),
            ]);

            $latestLog = AttendanceLog::query()
                ->where('attendance_id', $attendance->attendance_id)
                ->where('student_id', $student->student_id)
                ->latest('id')
                ->first();

            if ($latestLog && !$latestLog->time_out) {
                $latestLog->update([
                    'time_out' => $now->format('H:i:s'),
                ]);
            } else {
                AttendanceLog::create([
                    'attendance_id' => $attendance->attendance_id,
                    'student_id' => $student->student_id,
                    'time_out' => $now->format('H:i:s'),
                    'status' => $attendance->status,
                ]);
            }

            $action = 'time_out';
            $message = 'Time out recorded for ' . trim($student->first_name . ' ' . $student->last_name) . '.';
        } else {
            $action = 'repeat_scan';
            $message = 'Attendance for this student is already completed today.';
        }

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'attendance_' . $action,
            'table_name' => 'attendance_logs',
            'description' => $message,
        ]);

        return response()->json([
            'success' => true,
            'message' => $message,
            'entry' => [
                'id' => $attendance->attendance_id . '-' . $now->timestamp,
                'student' => trim($student->first_name . ' ' . $student->last_name),
                'studentNumber' => $student->student_number,
                'subject' => $schedule->subject->subject_name,
                'section' => $student->section?->section_name,
                'time' => $now->format('g:i A'),
                'status' => ucfirst((string) $attendance->status),
            ],
        ]);
    }

    private function formatTime(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? $value : date('g:i A', $timestamp);
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
