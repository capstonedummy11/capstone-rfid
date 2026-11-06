<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_classes', function (Blueprint $table) {
            $table->unsignedBigInteger('instructor_id')->nullable()->change();
        });

        $now = now();
        DB::table('subject_offerings')
            ->whereIn('academic_year_id', DB::table('academic_years')
                ->whereIn('status', ['draft', 'active'])
                ->select('academic_year_id'))
            ->orderBy('subject_offering_id')
            ->chunkById(100, function ($offerings) use ($now) {
                foreach ($offerings as $offering) {
                    $scheduleIds = DB::table('schedules')
                        ->where('subject_offering_id', $offering->subject_offering_id)
                        ->select('scheduled_id');

                    DB::table('online_classes')
                        ->whereIn('schedule_id', $scheduleIds)
                        ->where('status', 'scheduled')
                        ->whereNull('deleted_at')
                        ->where(function ($query) use ($now) {
                            $query->whereDate('scheduled_date', '>', $now->toDateString())
                                ->orWhere(function ($today) use ($now) {
                                    $today->whereDate('scheduled_date', $now->toDateString())
                                        ->where('end_time', '>', $now->format('H:i:s'));
                                });
                        })
                        ->update(['instructor_id' => $offering->instructor_id, 'updated_at' => $now]);

                    DB::table('schedules')
                        ->where('subject_offering_id', $offering->subject_offering_id)
                        ->update(['instructor_id' => $offering->instructor_id]);
                }
            }, 'subject_offering_id');
    }

    public function down(): void
    {
        if (DB::table('online_classes')->whereNull('instructor_id')->exists()) {
            throw new RuntimeException('Assign instructors to unassigned online classes before rolling back this migration.');
        }

        Schema::table('online_classes', function (Blueprint $table) {
            $table->unsignedBigInteger('instructor_id')->nullable(false)->change();
        });
    }
};
