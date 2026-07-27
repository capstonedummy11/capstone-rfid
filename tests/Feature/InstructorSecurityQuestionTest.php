<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('instructors can save security questions for verification', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $user = User::factory()->create([
        'role' => 'instructor',
    ]);

    $questions = [
        [
            'question' => 'What was the name of your first school?',
            'answer' => 'North High',
        ],
        [
            'question' => 'What is your mother\'s maiden name?',
            'answer' => 'Rivera',
        ],
        [
            'question' => 'What was the name of your first pet?',
            'answer' => 'Buddy',
        ],
    ];

    $response = $this
        ->actingAs($user)
        ->post(route('instructor.verify.security.setup'), [
            'questions' => $questions,
        ]);

    $response
        ->assertRedirect()
        ->assertSessionHas('success', 'Security questions saved.');

    $user->refresh();

    expect($user->security_question)->toBe($questions[0]['question'])
        ->and(Hash::check('north high', $user->security_answer_hash))->toBeTrue()
        ->and($user->security_questions)->toHaveCount(3)
        ->and($user->security_questions[1]['question'])->toBe($questions[1]['question'])
        ->and(Hash::check('rivera', $user->security_questions[1]['answer_hash']))->toBeTrue();
});
