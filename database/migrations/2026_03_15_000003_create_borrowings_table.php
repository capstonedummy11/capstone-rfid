<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('borrowings', function (Blueprint $table) {
      $table->id('borrowing_id');

      // Borrower: either a student OR an instructor (user with role = instructor/teacher).
      // Only one of these will be set per row.
      $table->foreignId('student_id')
        ->nullable()
        ->constrained('students', 'student_id')
        ->nullOnDelete();

      $table->foreignId('user_id')
        ->nullable()
        ->constrained('users', 'user_id')
        ->nullOnDelete();

      $table->foreignId('item_id')
        ->nullable()
        ->constrained('items', 'item_id')
        ->nullOnDelete();

      $table->unsignedInteger('quantity')->default(1);

      // Which role is borrowing — used for display in the table (Student / Instructor)
      $table->enum('borrower_type', ['student', 'instructor']);

      $table->timestamp('borrowed_at');
      $table->timestamp('returned_at')->nullable();

      $table->enum('status', [
        'active',       // currently borrowed
        'returned',     // returned on time
        'overdue',      // not returned past due date
        'damaged',      // returned damaged
        'lost',
      ])->default('active');

      $table->date('due_date')->nullable();
      $table->text('remarks')->nullable();
      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('borrowings');
  }
};
