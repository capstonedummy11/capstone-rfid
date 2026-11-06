<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->softDeletes();
            $table->index('deleted_at', 'schedules_deleted_at_index');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->softDeletes();
            $table->index('deleted_at', 'attendances_deleted_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('attendances_deleted_at_index');
            $table->dropSoftDeletes();
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex('schedules_deleted_at_index');
            $table->dropSoftDeletes();
        });
    }
};
