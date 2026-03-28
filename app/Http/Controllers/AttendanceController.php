<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Students;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AttendanceController
{
    public function scanner()
    {
        $currentSchedule = Schedule::query()
            ->with(['subject.user', 'section.strand'])
            ->orderByDesc('timestamp')
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
                    'strand' => $student?->strand?->strand_code,
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
                ->map(fn (Subject $subject) => [
                    'value' => $subject->subject_id,
                    'label' => $subject->subject_name,
                ])
                ->values(),
            'sectionOptions' => Section::query()
                ->orderBy('section_name')
                ->get(['section_id', 'section_name'])
                ->map(fn (Section $section) => [
                    'value' => $section->section_id,
                    'label' => $section->section_name,
                ])
                ->values(),
            'instructorOptions' => User::query()
                ->whereIn('user_id', Subject::query()->whereNotNull('user_id')->pluck('user_id')->unique())
                ->orderBy('name')
                ->get(['user_id', 'name'])
                ->map(fn (User $user) => [
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
            ->orderByDesc('timestamp')
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
        $message = 'Attendance recorded for ' . trim($student->first_name . ' ' . $student->last_name) . '.';

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
                'student_id' => $student->student_id,
                'time_in' => $now->format('H:i:s'),
                'status' => $status,
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
        if (! $value) {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? $value : date('g:i A', $timestamp);
    }
}
