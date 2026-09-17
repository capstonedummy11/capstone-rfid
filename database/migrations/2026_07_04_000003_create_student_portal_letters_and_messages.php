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
            $table->unsignedBigInteger('academic_year_id')->nullable();
            $table->unsignedBigInteger('student_enrollment_id')->nullable();
            $table->foreignId('submitted_by_user_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->string('submitted_by_role');
            $table->string('subject');
            $table->date('from_date');
            $table->date('to_date');
            $table->text('reason');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('status')->default('submitted');
            $table->string('parent_signature')->nullable();
            $table->text('parent_approval_notes')->nullable();
            $table->foreignId('parent_approved_by_user_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamp('parent_approved_at')->nullable();
            $table->json('recipient_user_ids')->nullable();
            $table->timestamps();

            $table->index(['academic_year_id', 'student_id'], 'excuse_letter_year_student_index');
        });

        Schema::create('student_portal_messages', function (Blueprint $table) {
            $table->id('student_portal_message_id');
            $table->foreignId('student_id')->nullable()->constrained('students', 'student_id')->cascadeOnDelete();
            $table->foreignId('sender_user_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->string('sender_role');
            $table->foreignId('instructor_user_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->string('subject');
            $table->text('subject_ciphertext')->nullable();
            $table->text('body');
            $table->longText('body_ciphertext')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime')->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'sender_role']);
            $table->index(['recipient_user_id', 'created_at']);
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
