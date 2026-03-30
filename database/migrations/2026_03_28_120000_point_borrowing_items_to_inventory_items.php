<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    if (Schema::hasTable('borrowing_items') && Schema::hasTable('inventory_items')) {
      Schema::table('borrowing_items', function (Blueprint $table) {
        $table->dropForeign(['item_id']);
      });

      Schema::table('borrowing_items', function (Blueprint $table) {
        $table->foreign('item_id')
          ->references('item_id')
          ->on('inventory_items')
          ->restrictOnDelete();
      });
    }

    if (Schema::hasTable('borrowings') && Schema::hasColumn('borrowings', 'item_id') && Schema::hasTable('inventory_items')) {
      Schema::table('borrowings', function (Blueprint $table) {
        $table->dropForeign(['item_id']);
      });

      Schema::table('borrowings', function (Blueprint $table) {
        $table->foreign('item_id')
          ->references('item_id')
          ->on('inventory_items')
          ->nullOnDelete();
      });
    }
  }

  public function down(): void
  {
    if (Schema::hasTable('borrowing_items') && Schema::hasTable('items')) {
      Schema::table('borrowing_items', function (Blueprint $table) {
        $table->dropForeign(['item_id']);
      });

      Schema::table('borrowing_items', function (Blueprint $table) {
        $table->foreign('item_id')
          ->references('item_id')
          ->on('items')
          ->restrictOnDelete();
      });
    }

    if (Schema::hasTable('borrowings') && Schema::hasColumn('borrowings', 'item_id') && Schema::hasTable('items')) {
      Schema::table('borrowings', function (Blueprint $table) {
        $table->dropForeign(['item_id']);
      });

      Schema::table('borrowings', function (Blueprint $table) {
        $table->foreign('item_id')
          ->references('item_id')
          ->on('items')
          ->nullOnDelete();
      });
    }
  }
};