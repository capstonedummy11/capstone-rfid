<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('borrowing_items', function (Blueprint $table) {
      $table->id();
      $table->foreignId('borrowing_id')
        ->constrained('borrowings', 'borrowing_id')
        ->cascadeOnDelete();
      $table->foreignId('item_id')
        ->constrained('items', 'item_id')
        ->restrictOnDelete();
      $table->unsignedInteger('quantity')->default(1);
      $table->enum('status', [
        'borrowed',
        'borrow',
        'returned',
        'damaged',
        'lost',
      ])->default('borrowed');
      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('borrowing_items');
  }
};
