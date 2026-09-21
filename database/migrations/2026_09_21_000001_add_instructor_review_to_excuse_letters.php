<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addMissingColumns();
        $this->addMissingForeignKeys();

        DB::table('student_portal_messages')
            ->where('attachment_name', 'like', 'excuse-letter-%.pdf')
            ->orderBy('student_portal_message_id')
            ->each(function (object $message): void {
                if (! preg_match('/^excuse-letter-(\d+)\.pdf$/i', (string) $message->attachment_name, $matches)) {
                    return;
                }

                $letterId = (int) $matches[1];
                if (DB::table('student_excuse_letters')->where('student_excuse_letter_id', $letterId)->exists()) {
                    DB::table('student_portal_messages')
                        ->where('student_portal_message_id', $message->student_portal_message_id)
                        ->update(['student_excuse_letter_id' => $letterId]);
                }
            });
    }

    public function down(): void
    {
        $this->dropForeignKeyForColumn('excuse_letter_reviewed_by_user_id');
        $this->dropForeignKeyForColumn('student_excuse_letter_id');

        Schema::table('student_portal_messages', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('student_portal_messages', 'student_excuse_letter_id') ? 'student_excuse_letter_id' : null,
                Schema::hasColumn('student_portal_messages', 'excuse_letter_reviewed_by_user_id') ? 'excuse_letter_reviewed_by_user_id' : null,
                Schema::hasColumn('student_portal_messages', 'excuse_letter_review_decision') ? 'excuse_letter_review_decision' : null,
                Schema::hasColumn('student_portal_messages', 'excuse_letter_reviewed_at') ? 'excuse_letter_reviewed_at' : null,
                Schema::hasColumn('student_portal_messages', 'excuse_letter_review_email_subject') ? 'excuse_letter_review_email_subject' : null,
                Schema::hasColumn('student_portal_messages', 'excuse_letter_review_email_body') ? 'excuse_letter_review_email_body' : null,
                Schema::hasColumn('student_portal_messages', 'excuse_letter_review_recipients') ? 'excuse_letter_review_recipients' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    private function addMissingColumns(): void
    {
        $definitions = [
            'student_excuse_letter_id' => fn (Blueprint $table) => $table->unsignedBigInteger('student_excuse_letter_id')->nullable(),
            'excuse_letter_review_decision' => fn (Blueprint $table) => $table->string('excuse_letter_review_decision')->nullable(),
            'excuse_letter_reviewed_by_user_id' => fn (Blueprint $table) => $table->unsignedBigInteger('excuse_letter_reviewed_by_user_id')->nullable(),
            'excuse_letter_reviewed_at' => fn (Blueprint $table) => $table->timestamp('excuse_letter_reviewed_at')->nullable(),
            'excuse_letter_review_email_subject' => fn (Blueprint $table) => $table->string('excuse_letter_review_email_subject')->nullable(),
            'excuse_letter_review_email_body' => fn (Blueprint $table) => $table->text('excuse_letter_review_email_body')->nullable(),
            'excuse_letter_review_recipients' => fn (Blueprint $table) => $table->json('excuse_letter_review_recipients')->nullable(),
        ];

        foreach ($definitions as $column => $definition) {
            if (Schema::hasColumn('student_portal_messages', $column)) {
                continue;
            }

            Schema::table('student_portal_messages', function (Blueprint $table) use ($definition): void {
                $definition($table);
            });
        }
    }

    private function addMissingForeignKeys(): void
    {
        if (! $this->hasForeignKeyForColumn('student_excuse_letter_id')) {
            Schema::table('student_portal_messages', function (Blueprint $table): void {
                $table->foreign('student_excuse_letter_id', 'spm_excuse_letter_fk')
                    ->references('student_excuse_letter_id')
                    ->on('student_excuse_letters')
                    ->nullOnDelete();
            });
        }

        if (! $this->hasForeignKeyForColumn('excuse_letter_reviewed_by_user_id')) {
            Schema::table('student_portal_messages', function (Blueprint $table): void {
                $table->foreign('excuse_letter_reviewed_by_user_id', 'spm_excuse_reviewer_fk')
                    ->references('user_id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    private function hasForeignKeyForColumn(string $column): bool
    {
        return collect(Schema::getForeignKeys('student_portal_messages'))
            ->contains(fn (array $foreignKey): bool => in_array($column, $foreignKey['columns'], true));
    }

    private function dropForeignKeyForColumn(string $column): void
    {
        $foreignKey = collect(Schema::getForeignKeys('student_portal_messages'))
            ->first(fn (array $foreignKey): bool => in_array($column, $foreignKey['columns'], true));

        if (! $foreignKey) {
            return;
        }

        Schema::table('student_portal_messages', function (Blueprint $table) use ($column, $foreignKey): void {
            $table->dropForeign($foreignKey['name'] ?: [$column]);
        });
    }
};
