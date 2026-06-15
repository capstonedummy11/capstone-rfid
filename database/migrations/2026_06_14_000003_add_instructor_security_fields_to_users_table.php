<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'face_images')) {
                $table->json('face_images')->nullable()->after('rfid_tag');
            }

            if (! Schema::hasColumn('users', 'security_question')) {
                $table->string('security_question')->nullable()->after('face_images');
            }

            if (! Schema::hasColumn('users', 'security_answer_hash')) {
                $table->string('security_answer_hash')->nullable()->after('security_question');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['security_answer_hash', 'security_question', 'face_images'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
