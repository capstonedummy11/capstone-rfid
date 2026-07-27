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
        Schema::create('student_excuse_letters', function (Blueprint $table) {
            $table->id('student_excuse_letter_id');
            $table->foreignId('student_id')->constrained('students', 'student_id')->cascadeOnDelete();
            $table->foreignId('submitted_by_user_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->string('submitted_by_role');
            $table->string('subject');
            $table->date('from_date');
            $table->date('to_date');
            $table->text('reason');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('status')->default('submitted');
            $table->timestamps();
        });

        Schema::create('student_portal_messages', function (Blueprint $table) {
            $table->id('student_portal_message_id');
            $table->foreignId('student_id')->constrained('students', 'student_id')->cascadeOnDelete();
            $table->foreignId('sender_user_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->string('sender_role');
            $table->foreignId('instructor_user_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->string('subject');
            $table->text('body');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'sender_role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_portal_messages');
        Schema::dropIfExists('student_excuse_letters');
    }
};
