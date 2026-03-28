<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            if (! Schema::hasColumn('schedules', 'laboratory_id')) {
                $table->foreignId('laboratory_id')
                    ->nullable()
                    ->after('scheduled_id')
                    ->constrained('laboratories', 'laboratory_id')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('schedules', 'instructor_id')) {
                $table->foreignId('instructor_id')
                    ->nullable()
                    ->after('laboratory_id')
                    ->constrained('instructors', 'instructor_id')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['laboratory_id']);
            $table->dropForeign(['instructor_id']);
            $table->dropColumn(['laboratory_id', 'instructor_id']);
        });
    }
};
