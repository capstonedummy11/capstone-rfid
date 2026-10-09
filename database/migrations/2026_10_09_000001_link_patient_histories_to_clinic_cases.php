<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_histories', function (Blueprint $table) {
            $table->foreignId('clinic_case_id')->nullable()->unique()
                ->constrained('clinic_cases', 'clinic_case_id')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('patient_histories', function (Blueprint $table) {
            $table->dropForeign(['clinic_case_id']);
            $table->dropUnique(['clinic_case_id']);
            $table->dropColumn('clinic_case_id');
        });
    }
};
