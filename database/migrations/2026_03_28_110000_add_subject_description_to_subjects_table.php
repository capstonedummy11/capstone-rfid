<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subjects') && ! Schema::hasColumn('subjects', 'subject_description')) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->text('subject_description')->nullable()->after('subject_code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('subjects') && Schema::hasColumn('subjects', 'subject_description')) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->dropColumn('subject_description');
            });
        }
    }
};
