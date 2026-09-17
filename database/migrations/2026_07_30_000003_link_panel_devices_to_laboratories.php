<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('panel_devices', function (Blueprint $table) {
            $table->foreignId('laboratory_id')
                ->nullable()
                ->unique()
                ->after('panel_device_id')
                ->constrained('laboratories', 'laboratory_id')
                ->nullOnDelete();
            $table->string('description')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('panel_devices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('laboratory_id');
            $table->dropColumn('description');
        });
    }
};
