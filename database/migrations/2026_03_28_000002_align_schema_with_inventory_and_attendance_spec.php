<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventories')) {
            Schema::create('inventories', function (Blueprint $table) {
                $table->id('inventory_id');
                $table->foreignId('item_id')
                    ->constrained('items', 'item_id')
                    ->cascadeOnDelete();
                $table->unsignedInteger('quantity')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('transactions')) {
            Schema::create('transactions', function (Blueprint $table) {
                $table->id('transaction_id');
                $table->foreignId('inventory_id')
                    ->constrained('inventories', 'inventory_id')
                    ->cascadeOnDelete();
                $table->foreignId('item_id')
                    ->constrained('items', 'item_id')
                    ->restrictOnDelete();
                $table->unsignedInteger('quantity')->default(0);
                $table->string('transaction_type');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->id('logs_id');
                $table->uuid('event_id')->nullable()->unique();
                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained('users', 'user_id')
                    ->nullOnDelete();
                $table->string('user_name')->nullable();
                $table->string('user_role')->nullable();
                $table->string('action');
                $table->string('table_name');
                $table->string('module')->nullable();
                $table->string('outcome', 20)->default('success');
                $table->string('severity', 20)->default('info');
                $table->string('subject_type')->nullable();
                $table->string('subject_id')->nullable();
                $table->string('route_name')->nullable();
                $table->string('http_method', 10)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->unsignedSmallInteger('status_code')->nullable();
                $table->text('description')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['created_at', 'module']);
                $table->index(['user_role', 'created_at']);
                $table->index(['outcome', 'severity']);
                $table->index(['subject_type', 'subject_id']);
                $table->index('ip_address');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('inventories');
    }
};
