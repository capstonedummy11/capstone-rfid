<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id('student_id');
            $table->foreignId('section_id')->constrained('sections', 'section_id');
            $table->foreignId('course_id')->constrained('courses', 'course_id');
            $table->string('student_number')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name')->nullable();
            $table->enum('gender', ['male', 'female']);
            $table->string('email')->unique()->nullable();
            $table->string('phone')->nullable();
            $table->integer('year_level');
            $table->string('semester');                 
            $table->string('school_year');              
            $table->string('rfid_tag')->unique();      
            $table->enum('status', ['active', 'inactive', 'graduated', 'dropped'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
