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
        Schema::create('online_classes', function (Blueprint $table) {
            $table->id('online_class_id');
            $table->foreignId('schedule_id')->constrained('schedules', 'scheduled_id')->cascadeOnDelete();
            $table->foreignId('instructor_id')->constrained('instructors', 'instructor_id')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('sections', 'section_id')->cascadeOnDelete();
            $table->string('subject_code');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('meeting_link');
            $table->date('scheduled_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('require_face_recognition')->default(true);
            $table->string('status')->default('scheduled');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['instructor_id', 'scheduled_date']);
            $table->index(['section_id', 'scheduled_date']);
            $table->index('status');
        });

        Schema::create('online_class_attachments', function (Blueprint $table) {
            $table->id('online_class_attachment_id');
            $table->foreignId('online_class_id')->constrained('online_classes', 'online_class_id')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();
        });

        Schema::create('online_class_attendances', function (Blueprint $table) {
            $table->id('online_class_attendance_id');
            $table->foreignId('online_class_id')->constrained('online_classes', 'online_class_id')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students', 'student_id')->cascadeOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->string('status')->default('not_joined');
            $table->boolean('is_late')->default(false);
            $table->boolean('face_required')->default(false);
            $table->boolean('face_verified')->nullable();
            $table->timestamp('face_verified_at')->nullable();
            $table->timestamps();

            $table->unique(['online_class_id', 'student_id']);
        });

        Schema::create('online_class_notifications', function (Blueprint $table) {
            $table->id('online_class_notification_id');
            $table->foreignId('online_class_id')->constrained('online_classes', 'online_class_id')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students', 'student_id')->cascadeOnDelete();
            $table->string('event');
            $table->string('title');
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->text('email_error')->nullable();
            $table->timestamps();
        });

        Schema::create('online_class_audit_logs', function (Blueprint $table) {
            $table->id('online_class_audit_log_id');
            $table->foreignId('online_class_id')->nullable()->constrained('online_classes', 'online_class_id')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->string('user_role')->nullable();
            $table->string('action');
            $table->foreignId('section_id')->nullable()->constrained('sections', 'section_id')->nullOnDelete();
            $table->string('ip_address')->nullable();
            $table->json('previous_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'created_at']);
            $table->index(['section_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('online_class_audit_logs');
        Schema::dropIfExists('online_class_notifications');
        Schema::dropIfExists('online_class_attendances');
        Schema::dropIfExists('online_class_attachments');
        Schema::dropIfExists('online_classes');
    }
};
