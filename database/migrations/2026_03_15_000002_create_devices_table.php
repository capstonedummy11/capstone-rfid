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
      $table->string('item_code')->unique();
      $table->string('item_type');
      $table->string('barcode')->unique();
      $table->string('brand')->nullable();
      $table->string('model')->nullable();
      $table->text('description')->nullable();
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
