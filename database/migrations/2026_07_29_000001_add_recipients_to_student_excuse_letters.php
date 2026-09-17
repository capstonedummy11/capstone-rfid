<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_excuse_letters', function (Blueprint $table) {
            if (! Schema::hasColumn('student_excuse_letters', 'recipient_user_ids')) {
                $table->json('recipient_user_ids')->nullable()->after('parent_approved_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_excuse_letters', function (Blueprint $table) {
            if (Schema::hasColumn('student_excuse_letters', 'recipient_user_ids')) {
                $table->dropColumn('recipient_user_ids');
            }
        });
    }
};
