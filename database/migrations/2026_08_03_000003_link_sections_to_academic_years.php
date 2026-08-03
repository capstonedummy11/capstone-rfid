<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->foreignId('academic_year_id')
                ->nullable()
                ->after('strand_id')
                ->constrained('academic_years', 'academic_year_id')
                ->restrictOnDelete();
        });

        DB::table('sections')->orderBy('section_id')->each(function ($section) {
            $yearId = DB::table('academic_years')
                ->where('name', trim((string) $section->school_year))
                ->value('academic_year_id');

            if ($yearId) {
                DB::table('sections')->where('section_id', $section->section_id)->update([
                    'academic_year_id' => $yearId,
                ]);
            }
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->unique(
                ['academic_year_id', 'semester', 'section_name'],
                'sections_year_semester_name_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropUnique('sections_year_semester_name_unique');
            $table->dropConstrainedForeignId('academic_year_id');
        });
    }
};
