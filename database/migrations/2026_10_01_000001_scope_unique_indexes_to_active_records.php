<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('subjects', function (Blueprint $table) {
            $table->softDeletes();
        });

        if (in_array(DB::getDriverName(), ['sqlite', 'pgsql'], true)) {
            $this->createPartialUniqueIndexes();

            return;
        }

        $this->createGeneratedUniqueIndexes();
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['sqlite', 'pgsql'], true)) {
            $this->dropPartialUniqueIndexes();
        } else {
            $this->dropGeneratedUniqueIndexes();
        }

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('instructors', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }

    private function createPartialUniqueIndexes(): void
    {
        $indexes = [
            ['users_email_unique', 'users', 'email'],
            ['users_rfid_tag_unique', 'users', 'rfid_tag'],
            ['instructors_instructor_number_unique', 'instructors', 'instructor_number'],
            ['subjects_subject_code_section_id_unique', 'subjects', 'subject_code'],
            ['students_student_number_unique', 'students', 'student_number'],
            ['students_email_unique', 'students', 'email'],
            ['students_rfid_tag_unique', 'students', 'rfid_tag'],
            ['strands_strand_code_unique', 'strands', 'strand_code'],
            ['inventory_items_barcode_unique', 'inventory_items', 'barcode'],
        ];

        foreach ($indexes as [$index, $table, $column]) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
            DB::statement("CREATE UNIQUE INDEX {$index} ON {$table} ({$column}) WHERE deleted_at IS NULL");
        }
    }

    private function dropPartialUniqueIndexes(): void
    {
        $indexes = [
            ['users_email_unique', 'users', ['email']],
            ['users_rfid_tag_unique', 'users', ['rfid_tag']],
            ['instructors_instructor_number_unique', 'instructors', ['instructor_number']],
            ['subjects_subject_code_section_id_unique', 'subjects', ['subject_code', 'section_id']],
            ['students_student_number_unique', 'students', ['student_number']],
            ['students_email_unique', 'students', ['email']],
            ['students_rfid_tag_unique', 'students', ['rfid_tag']],
            ['strands_strand_code_unique', 'strands', ['strand_code']],
            ['inventory_items_barcode_unique', 'inventory_items', ['barcode']],
        ];

        foreach ($indexes as [$index, $table, $columns]) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
            DB::statement("CREATE UNIQUE INDEX {$index} ON {$table} (".implode(', ', $columns).')');
        }
    }

    private function createGeneratedUniqueIndexes(): void
    {
        $this->replaceWithGeneratedIndex('users', 'email', 'active_email', 'users_active_email_unique');
        $this->replaceWithGeneratedIndex('users', 'rfid_tag', 'active_rfid_tag', 'users_active_rfid_tag_unique');
        $this->replaceWithGeneratedIndex('instructors', 'instructor_number', 'active_instructor_number', 'instructors_active_number_unique');
        $this->replaceWithGeneratedIndex('subjects', 'subject_code', 'active_subject_code', 'subjects_active_code_unique', 'subjects_subject_code_section_id_unique');
        $this->replaceWithGeneratedIndex('students', 'student_number', 'active_student_number', 'students_active_number_unique');
        $this->replaceWithGeneratedIndex('students', 'email', 'active_email', 'students_active_email_unique');
        $this->replaceWithGeneratedIndex('students', 'rfid_tag', 'active_rfid_tag', 'students_active_rfid_tag_unique');
        $this->replaceWithGeneratedIndex('strands', 'strand_code', 'active_strand_code', 'strands_active_code_unique');
        $this->replaceWithGeneratedIndex('inventory_items', 'barcode', 'active_barcode', 'inventory_items_active_barcode_unique');
    }

    private function dropGeneratedUniqueIndexes(): void
    {
        $definitions = [
            ['users', 'active_email', 'users_active_email_unique', ['email'], 'users_email_unique'],
            ['users', 'active_rfid_tag', 'users_active_rfid_tag_unique', ['rfid_tag'], 'users_rfid_tag_unique'],
            ['instructors', 'active_instructor_number', 'instructors_active_number_unique', ['instructor_number'], 'instructors_instructor_number_unique'],
            ['subjects', 'active_subject_code', 'subjects_active_code_unique', ['subject_code', 'section_id'], 'subjects_subject_code_section_id_unique'],
            ['students', 'active_student_number', 'students_active_number_unique', ['student_number'], 'students_student_number_unique'],
            ['students', 'active_email', 'students_active_email_unique', ['email'], 'students_email_unique'],
            ['students', 'active_rfid_tag', 'students_active_rfid_tag_unique', ['rfid_tag'], 'students_rfid_tag_unique'],
            ['strands', 'active_strand_code', 'strands_active_code_unique', ['strand_code'], 'strands_strand_code_unique'],
            ['inventory_items', 'active_barcode', 'inventory_items_active_barcode_unique', ['barcode'], 'inventory_items_barcode_unique'],
        ];

        foreach ($definitions as [$tableName, $generatedColumn, $generatedIndex, $originalColumns, $originalIndex]) {
            Schema::table($tableName, function (Blueprint $table) use ($generatedColumn, $generatedIndex, $originalColumns, $originalIndex) {
                $table->dropUnique($generatedIndex);
                $table->dropColumn($generatedColumn);
                $table->unique($originalColumns, $originalIndex);
            });
        }
    }

    private function replaceWithGeneratedIndex(
        string $tableName,
        string $sourceColumn,
        string $generatedColumn,
        string $generatedIndex,
        ?string $originalIndex = null,
    ): void {
        $originalIndex ??= "{$tableName}_{$sourceColumn}_unique";

        Schema::table($tableName, function (Blueprint $table) use ($sourceColumn, $generatedColumn, $generatedIndex, $originalIndex) {
            $table->dropUnique($originalIndex);
            $table->string($generatedColumn)
                ->storedAs("CASE WHEN deleted_at IS NULL THEN {$sourceColumn} ELSE NULL END");
            $table->unique($generatedColumn, $generatedIndex);
        });
    }
};
