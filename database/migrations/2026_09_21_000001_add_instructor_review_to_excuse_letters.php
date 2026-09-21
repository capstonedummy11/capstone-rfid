<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_portal_messages', function (Blueprint $table) {
            $table->foreignId('student_excuse_letter_id')
                ->nullable()
                ->constrained('student_excuse_letters', 'student_excuse_letter_id')
                ->nullOnDelete();
            $table->string('excuse_letter_review_decision')->nullable();
            $table->foreignId('excuse_letter_reviewed_by_user_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamp('excuse_letter_reviewed_at')->nullable();
            $table->string('excuse_letter_review_email_subject')->nullable();
            $table->text('excuse_letter_review_email_body')->nullable();
            $table->json('excuse_letter_review_recipients')->nullable();
        });

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
        Schema::table('student_portal_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('excuse_letter_reviewed_by_user_id');
            $table->dropConstrainedForeignId('student_excuse_letter_id');
            $table->dropColumn([
                'excuse_letter_review_decision',
                'excuse_letter_reviewed_at',
                'excuse_letter_review_email_subject',
                'excuse_letter_review_email_body',
                'excuse_letter_review_recipients',
            ]);
        });
    }
};
