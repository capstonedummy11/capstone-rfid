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
        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_photo_path')->nullable()->after('gender');
        });
    }

    // @function down: Ibinabalik ang schema changes ng migration na ito.
    // @useIn down: Laravel migration runner
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('profile_photo_path');
        });
    }
};
