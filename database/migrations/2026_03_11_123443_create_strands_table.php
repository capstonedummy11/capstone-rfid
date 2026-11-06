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
        Schema::create('strands', function (Blueprint $table) {
            $table->id('strand_id');
            $table->string('strand_code')->unique();
            $table->string('strand_name');
            $table->string('department');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    // @function down: Ibinabalik ang schema changes ng migration na ito.
    // @useIn down: Laravel migration runner
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('strands');
    }
};
