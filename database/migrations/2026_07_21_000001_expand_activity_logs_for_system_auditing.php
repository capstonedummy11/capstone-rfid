<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->uuid('event_id')->nullable()->unique()->after('logs_id');
            $table->string('user_name')->nullable()->after('user_id');
            $table->string('user_role')->nullable()->after('user_name');
            $table->string('module')->nullable()->after('table_name');
            $table->string('outcome', 20)->default('success')->after('module');
            $table->string('severity', 20)->default('info')->after('outcome');
            $table->string('subject_type')->nullable()->after('severity');
            $table->string('subject_id')->nullable()->after('subject_type');
            $table->string('route_name')->nullable()->after('subject_id');
            $table->string('http_method', 10)->nullable()->after('route_name');
            $table->string('ip_address', 45)->nullable()->after('http_method');
            $table->text('user_agent')->nullable()->after('ip_address');
            $table->unsignedSmallInteger('status_code')->nullable()->after('user_agent');

            $table->index(['created_at', 'module']);
            $table->index(['user_role', 'created_at']);
            $table->index(['outcome', 'severity']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at', 'module']);
            $table->dropIndex(['user_role', 'created_at']);
            $table->dropIndex(['outcome', 'severity']);
            $table->dropIndex(['subject_type', 'subject_id']);
            $table->dropIndex(['ip_address']);
            $table->dropUnique(['event_id']);
            $table->dropColumn([
                'event_id', 'user_name', 'user_role', 'module', 'outcome', 'severity',
                'subject_type', 'subject_id', 'route_name', 'http_method', 'ip_address',
                'user_agent', 'status_code',
            ]);
        });
    }
};
