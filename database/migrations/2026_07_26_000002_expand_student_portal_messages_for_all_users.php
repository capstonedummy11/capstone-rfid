<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_portal_messages', function (Blueprint $table) {
            if (Schema::hasColumn('student_portal_messages', 'student_id')) {
                $table->dropForeign(['student_id']);
                $table->unsignedBigInteger('student_id')->nullable()->change();
                $table->foreign('student_id')->references('student_id')->on('students')->cascadeOnDelete();
            }

            if (! Schema::hasColumn('student_portal_messages', 'attachment_mime')) {
                $table->string('attachment_mime')->nullable()->after('attachment_name');
            }

            if (! Schema::hasColumn('student_portal_messages', 'attachment_size')) {
                $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_mime');
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_portal_messages', function (Blueprint $table) {
            foreach (['attachment_size', 'attachment_mime'] as $column) {
                if (Schema::hasColumn('student_portal_messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
