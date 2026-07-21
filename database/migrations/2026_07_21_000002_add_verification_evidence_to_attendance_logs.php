<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->string('verification_method')->nullable()->after('status');
            $table->string('time_in_face_path')->nullable()->after('verification_method');
            $table->string('time_out_face_path')->nullable()->after('time_in_face_path');
            $table->boolean('is_late')->default(false)->after('time_out_face_path');
            $table->string('completion_reason')->nullable()->after('is_late');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropColumn([
                'verification_method', 'time_in_face_path', 'time_out_face_path',
                'is_late', 'completion_reason',
            ]);
        });
    }
};
