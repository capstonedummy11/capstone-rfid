<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id('academic_year_id');
            $table->string('name', 20)->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('draft')->index();
            $table->string('active_semester', 50)->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by_user_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->text('reopen_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};
