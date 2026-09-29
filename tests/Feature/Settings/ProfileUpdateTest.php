<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('account owner can upload and replace their profile picture', function () {
    Storage::fake('public');
    Storage::disk('public')->put('profile-photos/old-photo.png', 'old photo');

    $user = User::factory()->create([
        'profile_photo_path' => 'profile-photos/old-photo.png',
    ]);

    $this->actingAs($user)
        ->post(route('profile.update'), [
            '_method' => 'patch',
            'name' => 'Profile Owner',
            'email' => $user->email,
            'phone' => '09171234567',
            'gender' => 'female',
            'profile_photo' => UploadedFile::fake()->image('profile.png', 300, 300)->size(500),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->profile_photo_path)->toStartWith('profile-photos/')
        ->and($user->phone)->toBe('09171234567')
        ->and($user->gender)->toBe('female');
    Storage::disk('public')->assertExists($user->profile_photo_path);
    Storage::disk('public')->assertMissing('profile-photos/old-photo.png');
});

test('account owner can remove their profile picture', function () {
    Storage::fake('public');
    Storage::disk('public')->put('profile-photos/remove-me.png', 'photo');

    $user = User::factory()->create([
        'profile_photo_path' => 'profile-photos/remove-me.png',
    ]);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'remove_profile_photo' => true,
        ])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->profile_photo_path)->toBeNull();
    Storage::disk('public')->assertMissing('profile-photos/remove-me.png');
});

test('profile picture rejects unsupported files', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('profile.update'), [
            '_method' => 'patch',
            'name' => $user->name,
            'email' => $user->email,
            'profile_photo' => UploadedFile::fake()->create('profile.svg', 10, 'image/svg+xml'),
        ])
        ->assertSessionHasErrors('profile_photo');

    expect($user->fresh()->profile_photo_path)->toBeNull();
});

test('user can delete their account', function () {
    Storage::fake('public');
    Storage::disk('public')->put('profile-photos/deleted-account.png', 'photo');
    $user = User::factory()->create([
        'profile_photo_path' => 'profile-photos/deleted-account.png',
    ]);

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
    Storage::disk('public')->assertMissing('profile-photos/deleted-account.png');
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});
