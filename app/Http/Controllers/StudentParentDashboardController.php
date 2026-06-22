<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\Students;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StudentParentDashboardController
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $role = strtolower(trim((string) $user?->role));
        $students = $role === 'parent'
            ? $user->parentStudents()->with(['section', 'strand'])->get()
            : $this->studentForUser($user);

        $studentIds = $students->pluck('student_id')->filter()->values();
        $logs = AttendanceLog::query()
            ->with(['student', 'attendance.schedule.subject', 'attendance.schedule.laboratory'])
            ->whereIn('student_id', $studentIds)
            ->latest('id')
            ->get();

        return Inertia::render('StudentParent/Dashboard', [
            'title' => $role === 'parent' ? 'Parent Dashboard' : 'Student Dashboard',
            'role' => $role,
            'students' => $students->map(fn (Students $student) => [
                'id' => $student->student_id,
                'name' => trim($student->first_name . ' ' . $student->last_name),
                'student_number' => $student->student_number,
                'section' => $student->section?->section_name,
                'strand' => $student->strand?->strand_code,
                'year_level' => $student->year_level,
                'status' => $student->status,
                'stats' => $this->attendanceStats($logs->where('student_id', $student->student_id)),
            ])->values(),
            'stats' => $this->attendanceStats($logs),
            'recentLogs' => $logs
                ->take(10)
                ->map(fn (AttendanceLog $log) => [
                    'id' => $log->id,
                    'student_id' => $log->student_id,
                    'student_name' => trim(($log->student?->first_name ?? '') . ' ' . ($log->student?->last_name ?? '')),
                    'subject' => $log->attendance?->subject_code
                        ?: $log->attendance?->schedule?->subject?->subject_code
                        ?: 'Attendance',
                    'room' => $log->attendance?->room ?: $log->attendance?->schedule?->laboratory?->laboratory_name,
                    'date' => $log->attendance?->date?->format('M d, Y') ?: 'No date',
                    'time_in' => $log->time_in,
                    'time_out' => $log->time_out,
                    'status' => $this->normalizeStatus($log->status),
                ])
                ->values(),
        ]);
    }

    private function studentForUser($user)
    {
        if (! $user) {
            return collect();
        }

        $student = Students::query()
            ->with(['section', 'strand'])
            ->where('user_id', $user->user_id)
            ->orWhere('email', $user->email)
            ->first();

        return $student ? collect([$student]) : collect();
    }

    private function attendanceStats($logs): array
    {
        $normalized = $logs->map(fn (AttendanceLog $log) => $this->normalizeStatus($log->status));

        return [
            'total' => $normalized->count(),
            'present' => $normalized->filter(fn (string $status) => $status === 'present')->count(),
            'late' => $normalized->filter(fn (string $status) => $status === 'late')->count(),
            'absent' => $normalized->filter(fn (string $status) => $status === 'absent')->count(),
            'completed' => $normalized->filter(fn (string $status) => $status === 'completed')->count(),
        ];
    }

    private function normalizeStatus(?string $status): string
    {
        $value = strtolower(trim((string) $status));

        if (str_contains($value, 'absent')) {
            return 'absent';
        }

        if (str_contains($value, 'late')) {
            return 'late';
        }

        if (str_contains($value, 'complete') || str_contains($value, 'out')) {
            return 'completed';
        }

        return $value !== '' ? $value : 'present';
    }
}
