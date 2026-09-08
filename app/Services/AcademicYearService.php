<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcademicYearService
{
    public function active(): ?AcademicYear
    {
        return AcademicYear::active();
    }

    public function activate(AcademicYear $academicYear, User $actor): AcademicYear
    {
        return DB::transaction(function () use ($academicYear, $actor) {
            $target = AcademicYear::query()->lockForUpdate()->findOrFail($academicYear->academic_year_id);

            if ($target->status === AcademicYear::STATUS_ARCHIVED) {
                throw ValidationException::withMessages([
                    'academic_year' => 'An archived academic year must be reopened before it can be activated.',
                ]);
            }

            AcademicYear::query()
                ->where('status', AcademicYear::STATUS_ACTIVE)
                ->whereKeyNot($target->academic_year_id)
                ->update([
                    'status' => AcademicYear::STATUS_CLOSED,
                    'closed_at' => now(),
                    'closed_by_user_id' => $actor->user_id,
                ]);

            $target->update([
                'status' => AcademicYear::STATUS_ACTIVE,
                'activated_at' => now(),
                'activated_by_user_id' => $actor->user_id,
                'closed_at' => null,
                'closed_by_user_id' => null,
            ]);

            return $target->fresh();
        });
    }

    public function close(AcademicYear $academicYear, User $actor): AcademicYear
    {
        if ($academicYear->status !== AcademicYear::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'academic_year' => 'Only the active academic year can be closed.',
            ]);
        }

        $academicYear->update([
            'status' => AcademicYear::STATUS_CLOSED,
            'closed_at' => now(),
            'closed_by_user_id' => $actor->user_id,
        ]);

        return $academicYear->fresh();
    }

    public function archive(AcademicYear $academicYear): AcademicYear
    {
        if ($academicYear->status !== AcademicYear::STATUS_CLOSED) {
            throw ValidationException::withMessages([
                'academic_year' => 'Only a closed academic year can be archived.',
            ]);
        }

        $academicYear->update(['status' => AcademicYear::STATUS_ARCHIVED]);

        return $academicYear->fresh();
    }

    public function reopen(AcademicYear $academicYear, User $actor, string $reason): AcademicYear
    {
        if (! in_array($academicYear->status, [AcademicYear::STATUS_CLOSED, AcademicYear::STATUS_ARCHIVED], true)) {
            throw ValidationException::withMessages([
                'academic_year' => 'Only a closed or archived academic year can be reopened.',
            ]);
        }

        $academicYear->update([
            'status' => AcademicYear::STATUS_DRAFT,
            'reopened_at' => now(),
            'reopened_by_user_id' => $actor->user_id,
            'reopen_reason' => $reason,
        ]);

        return $academicYear->fresh();
    }
}
