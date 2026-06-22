<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('replied_by_user_id')->nullable()->after('read_at')->constrained('users', 'user_id')->nullOnDelete();
            $table->text('reply_body')->nullable()->after('replied_by_user_id');
            $table->string('reply_attachment_path')->nullable()->after('reply_body');
            $table->string('reply_attachment_name')->nullable()->after('reply_attachment_path');
            $table->string('reply_attachment_mime')->nullable()->after('reply_attachment_name');
            $table->unsignedBigInteger('reply_attachment_size')->nullable()->after('reply_attachment_mime');
            $table->timestamp('replied_at')->nullable()->after('reply_attachment_size');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replied_by_user_id');
            $table->dropColumn([
                'reply_body',
                'reply_attachment_path',
                'reply_attachment_name',
                'reply_attachment_mime',
                'reply_attachment_size',
                'replied_at',
            ]);
        });
    }
};
