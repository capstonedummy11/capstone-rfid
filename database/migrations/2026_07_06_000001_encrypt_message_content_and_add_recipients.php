<?php

use App\Models\Students;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->text('subject_ciphertext')->nullable()->after('subject');
            $table->longText('body_ciphertext')->nullable()->after('body');
        });

        Schema::table('student_portal_messages', function (Blueprint $table) {
            $table->foreignId('recipient_user_id')->nullable()->after('sender_user_id')->constrained('users', 'user_id')->nullOnDelete();
            $table->text('subject_ciphertext')->nullable()->after('subject');
            $table->longText('body_ciphertext')->nullable()->after('body');
            $table->index(['recipient_user_id', 'created_at']);
        });

        DB::table('messages')
            ->orderBy('message_id')
            ->chunkById(100, function ($messages) {
                foreach ($messages as $message) {
                    DB::table('messages')
                        ->where('message_id', $message->message_id)
                        ->update([
                            'subject_ciphertext' => $message->subject !== null ? Crypt::encryptString((string) $message->subject) : null,
                            'body_ciphertext' => Crypt::encryptString((string) $message->body),
                            'subject' => $message->subject !== null ? 'Encrypted message' : null,
                            'body' => 'Encrypted message',
                        ]);
                }
            }, 'message_id');

        DB::table('student_portal_messages')
            ->orderBy('student_portal_message_id')
            ->chunkById(100, function ($messages) {
                foreach ($messages as $message) {
                    DB::table('student_portal_messages')
                        ->where('student_portal_message_id', $message->student_portal_message_id)
                        ->update([
                            'recipient_user_id' => $this->inferPortalRecipient($message),
                            'subject_ciphertext' => Crypt::encryptString((string) $message->subject),
                            'body_ciphertext' => Crypt::encryptString((string) $message->body),
                            'subject' => 'Encrypted message',
                            'body' => 'Encrypted message',
                        ]);
                }
            }, 'student_portal_message_id');
    }

    public function down(): void
    {
        DB::table('messages')
            ->orderBy('message_id')
            ->chunkById(100, function ($messages) {
                foreach ($messages as $message) {
                    DB::table('messages')
                        ->where('message_id', $message->message_id)
                        ->update([
                            'subject' => $this->decryptOrNull($message->subject_ciphertext),
                            'body' => $this->decryptOrNull($message->body_ciphertext) ?? (string) $message->body,
                        ]);
                }
            }, 'message_id');

        DB::table('student_portal_messages')
            ->orderBy('student_portal_message_id')
            ->chunkById(100, function ($messages) {
                foreach ($messages as $message) {
                    DB::table('student_portal_messages')
                        ->where('student_portal_message_id', $message->student_portal_message_id)
                        ->update([
                            'subject' => $this->decryptOrNull($message->subject_ciphertext) ?? (string) $message->subject,
                            'body' => $this->decryptOrNull($message->body_ciphertext) ?? (string) $message->body,
                        ]);
                }
            }, 'student_portal_message_id');

        Schema::table('student_portal_messages', function (Blueprint $table) {
            $table->dropIndex(['recipient_user_id', 'created_at']);
            $table->dropConstrainedForeignId('recipient_user_id');
            $table->dropColumn(['subject_ciphertext', 'body_ciphertext']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['subject_ciphertext', 'body_ciphertext']);
        });
    }

    private function inferPortalRecipient(object $message): ?int
    {
        $senderRole = strtolower((string) $message->sender_role);

        if (in_array($senderRole, ['student', 'parent'], true)) {
            return $message->instructor_user_id ?: null;
        }

        $student = Students::query()->find($message->student_id);

        return $student
            ? User::query()->where('email', $student->email)->value('user_id')
            : null;
    }

    private function decryptOrNull(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return $value;
        }
    }
};
