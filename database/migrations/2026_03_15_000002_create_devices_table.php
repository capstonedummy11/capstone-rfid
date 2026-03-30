<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('items', function (Blueprint $table) {
      $table->id('item_id');
      $table->string('item_name');
      $table->text('item_description')->nullable();
      $table->string('item_sku')->nullable()->unique();
      $table->string('item_barcode')->nullable()->unique();
      $table->enum('status', [
        'available',
        'borrowed',
        'damaged',
        'under_maintenance',
        'lost',
      ])->default('available');
      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('items');
  }
};
