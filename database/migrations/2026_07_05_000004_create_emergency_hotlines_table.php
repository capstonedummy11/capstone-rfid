<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_hotlines', function (Blueprint $table) {
            $table->id('emergency_hotline_id');
            $table->string('name');
            $table->string('category')->default('clinic');
            $table->string('phone_number');
            $table->string('contact_person')->nullable();
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
            $table->index(['category', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_hotlines');
    }
};
