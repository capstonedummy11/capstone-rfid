<?php

use App\Models\User;
use App\Services\Auth\AccessRevocationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    Schema::dropIfExists('oauth_refresh_tokens');
    Schema::dropIfExists('oauth_access_tokens');
    Schema::dropIfExists('personal_access_tokens');
});

test('database sessions and remember tokens are revoked without affecting another user', function () {
    config(['session.driver' => 'database', 'session.table' => 'sessions']);
    $user = User::factory()->create(['remember_token' => 'old-token']);
    $other = User::factory()->create();
    foreach ([[$user, 'target-session'], [$other, 'other-session']] as [$owner, $id]) {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $owner->user_id, 'payload' => 'x', 'last_activity' => now()->timestamp]);
    }

    $result = app(AccessRevocationService::class)->revokeAccess($user);

    expect($user->fresh()->remember_token)->not->toBeNull()->not->toBe('old-token')
        ->and($result->sessionsCleared)->toBe(1)
        ->and($result->rememberRotated)->toBeTrue();
    $this->assertDatabaseMissing('sessions', ['id' => 'target-session']);
    $this->assertDatabaseHas('sessions', ['id' => 'other-session']);
});

test('non database session drivers log and expose the skipped revocation', function () {
    config(['session.driver' => 'file']);
    Log::shouldReceive('warning')->once();
    $user = User::factory()->create(['remember_token' => 'old-token']);

    $result = app(AccessRevocationService::class)->revokeAccess($user);

    expect($result->sessionsCleared)->toBeNull()
        ->and($result->skippedReasons)->not->toBeEmpty()
        ->and($user->fresh()->remember_token)->not->toBeNull()->not->toBe('old-token');
});

test('missing Passport and Sanctum tables are safe no ops', function () {
    config(['session.driver' => 'database']);
    $result = app(AccessRevocationService::class)->revokeAccess(User::factory()->create());

    expect($result->passportRevoked)->toBe(0)
        ->and($result->passportRefreshRevoked)->toBe(0)
        ->and($result->sanctumRevoked)->toBe(0);
});

test('Passport access and refresh tokens are revoked only for the selected user', function () {
    Schema::create('oauth_access_tokens', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->boolean('revoked')->default(false);
    });
    Schema::create('oauth_refresh_tokens', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('access_token_id');
        $table->boolean('revoked')->default(false);
    });
    $user = User::factory()->create();
    $other = User::factory()->create();
    DB::table('oauth_access_tokens')->insert([['id' => 'mine', 'user_id' => $user->user_id], ['id' => 'other', 'user_id' => $other->user_id]]);
    DB::table('oauth_refresh_tokens')->insert([['id' => 'refresh-mine', 'access_token_id' => 'mine'], ['id' => 'refresh-other', 'access_token_id' => 'other']]);

    $result = app(AccessRevocationService::class)->revokeAccess($user);

    expect($result->passportRevoked)->toBe(1)->and($result->passportRefreshRevoked)->toBe(1);
    $this->assertDatabaseHas('oauth_access_tokens', ['id' => 'mine', 'revoked' => true]);
    $this->assertDatabaseHas('oauth_access_tokens', ['id' => 'other', 'revoked' => false]);
    $this->assertDatabaseHas('oauth_refresh_tokens', ['id' => 'refresh-mine', 'revoked' => true]);
    $this->assertDatabaseHas('oauth_refresh_tokens', ['id' => 'refresh-other', 'revoked' => false]);
});

test('Sanctum tokens are deleted only for the selected user', function () {
    Schema::create('personal_access_tokens', function (Blueprint $table) {
        $table->id();
        $table->string('tokenable_type');
        $table->unsignedBigInteger('tokenable_id');
    });
    $user = User::factory()->create();
    $other = User::factory()->create();
    DB::table('personal_access_tokens')->insert([
        ['tokenable_type' => $user->getMorphClass(), 'tokenable_id' => $user->user_id],
        ['tokenable_type' => $other->getMorphClass(), 'tokenable_id' => $other->user_id],
    ]);

    $result = app(AccessRevocationService::class)->revokeAccess($user);

    expect($result->sanctumRevoked)->toBe(1);
    $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->user_id]);
    $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $other->user_id]);
});

test('a later revocation failure rolls back every earlier step', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->create(['remember_token' => 'old-token']);
    DB::table('sessions')->insert(['id' => 'rollback-session', 'user_id' => $user->user_id, 'payload' => 'x', 'last_activity' => now()->timestamp]);
    $service = new class extends AccessRevocationService
    {
        protected function revokeSanctumTokens(User $user, array &$skippedReasons): int
        {
            throw new RuntimeException('forced failure');
        }
    };

    expect(fn () => $service->revokeAccess($user))->toThrow(RuntimeException::class, 'forced failure');
    expect($user->fresh()->remember_token)->toBe('old-token');
    $this->assertDatabaseHas('sessions', ['id' => 'rollback-session']);
});
