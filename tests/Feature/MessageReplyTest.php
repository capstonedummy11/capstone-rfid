<?php

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('assigned instructors can reply to a message with an attachment', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    Storage::fake('public');

    $instructor = User::factory()->create(['role' => 'instructor']);
    $message = Message::query()->create([
        'instructor_user_id' => $instructor->user_id,
        'sender_type' => 'student',
        'sender_name' => 'Alex Student',
        'body' => 'Can I submit my absence note?',
    ]);

    $response = $this
        ->actingAs($instructor)
        ->withSession(['instructor_verified' => true])
        ->post(route('admin.messages.reply', $message), [
            'reply_body' => 'Yes, please attach it here.',
            'reply_attachment' => UploadedFile::fake()->create('guidelines.pdf', 120, 'application/pdf'),
        ]);

    $response
        ->assertRedirect()
        ->assertSessionHas('success', 'Reply sent.');

    $message->refresh();

    expect($message->reply_body)->toBe('Yes, please attach it here.')
        ->and($message->replied_by_user_id)->toBe($instructor->user_id)
        ->and($message->reply_attachment_name)->toBe('guidelines.pdf')
        ->and($message->replied_at)->not->toBeNull()
        ->and($message->read_at)->not->toBeNull();

    Storage::disk('public')->assertExists($message->reply_attachment_path);
});
