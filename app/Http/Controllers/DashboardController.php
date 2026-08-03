<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Instructor;
use App\Models\Laboratory;
use App\Models\OnlineClass;
use App\Models\Schedule;
use App\Models\Students;
use App\Models\StudentPortalMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function admin(Request $request): Response
    {
        $user = $request->user();
        $role = strtolower((string) $user?->role);
        $isInstructor = $role === 'instructor';
        $instructorId = $isInstructor
            ? Instructor::query()->where('user_id', $user?->user_id)->value('instructor_id')
            : null;
        $academicYearId = $request->integer('academic_year_id') ?: AcademicYear::currentOrLatest()?->academic_year_id;

        $sectionIds = $this->scheduleQuery($isInstructor, $instructorId, $academicYearId)
            ->whereNotNull('section_id')
            ->distinct()
            ->pluck('section_id');

        $todayAttendanceQuery = Attendance::query()
            ->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->whereDate('date', now()->toDateString())
            ->when($isInstructor, function ($query) use ($instructorId) {
                $query->whereHas('schedule', fn ($scheduleQuery) => $scheduleQuery->where('instructor_id', $instructorId ?: 0));
            });

        $stats = [
            'schedules' => $this->scheduleQuery($isInstructor, $instructorId, $academicYearId)->count(),
            'sections' => $sectionIds->count(),
            'students' => $isInstructor
                ? Students::query()->whereHas('enrollments', fn ($query) => $query->when($academicYearId, fn ($year) => $year->where('academic_year_id', $academicYearId))->whereIn('section_id', $sectionIds))->count()
                : Students::query()->when($academicYearId, fn ($query) => $query->whereHas('enrollments', fn ($year) => $year->where('academic_year_id', $academicYearId)))->count(),
            'todayPresent' => (clone $todayAttendanceQuery)->where('status', 'present')->count(),
            'todayLate' => (clone $todayAttendanceQuery)->where('status', 'late')->count(),
            'todayAbsent' => (clone $todayAttendanceQuery)->where('status', 'absent')->count(),
            'onlineClasses' => OnlineClass::query()
                ->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId))
                ->when($isInstructor, fn ($query) => $query->where('instructor_id', $instructorId ?: 0))
                ->whereDate('scheduled_date', '>=', now()->toDateString())
                ->where('status', '!=', 'cancelled')
                ->count(),
            'unreadMessages' => $isInstructor
                ? StudentPortalMessage::query()
                    ->where('recipient_user_id', $user?->user_id)
                    ->whereNull('read_at')
                    ->count()
                : 0,
            'laboratories' => $isInstructor
                ? $this->scheduleQuery($isInstructor, $instructorId, $academicYearId)->whereNotNull('laboratory_id')->distinct()->count('laboratory_id')
                : Laboratory::query()->count(),
            'instructors' => $isInstructor ? 1 : User::query()->whereRaw('LOWER(role) = ?', ['instructor'])->count(),
        ];

        $schedules = Schedule::query()
            ->with(['laboratory', 'section', 'subject', 'instructor.user'])
            ->when($isInstructor, fn ($query) => $query->where('instructor_id', $instructorId ?: 0))
            ->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->orderBy('weekdays')
            ->orderBy('time_start')
            ->take(8)
            ->get()
            ->map(fn (Schedule $schedule) => [
                'id' => $schedule->scheduled_id,
                'subject' => $schedule->subject?->subject_name ?? $schedule->subject_code,
                'subject_code' => $schedule->subject_code,
                'section' => $schedule->section?->section_name ?? 'Unassigned section',
                'room' => $schedule->room ?: ($schedule->laboratory?->name ?? 'No room'),
                'weekday' => $schedule->weekdays ?: 'No day',
                'time' => trim(($schedule->time_start ?: '--').' - '.($schedule->time_end ?: '--')),
                'instructor' => $schedule->instructor?->user?->name ?? 'Unassigned instructor',
            ]);

        $attendance = Attendance::query()
            ->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->with(['student', 'schedule.subject', 'schedule.section'])
            ->when($isInstructor, function ($query) use ($instructorId) {
                $query->whereHas('schedule', fn ($scheduleQuery) => $scheduleQuery->where('instructor_id', $instructorId ?: 0));
            })
            ->latest('date')
            ->latest('attendance_id')
            ->take(8)
            ->get()
            ->map(fn (Attendance $attendance) => [
                'id' => $attendance->attendance_id,
                'student' => trim(implode(' ', array_filter([
                    $attendance->student?->first_name,
                    $attendance->student?->last_name,
                ]))) ?: 'Unknown student',
                'section' => $attendance->schedule?->section?->section_name ?? $attendance->student?->section?->section_name ?? '-',
                'subject' => $attendance->schedule?->subject?->subject_name ?? $attendance->subject_code ?? 'Subject',
                'date' => $attendance->date?->format('M d, Y') ?? '-',
                'time_in' => $attendance->time_in ?: '-',
                'time_out' => $attendance->time_out ?: '-',
                'status' => $attendance->status ?: 'present',
            ]);

        $onlineClasses = OnlineClass::query()
            ->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->with(['section', 'subject'])
            ->when($isInstructor, fn ($query) => $query->where('instructor_id', $instructorId ?: 0))
            ->whereDate('scheduled_date', '>=', now()->toDateString())
            ->where('status', '!=', 'cancelled')
            ->orderBy('scheduled_date')
            ->orderBy('start_time')
            ->take(5)
            ->get()
            ->map(fn (OnlineClass $onlineClass) => [
                'id' => $onlineClass->online_class_id,
                'title' => $onlineClass->title,
                'subject' => $onlineClass->subject?->subject_name ?? $onlineClass->subject_code,
                'section' => $onlineClass->section?->section_name ?? '-',
                'date' => $onlineClass->scheduled_date?->format('M d, Y') ?? '-',
                'time' => trim(($onlineClass->start_time ?: '--').' - '.($onlineClass->end_time ?: '--')),
                'status' => $onlineClass->status,
                'face_required' => (bool) $onlineClass->require_face_recognition,
            ]);

        return Inertia::render('Auth/Admin/Dashboard', [
            'title' => $isInstructor ? 'Instructor Dashboard' : 'Dashboard',
            'role' => $role,
            'scopeLabel' => ($isInstructor ? 'Your assigned classes' : 'Whole system').' · '.(AcademicYear::query()->find($academicYearId)?->name ?? 'All years'),
            'academicYears' => AcademicYear::query()->orderByDesc('starts_on')->get(['academic_year_id', 'name', 'status']),
            'selectedAcademicYearId' => $academicYearId,
            'instructorProfile' => $isInstructor ? Instructor::query()
                ->with(['strand'])
                ->where('instructor_id', $instructorId)
                ->first()?->only(['instructor_id', 'instructor_number', 'status']) : null,
            'stats' => $stats,
            'schedules' => $schedules,
            'attendance' => $attendance,
            'onlineClasses' => $onlineClasses,
        ]);
    }

    private function scheduleQuery(bool $isInstructor, ?int $instructorId, ?int $academicYearId = null): Builder
    {
        return Schedule::query()
            ->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->when($isInstructor, fn ($query) => $query->where('instructor_id', $instructorId ?: 0));
    }
}
