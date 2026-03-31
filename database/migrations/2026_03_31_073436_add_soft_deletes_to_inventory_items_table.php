<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
        public function up()
        {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->softDeletes(); // adds nullable deleted_at timestamp
            });
        }

        public function down()
        {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
};
