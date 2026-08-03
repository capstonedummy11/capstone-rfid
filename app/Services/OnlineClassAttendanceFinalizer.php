<?php

namespace App\Services;

use App\Models\OnlineClass;
use App\Models\Students;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OnlineClassAttendanceFinalizer
{
    public function finalizeEnded(): int
    {
        $created = 0;

        OnlineClass::query()
            ->where('status', '!=', 'cancelled')
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

    public function finalize(OnlineClass $onlineClass): int
    {
        if ($onlineClass->status === 'cancelled' || ! $this->hasEnded($onlineClass)) {
            return 0;
        }

        $existingStudentIds = DB::table('online_class_attendances')
            ->where('online_class_id', $onlineClass->online_class_id)
            ->pluck('student_id');

        $now = now();
        $rows = Students::query()
            ->where('section_id', $onlineClass->section_id)
            ->where(function ($query) {
                $query->whereNull('status')->orWhereRaw('LOWER(status) = ?', ['active']);
            })
            ->whereNotIn('student_id', $existingStudentIds)
            ->get(['student_id'])
            ->map(fn (Students $student) => [
                'online_class_id' => $onlineClass->online_class_id,
                'student_id' => $student->student_id,
                'joined_at' => null,
                'status' => 'absent',
                'is_late' => false,
                'face_required' => (bool) $onlineClass->require_face_recognition,
                'face_verified' => null,
                'face_verified_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        if ($rows->isEmpty()) {
            return 0;
        }

        return DB::table('online_class_attendances')->insertOrIgnore($rows->all());
    }

    public function hasEnded(OnlineClass $onlineClass): bool
    {
        return Carbon::parse($onlineClass->scheduled_date->format('Y-m-d').' '.$onlineClass->end_time)->isPast();
    }
}
