<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('panel_devices', function (Blueprint $table) {
            $table->id('panel_device_id');
            $table->string('label')->unique();
            $table->string('pin_hash');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('panel_devices')->insert([
            'label' => 'Attendance Console',
            'pin_hash' => Hash::make((string) config('panel.pin', '1234')),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('panel_devices');
    }
};
