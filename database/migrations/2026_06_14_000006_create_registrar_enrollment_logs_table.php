<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrar_enrollment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registrar_user_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->string('action');
            $table->string('person_type');
            $table->unsignedBigInteger('person_id');
            $table->string('person_name');
            $table->string('identifier')->nullable();
            $table->timestamps();

            $table->index(['action', 'created_at']);
            $table->index(['person_type', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrar_enrollment_logs');
    }
};
