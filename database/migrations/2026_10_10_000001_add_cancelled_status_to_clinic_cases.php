<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_cases', function (Blueprint $table) {
            $table->enum('status', ['open', 'monitoring', 'resolved', 'referred', 'cancelled'])
                ->default('open')
                ->change();
        });
    }

    public function down(): void
    {
        DB::table('clinic_cases')->where('status', 'cancelled')->update(['status' => 'open']);

        Schema::table('clinic_cases', function (Blueprint $table) {
            $table->enum('status', ['open', 'monitoring', 'resolved', 'referred'])
                ->default('open')
                ->change();
        });
    }
};
