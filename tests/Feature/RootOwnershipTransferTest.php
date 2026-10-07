<?php

use App\Models\RootAuditLog;
use App\Models\RootOverrideRequest;
use App\Models\RootTransferRequest;
use App\Models\User;
use App\Notifications\RootOwnershipNotification;
use App\Services\RootOverrideService;
use App\Services\RootTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    config(['session.driver' => 'database']);
});

function ownershipAdmin(array $attributes = []): User
{
    return User::factory()->create(array_merge(['role' => 'admin', 'is_root_admin' => false, 'password' => 'password'], $attributes));
}

function requestTransfer($test, User $root, User $target): RootTransferRequest
{
    $test->actingAs($root)->post(route('admin.root-ownership.transfers.store'), [
        'to_user_id' => $target->user_id,
        'password' => 'password',
        'confirmed' => true,
    ])->assertRedirect()->assertSessionHasNoErrors();

    return RootTransferRequest::query()->sole();
}

test('accepted normal transfer completes atomically and revokes the old owner access', function () {
    $root = ownershipAdmin(['is_root_admin' => true, 'remember_token' => 'old-remember']);
    $target = ownershipAdmin();
    DB::table('sessions')->insert(['id' => 'old-root-session', 'user_id' => $root->user_id, 'payload' => 'x', 'last_activity' => now()->timestamp]);
    $transfer = requestTransfer($this, $root, $target);
    $acceptUrl = URL::temporarySignedRoute('root-ownership.accept.store', $transfer->expires_at, ['transfer' => $transfer->id]);

    $this->actingAs($target)->post($acceptUrl)->assertRedirect(route('admin.users.index'));
    $this->travel((int) config('root_ownership.transfer_delay_days'))->days();
    app(RootTransferService::class)->processDue();

    expect($root->fresh()->is_root_admin)->toBeFalse()
        ->and($target->fresh()->is_root_admin)->toBeTrue()
        ->and($root->fresh()->remember_token)->not->toBe('old-remember')
        ->and(RootTransferRequest::query()->sole()->status)->toBe('completed');
    $this->assertDatabaseMissing('sessions', ['id' => 'old-root-session']);
    $this->assertDatabaseHas('root_audit_logs', ['action' => 'completed', 'target_user_id' => $target->user_id]);
    $audit = RootAuditLog::query()->where('action', 'completed')->sole();
    expect($audit->metadata['old_owner_access_revocation']['sessions_cleared'])->toBe(1)
        ->and($audit->metadata['old_owner_access_revocation']['remember_rotated'])->toBeTrue();
});

test('root owner can cancel and only one pending transfer is allowed', function () {
    $root = ownershipAdmin(['is_root_admin' => true]);
    $first = ownershipAdmin();
    $second = ownershipAdmin();
    $transfer = requestTransfer($this, $root, $first);

    $this->actingAs($root)->post(route('admin.root-ownership.transfers.store'), [
        'to_user_id' => $second->user_id, 'password' => 'password', 'confirmed' => true,
    ])->assertSessionHasErrors('to_user_id');

    $this->actingAs($root)->delete(route('admin.root-ownership.transfers.cancel', $transfer))->assertRedirect();
    expect($transfer->fresh()->status)->toBe('cancelled')->and($transfer->fresh()->pending_guard)->toBeNull();
});

test('signed one time email link cancels a transfer without trusting an active session', function () {
    $root = ownershipAdmin(['is_root_admin' => true]);
    $target = ownershipAdmin();
    $transfer = requestTransfer($this, $root, $target);
    $cancelUrl = null;

    Notification::assertSentTo($root, RootOwnershipNotification::class, function ($notification) use ($root, &$cancelUrl) {
        $cancelUrl = $notification->toMail($root)->actionUrl;

        return $cancelUrl !== null;
    });

    $this->get($cancelUrl)->assertOk()->assertInertia(fn ($page) => $page->component('RootOwnership/Cancel'));
    $this->post($cancelUrl)->assertOk()->assertInertia(fn ($page) => $page->component('RootOwnership/Result'));
    expect($transfer->fresh()->status)->toBe('cancelled')->and($transfer->fresh()->cancel_token_hash)->toBeNull();
    $this->get($cancelUrl)->assertOk()->assertInertia(fn ($page) => $page->component('RootOwnership/Result'));
});

test('unauthorized admin cannot create a normal transfer', function () {
    $admin = ownershipAdmin();
    $target = ownershipAdmin();
    $this->actingAs($admin)->post(route('admin.root-ownership.transfers.store'), [
        'to_user_id' => $target->user_id, 'password' => 'password', 'confirmed' => true,
    ])->assertForbidden();
});

test('completed transfer enforces the configured cooldown', function () {
    $root = ownershipAdmin(['is_root_admin' => true]);
    $target = ownershipAdmin();
    $third = ownershipAdmin();
    $transfer = requestTransfer($this, $root, $target);
    app(RootTransferService::class)->accept($transfer, $target);
    $this->travel((int) config('root_ownership.transfer_delay_days'))->days();
    app(RootTransferService::class)->processDue();

    $this->actingAs($target->fresh())->post(route('admin.root-ownership.transfers.store'), [
        'to_user_id' => $third->user_id, 'password' => 'password', 'confirmed' => true,
    ])->assertSessionHasErrors('to_user_id');
});

test('unaccepted transfers expire at the effective date', function () {
    $root = ownershipAdmin(['is_root_admin' => true]);
    $transfer = requestTransfer($this, $root, ownershipAdmin());
    $this->travel((int) config('root_ownership.transfer_delay_days'))->days();
    app(RootTransferService::class)->processDue();
    expect($transfer->fresh()->status)->toBe('expired');
});

test('emergency override blocks self approval and requires two independent approvals', function () {
    $root = ownershipAdmin(['is_root_admin' => true, 'remember_token' => 'root-token']);
    $requester = ownershipAdmin();
    $target = ownershipAdmin();
    $approverOne = ownershipAdmin();
    $approverTwo = ownershipAdmin();

    $this->actingAs($requester)->post(route('admin.root-ownership.overrides.store'), [
        'to_user_id' => $target->user_id, 'reason' => 'The owner is confirmed unreachable after an account compromise.',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $override = RootOverrideRequest::query()->sole();

    $this->actingAs($requester)->post(route('admin.root-ownership.overrides.decide', $override), ['decision' => 'approve'])->assertForbidden();
    $this->actingAs($approverOne)->post(route('admin.root-ownership.overrides.decide', $override), ['decision' => 'approve'])->assertRedirect();
    expect($override->fresh()->status)->toBe('pending');
    $this->actingAs($approverTwo)->post(route('admin.root-ownership.overrides.decide', $override), ['decision' => 'approve'])->assertRedirect();
    expect($override->fresh()->status)->toBe('approved')->and($override->fresh()->execute_at)->not->toBeNull();

    $this->travel((int) config('root_ownership.override_delay_hours'))->hours();
    app(RootOverrideService::class)->processDue();
    expect($root->fresh()->is_root_admin)->toBeFalse()->and($target->fresh()->is_root_admin)->toBeTrue();
    $this->assertDatabaseHas('root_audit_logs', ['action' => 'override_executed', 'target_user_id' => $target->user_id]);
});

test('an emergency override expires without enough approvals', function () {
    ownershipAdmin(['is_root_admin' => true]);
    $requester = ownershipAdmin();
    $target = ownershipAdmin();
    $override = app(RootOverrideService::class)->request($requester, $target, 'The owner is unreachable and recovery has been independently escalated.', request());
    $this->travel((int) config('root_ownership.override_approval_window_hours'))->hours();
    app(RootOverrideService::class)->processDue();
    expect($override->fresh()->status)->toBe('expired');
});
