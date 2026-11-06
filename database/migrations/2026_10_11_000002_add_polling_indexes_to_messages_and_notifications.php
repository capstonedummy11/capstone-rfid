<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_portal_messages', function (Blueprint $table) {
            $table->index(
                ['sender_user_id', 'recipient_user_id', 'student_portal_message_id'],
                'spm_sender_recipient_poll_idx',
            );
            $table->index(
                ['recipient_user_id', 'sender_user_id', 'student_portal_message_id'],
                'spm_recipient_sender_poll_idx',
            );
            $table->index(
                ['recipient_user_id', 'read_at', 'student_portal_message_id'],
                'spm_unread_poll_idx',
            );
            $table->index(
                ['student_id', 'student_portal_message_id'],
                'spm_student_poll_idx',
            );
        });

        Schema::table('online_class_notifications', function (Blueprint $table) {
            $table->index(
                ['student_id', 'read_at', 'online_class_notification_id'],
                'ocn_student_unread_poll_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('online_class_notifications', function (Blueprint $table) {
            $table->dropIndex('ocn_student_unread_poll_idx');
        });

        Schema::table('student_portal_messages', function (Blueprint $table) {
            $table->dropIndex('spm_sender_recipient_poll_idx');
            $table->dropIndex('spm_recipient_sender_poll_idx');
            $table->dropIndex('spm_unread_poll_idx');
            $table->dropIndex('spm_student_poll_idx');
        });
    }
};
