<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_types', function (Blueprint $table) {
            $table->id('emergency_type_id');
            $table->string('name');
            $table->string('category')->default('general');
            $table->text('default_message')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('emergency_alerts', function (Blueprint $table) {
            $table->id('emergency_alert_id');
            $table->foreignId('emergency_type_id')->nullable()->constrained('emergency_types', 'emergency_type_id')->nullOnDelete();
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained('schedules', 'scheduled_id')->nullOnDelete();
            $table->string('room')->nullable();
            $table->string('subject_code')->nullable();
            $table->string('triggered_by_name')->nullable();
            $table->string('severity')->default('urgent');
            $table->enum('status', ['open', 'acknowledged', 'resolved', 'cancelled'])->default('open');
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('clinic_cases', function (Blueprint $table) {
            $table->id('clinic_case_id');
            $table->foreignId('emergency_alert_id')->nullable()->constrained('emergency_alerts', 'emergency_alert_id')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students', 'student_id')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->foreignId('handled_by_user_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->string('patient_type')->nullable();
            $table->string('patient_name')->nullable();
            $table->string('case_type')->nullable();
            $table->text('symptoms')->nullable();
            $table->text('action_taken')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['open', 'monitoring', 'resolved', 'referred'])->default('open');
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
        });

        Schema::create('patient_histories', function (Blueprint $table) {
            $table->id('patient_history_id');
            $table->foreignId('student_id')->nullable()->constrained('students', 'student_id')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->string('patient_type')->nullable();
            $table->string('patient_name')->nullable();
            $table->string('summary');
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_histories');
        Schema::dropIfExists('clinic_cases');
        Schema::dropIfExists('emergency_alerts');
        Schema::dropIfExists('emergency_types');
    }
};
