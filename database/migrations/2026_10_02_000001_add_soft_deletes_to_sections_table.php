<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // @function up: Ginagawa o binabago ang database schema para sa migration na ito.
    // @useIn up: Laravel migration runner
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    // @function down: Ibinabalik ang schema changes ng migration na ito.
    // @useIn down: Laravel migration runner
    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
