<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // @function up: Ginagawa o binabago ang database schema para sa migration na ito.
    // @useIn up: Laravel migration runner
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('parent_student_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_user_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students', 'student_id')->cascadeOnDelete();
            $table->string('relationship')->default('parent');
            $table->timestamps();

            $table->unique(['parent_user_id', 'student_id']);
        });
    }

    // @function down: Ibinabalik ang schema changes ng migration na ito.
    // @useIn down: Laravel migration runner
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parent_student_links');
    }
};
