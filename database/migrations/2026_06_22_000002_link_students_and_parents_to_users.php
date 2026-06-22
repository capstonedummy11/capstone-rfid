<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'user_id')) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('student_id')
                    ->unique()
                    ->constrained('users', 'user_id')
                    ->nullOnDelete();
            }
        });

        Schema::create('parent_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_user_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students', 'student_id')->cascadeOnDelete();
            $table->string('relationship')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['parent_user_id', 'student_id']);
            $table->index(['student_id', 'parent_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_student');

        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropUnique(['user_id']);
                $table->dropColumn('user_id');
            }
        });
    }
};
