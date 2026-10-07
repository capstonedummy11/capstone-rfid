<?php

// FEATURE:root-ownership - Dito ang transfer, override, at audit tables ng Root ownership.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // @function up: Ginagawa o binabago ang database schema para sa migration na ito.
    // @useIn up: Laravel migration runner
    public function up(): void
    {
        Schema::create('root_transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_user_id')->constrained('users', 'user_id')->restrictOnDelete();
            $table->foreignId('to_user_id')->constrained('users', 'user_id')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users', 'user_id')->restrictOnDelete();
            $table->string('status', 30)->default('pending')->index();
            $table->string('pending_guard', 30)->nullable()->unique();
            $table->string('cancel_token_hash', 64)->nullable()->unique();
            $table->dateTime('effective_at');
            $table->dateTime('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reminder_7_sent_at')->nullable();
            $table->timestamp('reminder_1_sent_at')->nullable();
            $table->string('request_ip', 45)->nullable();
            $table->text('request_user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('root_override_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users', 'user_id')->restrictOnDelete();
            $table->foreignId('from_user_id')->constrained('users', 'user_id')->restrictOnDelete();
            $table->foreignId('to_user_id')->constrained('users', 'user_id')->restrictOnDelete();
            $table->text('reason');
            $table->string('status', 30)->default('pending')->index();
            $table->string('pending_guard', 30)->nullable()->unique();
            $table->unsignedSmallInteger('required_approvals');
            $table->timestamp('execute_at')->nullable();
            $table->dateTime('expires_at');
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('request_ip', 45)->nullable();
            $table->text('request_user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('root_override_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('root_override_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approver_id')->constrained('users', 'user_id')->restrictOnDelete();
            $table->string('decision', 20);
            $table->text('comment')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->unique(['root_override_request_id', 'approver_id'], 'root_override_approver_unique');
        });

        Schema::create('root_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->foreignId('root_transfer_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('root_override_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60)->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    // @function down: Ibinabalik ang schema changes ng migration na ito.
    // @useIn down: Laravel migration runner
    public function down(): void
    {
        Schema::dropIfExists('root_audit_logs');
        Schema::dropIfExists('root_override_approvals');
        Schema::dropIfExists('root_override_requests');
        Schema::dropIfExists('root_transfer_requests');
    }
};
