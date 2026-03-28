<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('strands')) {
            Schema::create('strands', function (Blueprint $table) {
                $table->id('strand_id');
                $table->string('strand_code')->unique();
                $table->string('strand_name');
                $table->string('department');
                $table->string('status')->default('active');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('subjects')) {
            Schema::create('subjects', function (Blueprint $table) {
                $table->id('subject_id');
                $table->foreignId('section_id')
                    ->nullable()
                    ->constrained('sections', 'section_id')
                    ->nullOnDelete();
                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained('users', 'user_id')
                    ->nullOnDelete();
                $table->string('subject_name');
                $table->string('subject_code')->unique();
                $table->text('subject_description')->nullable();
                $table->string('department')->nullable();
                $table->unsignedInteger('unit')->default(0);
                $table->string('semester')->nullable();
                $table->timestamps();
            });
        }

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
                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained('users', 'user_id')
                    ->nullOnDelete();
                $table->string('action');
                $table->string('table_name');
                $table->text('description')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('schedules')) {
            Schema::create('schedules', function (Blueprint $table) {
                $table->id('scheduled_id');
                $table->foreignId('section_id')
                    ->constrained('sections', 'section_id')
                    ->cascadeOnDelete();
                $table->string('subject_code')->nullable();
                $table->string('weekdays');
                $table->time('time_start');
                $table->time('time_end');
                $table->string('room');
                $table->timestamp('timestamp')->nullable();

                $table->foreign('subject_code')
                    ->references('subject_code')
                    ->on('subjects')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'deleted_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('items')) {
            Schema::table('items', function (Blueprint $table) {
                if (! Schema::hasColumn('items', 'item_description')) {
                    $table->text('item_description')->nullable()->after('item_name');
                }

                if (! Schema::hasColumn('items', 'item_sku')) {
                    $table->string('item_sku')->nullable()->unique()->after('item_description');
                }

                if (! Schema::hasColumn('items', 'item_barcode')) {
                    $table->string('item_barcode')->nullable()->unique()->after('item_sku');
                }
            });
        }

        if (Schema::hasTable('sections') && ! Schema::hasColumn('sections', 'strand_id')) {
            Schema::table('sections', function (Blueprint $table) {
                $table->foreignId('strand_id')
                    ->nullable()
                    ->after('section_id')
                    ->constrained('strands', 'strand_id')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('students')) {
            Schema::table('students', function (Blueprint $table) {
                if (! Schema::hasColumn('students', 'strand_id')) {
                    $table->foreignId('strand_id')
                        ->nullable()
                        ->after('section_id')
                        ->constrained('strands', 'strand_id')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('students', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        if (Schema::hasTable('borrowings') && ! Schema::hasColumn('borrowings', 'due_date')) {
            Schema::table('borrowings', function (Blueprint $table) {
                $table->date('due_date')->nullable()->after('status');
            });
        }

        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                if (! Schema::hasColumn('attendances', 'subject_id')) {
                    $table->foreignId('subject_id')
                        ->nullable()
                        ->after('attendance_id')
                        ->constrained('subjects', 'subject_id')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('attendances', 'schedule_id')) {
                    $table->foreignId('schedule_id')
                        ->nullable()
                        ->after('subject_id')
                        ->constrained('schedules', 'scheduled_id')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('attendances', 'time_start')) {
                    $table->time('time_start')->nullable()->after('date');
                }

                if (! Schema::hasColumn('attendances', 'time_end')) {
                    $table->time('time_end')->nullable()->after('time_start');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                if (Schema::hasColumn('attendances', 'schedule_id')) {
                    $table->dropConstrainedForeignId('schedule_id');
                }

                if (Schema::hasColumn('attendances', 'subject_id')) {
                    $table->dropConstrainedForeignId('subject_id');
                }

                if (Schema::hasColumn('attendances', 'time_end')) {
                    $table->dropColumn('time_end');
                }

                if (Schema::hasColumn('attendances', 'time_start')) {
                    $table->dropColumn('time_start');
                }
            });
        }

        if (Schema::hasTable('borrowings') && Schema::hasColumn('borrowings', 'due_date')) {
            Schema::table('borrowings', function (Blueprint $table) {
                $table->dropColumn('due_date');
            });
        }

        if (Schema::hasTable('students')) {
            Schema::table('students', function (Blueprint $table) {
                if (Schema::hasColumn('students', 'strand_id')) {
                    $table->dropConstrainedForeignId('strand_id');
                }

                if (Schema::hasColumn('students', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }

        if (Schema::hasTable('sections') && Schema::hasColumn('sections', 'strand_id')) {
            Schema::table('sections', function (Blueprint $table) {
                $table->dropConstrainedForeignId('strand_id');
            });
        }

        if (Schema::hasTable('items')) {
            Schema::table('items', function (Blueprint $table) {
                $columns = [];

                if (Schema::hasColumn('items', 'item_barcode')) {
                    $columns[] = 'item_barcode';
                }

                if (Schema::hasColumn('items', 'item_sku')) {
                    $columns[] = 'item_sku';
                }

                if (Schema::hasColumn('items', 'item_description')) {
                    $columns[] = 'item_description';
                }

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'deleted_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        Schema::dropIfExists('schedules');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('inventories');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('strands');
    }
};