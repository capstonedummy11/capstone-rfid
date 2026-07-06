<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_root_admin')) {
                $table->boolean('is_root_admin')->default(false)->after('role');
            }
        });

        $firstAdminId = DB::table('users')
            ->whereRaw('LOWER(role) = ?', ['admin'])
            ->orderBy('user_id')
            ->value('user_id');

        if ($firstAdminId) {
            DB::table('users')
                ->where('user_id', $firstAdminId)
                ->update(['is_root_admin' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_root_admin')) {
                $table->dropColumn('is_root_admin');
            }
        });
    }
};
