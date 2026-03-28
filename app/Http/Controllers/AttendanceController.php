<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Borrowing;
use App\Models\Device;
use App\Models\RfidPanelSession;
use App\Models\Schedule;
use App\Models\Students;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            ->with(['course', 'section'])
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

        if (!$currentSubject) {
            return response()->json([
                'ok' => false,
                'message' => 'Current subject session could not be resolved.',
            ], 422);
        }

        if ((int) $student->section_id !== (int) $currentSubject->section_id || (int) $student->year_level !== (int) $currentSubject->year_level) {
            return response()->json([
                'ok' => false,
                'message' => 'Student are not in class.',
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
                    'course' => $student->course?->course_code,
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
                'course' => $student->course?->course_code,
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
            ->leftJoin('courses', 'courses.course_id', '=', 'students.course_id')
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
                'courses.course_code',
                'sections.section_name',
            ])
            ->get()
            ->map(function ($record) {
                return [
                    'id' => $record->id,
                    'rfid' => $record->rfid_tag,
                    'name' => trim(($record->first_name ?? '') . ' ' . ($record->last_name ?? '')),
                    'course' => $record->course_code,
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
            // Validate schedule by room/time/day and instructor ownership in one query.
            $now = now();
            $currentTime = $now->format('H:i:s');
            $weekday = $now->format('D');
            $normalizedRoom = strtolower(trim($room));

            $schedule = null;
            $hasValidSchedule = false;
            $scheduleLabel = 'No schedule assigned';
            $scheduleTimeStart = null;
            $scheduleTimeEnd = null;
            $scheduleWeekdays = null;

            if ($room) {
                $schedule = Schedule::query()
                    ->join('subjects', function ($join) use ($instructor) {
                        $join->on('subjects.subject_code', '=', 'schedules.subject_code')
                            ->on('subjects.section_id', '=', 'schedules.section_id')
                            ->where('subjects.user_id', '=', $instructor->user_id);
                    })
                    ->leftJoin('sections', 'sections.section_id', '=', 'schedules.section_id')
                    ->leftJoin('courses', 'courses.course_id', '=', 'sections.course_id')
                    ->whereRaw('LOWER(TRIM(schedules.room)) = ?', [$normalizedRoom])
                    ->whereRaw('TIME(?) >= schedules.time_start AND TIME(?) < schedules.time_end', [$currentTime, $currentTime])
                    ->whereRaw('FIND_IN_SET(?, REPLACE(schedules.weekdays, " ", "")) > 0', [$weekday])
                    ->select([
                        'schedules.*',
                        'subjects.subject_name as matched_subject_name',
                        'subjects.subject_code as matched_subject_code',
                        'subjects.section_id as matched_section_id',
                        'subjects.year_level as matched_subject_year_level',
                        'sections.section_name as matched_section_name',
                        'sections.year_level as matched_year_level',
                        'courses.course_code as matched_course_code',
                        'courses.course_name as matched_course_name',
                    ])
                    ->first();

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
                        ->whereRaw('FIND_IN_SET(?, REPLACE(weekdays, " ", "")) > 0', [$weekday])
                        ->count();

                    $matchingByInstructorDayTime = Schedule::query()
                        ->join('subjects', function ($join) use ($instructor) {
                            $join->on('subjects.subject_code', '=', 'schedules.subject_code')
                                ->on('subjects.section_id', '=', 'schedules.section_id')
                                ->where('subjects.user_id', '=', $instructor->user_id);
                        })
                        ->whereRaw('FIND_IN_SET(?, REPLACE(schedules.weekdays, " ", "")) > 0', [$weekday])
                        ->whereRaw('TIME(?) >= schedules.time_start AND TIME(?) < schedules.time_end', [$currentTime, $currentTime])
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
                    'course' => $schedule?->matched_course_code ?? $schedule?->matched_course_name ?? 'Unassigned Course',
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
            $student->loadMissing(['course', 'section']);

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
                    'course' => $student->course?->course_code,
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
            ->filter(fn(array $room) => $room['status'] === 'offline')
            ->values()
            ->all();

        return Inertia::render('AttendanceControlPanel', [
            'rooms' => $rooms,
            'demoStudentRfids' => Students::query()
                ->whereNotNull('rfid_tag')
                ->where('rfid_tag', '!=', '')
                ->where('status', 'active')
                ->orderBy('student_number')
                ->pluck('rfid_tag')
                ->values()
                ->all(),
            'borrowItemsCatalog' => Device::query()
                ->whereNotNull('barcode')
                ->select(['item_name', 'item_code', 'item_type', 'barcode'])
                ->orderBy('item_code')
                ->get()
                ->map(function ($device) {
                    return [
                        'name' => $device->item_name,
                        'id' => $device->item_code,
                        'type' => $device->item_type,
                        'barcode' => (string) $device->barcode,
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
                $map[$rfidKey] = [];
            }

            foreach ($borrowing->items as $item) {
                $borrowedItem = $item->item;
                if (!$borrowedItem || empty($borrowedItem->barcode)) {
                    continue;
                }

                $map[$rfidKey][] = [
                    'name' => $borrowedItem->item_name,
                    'id' => $borrowedItem->item_code,
                    'type' => $borrowedItem->item_type,
                    'barcode' => (string) $borrowedItem->barcode,
                ];
            }
        }

        foreach ($map as $key => $items) {
            $map[$key] = collect($items)
                ->unique('barcode')
                ->values()
                ->all();
        }

        return $map;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Attendance $attendance)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Attendance $attendance)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Attendance $attendance)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Attendance $attendance)
    {
        //
    }
}
