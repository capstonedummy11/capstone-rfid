<?php

namespace App\Services;

use App\Models\Attendance;
use Carbon\Carbon;

class PhysicalAttendanceStatus
{
    public function forSession(?Attendance $attendance, object $session): string
    {
        if (! $attendance) {
            return $this->sessionEnded($session) ? 'Absent' : 'Pending';
        }

        $status = trim((string) $attendance->status);
        if (strcasecmp($status, 'pending') === 0 && $this->sessionEnded($session)) {
            return empty($attendance->time_out)
                ? 'Incomplete Attendance'
                : ucfirst((string) ($attendance->check_in_status ?: 'present'));
        }

        return $status === '' ? 'Pending' : ucwords(str_replace('_', ' ', strtolower($status)));
    }

    public function sessionEnded(object $session): bool
    {
        return Carbon::parse($session->date.' '.$session->time_end)->isPast()
            || in_array(strtolower((string) ($session->status ?? '')), ['completed', 'ended', 'closed'], true);
    }
}
