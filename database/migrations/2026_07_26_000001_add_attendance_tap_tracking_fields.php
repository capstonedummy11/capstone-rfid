<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendances')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE attendances MODIFY status VARCHAR(50) NOT NULL DEFAULT 'pending'");
            }

            Schema::table('attendances', function (Blueprint $table) {
                if (! Schema::hasColumn('attendances', 'check_in_status')) {
                    $table->string('check_in_status')->nullable()->after('time_out');
                }

                if (! Schema::hasColumn('attendances', 'room_status')) {
                    $table->string('room_status')->default('outside')->after('check_in_status');
                }

                if (! Schema::hasColumn('attendances', 'total_taps')) {
                    $table->unsignedInteger('total_taps')->default(0)->after('room_status');
                }

                if (! Schema::hasColumn('attendances', 'remarks')) {
                    $table->text('remarks')->nullable()->after('total_taps');
                }
            });
        }

        if (Schema::hasTable('attendance_logs')) {
            Schema::table('attendance_logs', function (Blueprint $table) {
                if (! Schema::hasColumn('attendance_logs', 'main_attendance_id')) {
                    $table->foreignId('main_attendance_id')
                        ->nullable()
                        ->after('attendance_id')
                        ->constrained('attendances', 'attendance_id')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('attendance_logs', 'schedule_id')) {
                    $table->foreignId('schedule_id')
                        ->nullable()
                        ->after('student_id')
                        ->constrained('schedules', 'scheduled_id')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('attendance_logs', 'tap_datetime')) {
                    $table->dateTime('tap_datetime')->nullable()->after('status');
                }

                if (! Schema::hasColumn('attendance_logs', 'tap_type')) {
                    $table->string('tap_type')->nullable()->after('tap_datetime');
                }

                if (! Schema::hasColumn('attendance_logs', 'tap_sequence_number')) {
                    $table->unsignedInteger('tap_sequence_number')->default(1)->after('tap_type');
                }

                if (! Schema::hasColumn('attendance_logs', 'device_scanner_id')) {
                    $table->string('device_scanner_id')->nullable()->after('tap_sequence_number');
                }

                if (! Schema::hasColumn('attendance_logs', 'location')) {
                    $table->string('location')->nullable()->after('device_scanner_id');
                }

                if (! Schema::hasColumn('attendance_logs', 'validation_result')) {
                    $table->string('validation_result')->default('valid')->after('location');
                }

                if (! Schema::hasColumn('attendance_logs', 'remarks')) {
                    $table->text('remarks')->nullable()->after('validation_result');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('attendance_logs')) {
            Schema::table('attendance_logs', function (Blueprint $table) {
                foreach ([
                    'remarks',
                    'validation_result',
                    'location',
                    'device_scanner_id',
                    'tap_sequence_number',
                    'tap_type',
                    'tap_datetime',
                ] as $column) {
                    if (Schema::hasColumn('attendance_logs', $column)) {
                        $table->dropColumn($column);
                    }
                }

                if (Schema::hasColumn('attendance_logs', 'schedule_id')) {
                    $table->dropConstrainedForeignId('schedule_id');
                }

                if (Schema::hasColumn('attendance_logs', 'main_attendance_id')) {
                    $table->dropConstrainedForeignId('main_attendance_id');
                }
            });
        }

        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                foreach (['remarks', 'total_taps', 'room_status', 'check_in_status'] as $column) {
                    if (Schema::hasColumn('attendances', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
