<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_excuse_letters', function (Blueprint $table) {
            if (! Schema::hasColumn('student_excuse_letters', 'parent_signature')) {
                $table->string('parent_signature')->nullable()->after('status');
            }

            if (! Schema::hasColumn('student_excuse_letters', 'parent_approval_notes')) {
                $table->text('parent_approval_notes')->nullable()->after('parent_signature');
            }

            if (! Schema::hasColumn('student_excuse_letters', 'parent_approved_by_user_id')) {
                $table->foreignId('parent_approved_by_user_id')
                    ->nullable()
                    ->after('parent_approval_notes')
                    ->constrained('users', 'user_id')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('student_excuse_letters', 'parent_approved_at')) {
                $table->timestamp('parent_approved_at')->nullable()->after('parent_approved_by_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_excuse_letters', function (Blueprint $table) {
            if (Schema::hasColumn('student_excuse_letters', 'parent_approved_by_user_id')) {
                $table->dropConstrainedForeignId('parent_approved_by_user_id');
            }

            foreach (['parent_approved_at', 'parent_approval_notes', 'parent_signature'] as $column) {
                if (Schema::hasColumn('student_excuse_letters', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
