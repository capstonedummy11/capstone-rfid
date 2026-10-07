<?php

namespace App\Services;

use App\Models\RootTransferRequest;
use App\Models\User;
use App\Notifications\RootOwnershipNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RootTransferService
{
    // @function __construct: Tinatanggap ang dependencies ng Root Transfer sa pagbuo ng object.
    // @useIn __construct: Laravel dependency injection kapag ginagamit ang RootTransferService
    public function __construct(private readonly RootOwnershipSwapService $swap, private readonly RootAuditService $audit) {}

    // @function request: Pinoproseso ang request sa database transaction.
    // @useIn request: app/Http/Controllers/RootOwnershipController.php
    public function request(User $actor, User $target, Request $httpRequest): RootTransferRequest
    {
        $token = Str::random(64);
        $transfer = DB::transaction(function () use ($actor, $target, $httpRequest, $token) {
            $owner = User::query()->where('is_root_admin', true)->where('role', 'admin')->lockForUpdate()->sole();
            abort_unless($actor->is($owner), 403);
            abort_if($actor->is($target), 422, 'Choose a different Admin as the new owner.');
            abort_unless(strtolower((string) $target->role) === 'admin' && ! $target->trashed(), 422, 'The new owner must be an active Admin.');

            if (RootTransferRequest::query()->whereNotNull('pending_guard')->lockForUpdate()->exists()
                || \App\Models\RootOverrideRequest::query()->whereNotNull('pending_guard')->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['to_user_id' => 'Another Root Admin transfer is already pending.']);
            }
            $cooldown = (int) config('root_ownership.transfer_cooldown_days', 14);
            if (RootTransferRequest::query()->where('status', 'completed')->where('completed_at', '>', now()->subDays($cooldown))->exists()) {
                throw ValidationException::withMessages(['to_user_id' => "A new transfer is blocked for {$cooldown} days after completion."]);
            }

            $effectiveAt = now()->addDays((int) config('root_ownership.transfer_delay_days', 14));
            $model = RootTransferRequest::query()->create([
                'from_user_id' => $actor->user_id, 'to_user_id' => $target->user_id, 'requested_by' => $actor->user_id,
                'status' => 'pending', 'pending_guard' => 'pending', 'cancel_token_hash' => hash('sha256', $token),
                'effective_at' => $effectiveAt, 'expires_at' => $effectiveAt,
                'request_ip' => $httpRequest->ip(), 'request_user_agent' => $httpRequest->userAgent(),
            ]);
            $this->audit->record('requested', $actor, $target, ['effective_at' => $effectiveAt->toIso8601String()], $httpRequest, $model->id);

            return $model;
        });

        $this->notifyRequest($transfer->load('fromUser', 'toUser', 'requester'), $token);

        return $transfer;
    }

    // @function accept: Pinoproseso ang accept sa database transaction.
    // @useIn accept: app/Http/Controllers/RootOwnershipController.php
    public function accept(RootTransferRequest $transfer, User $actor, ?Request $request = null): void
    {
        DB::transaction(function () use ($transfer, $actor, $request) {
            $locked = RootTransferRequest::query()->lockForUpdate()->findOrFail($transfer->id);
            abort_unless($locked->status === 'pending' && now()->lte($locked->expires_at), 410, 'This transfer is no longer available.');
            abort_unless($locked->to_user_id === $actor->user_id, 403, 'Only the selected new owner can accept this transfer.');
            $locked->update(['status' => 'accepted', 'accepted_at' => now()]);
            $this->audit->record('accepted', $actor, $actor, [], $request, $locked->id);
        });
    }

    // @function cancel: Kina-cancel ang root transfer sa Root Transfer flow.
    // @useIn cancel: app/Http/Controllers/RootOwnershipController.php
    public function cancel(RootTransferRequest $transfer, ?User $actor, ?string $token, ?Request $request = null): void
    {
        DB::transaction(function () use ($transfer, $actor, $token, $request) {
            $locked = RootTransferRequest::query()->lockForUpdate()->findOrFail($transfer->id);
            abort_unless(in_array($locked->status, ['pending', 'accepted'], true), 410, 'This transfer can no longer be cancelled.');
            $authorizedOwner = $actor?->user_id === $locked->from_user_id && (bool) $actor?->is_root_admin;
            $authorizedToken = $token !== null && hash_equals((string) $locked->cancel_token_hash, hash('sha256', $token));
            abort_unless($authorizedOwner || $authorizedToken, 403);
            $locked->update(['status' => 'cancelled', 'pending_guard' => null, 'cancel_token_hash' => null, 'cancelled_at' => now()]);
            $this->audit->record('cancelled', $actor, $locked->toUser, ['method' => $authorizedToken ? 'one_time_email_token' : 'authenticated'], $request, $locked->id);
            $this->notifyBoth($locked, 'cancelled', 'Root Admin transfer cancelled', 'The pending Root Admin ownership transfer was cancelled.');
        });
    }

    // @function processDue: Pinoproseso ang due sa Root Transfer flow.
    // @useIn processDue: routes/console.php
    public function processDue(): void
    {
        RootTransferRequest::query()->whereIn('status', ['pending', 'accepted'])->get()->each(function (RootTransferRequest $transfer) {
            $days = now()->diffInDays($transfer->effective_at, false);
            if ($days <= 7 && $days > 1 && ! $transfer->reminder_7_sent_at) {
                $this->sendReminder($transfer, 7);
            }
            if ($days <= 1 && $days >= 0 && ! $transfer->reminder_1_sent_at) {
                $this->sendReminder($transfer, 1);
            }
            if (now()->lt($transfer->effective_at)) {
                return;
            }

            if ($transfer->status !== 'accepted') {
                $transfer->update(['status' => 'expired', 'pending_guard' => null, 'cancel_token_hash' => null]);
                $this->audit->record('expired', null, $transfer->toUser, [], null, $transfer->id);
                $this->notifyBoth($transfer, 'expired', 'Root Admin transfer expired', 'The transfer expired because the new owner did not accept it in time.');

                return;
            }

            DB::transaction(function () use ($transfer) {
                $locked = RootTransferRequest::query()->lockForUpdate()->findOrFail($transfer->id);
                if ($locked->status !== 'accepted') {
                    return;
                }
                $revocation = $this->swap->swap($locked->fromUser, $locked->toUser);
                $locked->update(['status' => 'completed', 'pending_guard' => null, 'cancel_token_hash' => null, 'completed_at' => now()]);
                $this->audit->record('completed', null, $locked->toUser, ['old_owner_access_revocation' => $revocation], null, $locked->id);
            });
            $this->notifyBoth($transfer, 'completed', 'Root Admin ownership transferred', 'The Root Admin ownership transfer is complete.');
        });
    }

    // @function notifyRequest: Nagnonotify ang request sa Root Transfer flow.
    // @useIn notifyRequest: RootTransferService::request (app/Services/RootTransferService.php)
    private function notifyRequest(RootTransferRequest $transfer, string $token): void
    {
        $base = $this->details($transfer);
        $base['cancel_url'] = URL::temporarySignedRoute('root-ownership.cancel.show', $transfer->expires_at, ['transfer' => $transfer->id, 'token' => $token]);
        $old = $base + ['subject' => 'Root Admin ownership transfer requested', 'message' => 'A Root Admin ownership transfer was requested from your account.'];
        $new = $base + ['subject' => 'Accept Root Admin ownership transfer', 'message' => 'You were selected as the new Root Admin.', 'accept_url' => URL::temporarySignedRoute('root-ownership.accept.show', $transfer->expires_at, ['transfer' => $transfer->id])];
        $transfer->fromUser->notify(new RootOwnershipNotification('requested', $old));
        $transfer->toUser->notify(new RootOwnershipNotification('requested', $new));
    }

    // @function sendReminder: Ipinapadala ang reminder sa Root Transfer flow.
    // @useIn sendReminder: RootTransferService::processDue (app/Services/RootTransferService.php)
    private function sendReminder(RootTransferRequest $transfer, int $days): void
    {
        $column = $days === 7 ? 'reminder_7_sent_at' : 'reminder_1_sent_at';
        $transfer->update([$column => now()]);
        $this->audit->record('reminder_sent', null, $transfer->toUser, ['days' => $days], null, $transfer->id);
        $this->notifyBoth($transfer, 'reminder_sent', "Root Admin transfer reminder: {$days} day(s)", 'The pending Root Admin transfer is approaching its effective date.');
    }

    // @function notifyBoth: Nagnonotify ang both sa Root Transfer flow.
    // @useIn notifyBoth: RootTransferService::cancel (app/Services/RootTransferService.php)
    private function notifyBoth(RootTransferRequest $transfer, string $event, string $subject, string $message): void
    {
        Notification::send([$transfer->fromUser, $transfer->toUser], new RootOwnershipNotification($event, $this->details($transfer) + compact('subject', 'message')));
    }

    // @function details: Kinukuha ang details result para sa Root Transfer.
    // @useIn details: RootTransferService::notifyRequest (app/Services/RootTransferService.php)
    private function details(RootTransferRequest $transfer): array
    {
        return ['requested_by' => $transfer->requester->email, 'requested_at' => $transfer->created_at?->toIso8601String(), 'ip' => $transfer->request_ip, 'user_agent' => $transfer->request_user_agent, 'effective_at' => $transfer->effective_at->toIso8601String()];
    }
}
