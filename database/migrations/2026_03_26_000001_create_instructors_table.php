<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // @function up: Ginagawa o binabago ang database schema para sa migration na ito.
    // @useIn up: Laravel migration runner
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('instructors', function (Blueprint $table) {
            $table->id('instructor_id');
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->foreignId('strand_id')->constrained('strands', 'strand_id');
            $table->string('instructor_number')->unique();
            $table->enum('status', ['active', 'inactive', 'on_leave'])->default('active');
            $table->timestamps();
        });
    }

    // @function down: Ibinabalik ang schema changes ng migration na ito.
    // @useIn down: Laravel migration runner
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instructors');
    }
};
