<?php
// FEATURE:online-class-management - konektadong model, service, route, o UI para sa feature na ito.
// FEATURE:online-class-join - konektadong model, service, route, o UI para sa feature na ito.

namespace App\Services;

use App\Models\OnlineClass;
use App\Models\Students;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OnlineClassAttendanceFinalizer
{
    // @function finalizeEnded: Kinukuha ang finalize ended result para sa Online Class Attendance Finalizer.
    // @useIn finalizeEnded: routes/console.php
    public function finalizeEnded(): int
    {
        $created = 0;

        OnlineClass::query()
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('instructor_id')
            ->whereDate('scheduled_date', '<=', now()->toDateString())
            ->orderBy('online_class_id')
            ->chunkById(100, function ($classes) use (&$created) {
                foreach ($classes as $onlineClass) {
                    if ($this->hasEnded($onlineClass)) {
                        $created += $this->finalize($onlineClass);
                    }
                }
            }, 'online_class_id');

        return $created;
    }

    // @function finalize: Kinukuha ang finalize result para sa Online Class Attendance Finalizer.
    // @useIn finalize: OnlineClassAttendanceFinalizer::finalizeEnded (app/Services/OnlineClassAttendanceFinalizer.php)
    public function finalize(OnlineClass $onlineClass): int
    {
        if ($onlineClass->status === 'cancelled' || ! $onlineClass->instructor_id || ! $this->hasEnded($onlineClass)) {
            return 0;
        }

        $existingStudentIds = DB::table('online_class_attendances')
            ->where('online_class_id', $onlineClass->online_class_id)
            ->pluck('student_id');

        $now = now();
        $rows = Students::query()
            ->when($onlineClass->academic_year_id,
                fn ($query) => $query->whereHas('enrollments', fn ($enrollment) => $enrollment
                    ->where('academic_year_id', $onlineClass->academic_year_id)->where('section_id', $onlineClass->section_id)->where('status', 'active')),
                fn ($query) => $query->where('section_id', $onlineClass->section_id))
            ->where(function ($query) {
                $query->whereNull('status')->orWhereRaw('LOWER(status) = ?', ['active']);
            })
            ->whereNotIn('student_id', $existingStudentIds)
            ->get(['student_id'])
            ->map(function (Students $student) use ($onlineClass, $now) {
                $enrollmentId = $onlineClass->academic_year_id ? $student->enrollments()->where('academic_year_id', $onlineClass->academic_year_id)->where('section_id', $onlineClass->section_id)->value('student_enrollment_id') : null;
                return [
                'online_class_id' => $onlineClass->online_class_id,
                'student_id' => $student->student_id,
                'academic_year_id' => $onlineClass->academic_year_id,
                'subject_offering_id' => $onlineClass->subject_offering_id,
                'student_enrollment_id' => $enrollmentId,
                'joined_at' => null,
                'status' => 'absent',
                'is_late' => false,
                'face_required' => (bool) $onlineClass->require_face_recognition,
                'face_verified' => null,
                'face_verified_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
                ];
            });

        if ($rows->isEmpty()) {
            return 0;
        }

        return DB::table('online_class_attendances')->insertOrIgnore($rows->all());
    }

    // @function hasEnded: Sinusuri kung ended para sa Online Class Attendance Finalizer.
    // @useIn hasEnded: OnlineClassAttendanceFinalizer::finalizeEnded (app/Services/OnlineClassAttendanceFinalizer.php)
    public function hasEnded(OnlineClass $onlineClass): bool
    {
        return Carbon::parse($onlineClass->scheduled_date->format('Y-m-d').' '.$onlineClass->end_time)->isPast();
    }
}
