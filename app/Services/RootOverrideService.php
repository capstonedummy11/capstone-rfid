<?php

namespace App\Services;

use App\Models\RootOverrideApproval;
use App\Models\RootOverrideRequest;
use App\Models\User;
use App\Notifications\RootOwnershipNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class RootOverrideService
{
    // @function __construct: Tinatanggap ang dependencies ng Root Override sa pagbuo ng object.
    // @useIn __construct: Laravel dependency injection kapag ginagamit ang RootOverrideService
    public function __construct(private readonly RootOwnershipSwapService $swap, private readonly RootAuditService $audit) {}

    // @function request: Pinoproseso ang request sa database transaction.
    // @useIn request: app/Http/Controllers/Shared/RootOwnership/RootOverrideController.php
    public function request(User $actor, User $target, string $reason, Request $httpRequest): RootOverrideRequest
    {
        $override = DB::transaction(function () use ($actor, $target, $reason, $httpRequest) {
            $owner = User::query()->where('is_root_admin', true)->where('role', 'admin')->lockForUpdate()->sole();
            abort_if($actor->is($target) || $target->is($owner), 422, 'Choose a different Admin as the proposed owner.');
            if (RootOverrideRequest::query()->whereNotNull('pending_guard')->lockForUpdate()->exists()
                || \App\Models\RootTransferRequest::query()->whereNotNull('pending_guard')->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['reason' => 'Another emergency override is already active.']);
            }
            $model = RootOverrideRequest::query()->create([
                'requester_id' => $actor->user_id, 'from_user_id' => $owner->user_id, 'to_user_id' => $target->user_id,
                'reason' => $reason, 'status' => 'pending', 'pending_guard' => 'pending',
                'required_approvals' => max(2, (int) config('root_ownership.override_required_approvals', 2)),
                'expires_at' => now()->addHours((int) config('root_ownership.override_approval_window_hours', 72)),
                'request_ip' => $httpRequest->ip(), 'request_user_agent' => $httpRequest->userAgent(),
            ]);
            $this->audit->record('override_requested', $actor, $target, ['reason' => $reason], $httpRequest, null, $model->id);

            return $model;
        });
        $this->notifyParties($override, 'override_requested', 'Emergency Root Admin override requested', 'An emergency ownership override was requested. The current owner may contest it before execution.');

        return $override;
    }

    // @function decide: Pinoproseso ang decide sa database transaction.
    // @useIn decide: app/Http/Controllers/Shared/RootOwnership/RootOverrideController.php
    public function decide(RootOverrideRequest $override, User $actor, string $decision, ?string $comment, Request $request): void
    {
        DB::transaction(function () use ($override, $actor, $decision, $comment, $request) {
            $locked = RootOverrideRequest::query()->lockForUpdate()->findOrFail($override->id);
            abort_unless(in_array($locked->status, ['pending', 'approved'], true) && now()->lt($locked->expires_at), 410, 'This override is no longer active.');
            abort_if($locked->requester_id === $actor->user_id, 403, 'The requester cannot approve or reject their own override.');
            abort_unless($this->isEligibleApprover($actor), 403);
            RootOverrideApproval::query()->create([
                'root_override_request_id' => $locked->id, 'approver_id' => $actor->user_id, 'decision' => $decision,
                'comment' => $comment, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
            ]);
            $this->audit->record($decision === 'approve' ? 'override_approved' : 'override_rejected', $actor, $locked->toUser, ['comment' => $comment], $request, null, $locked->id);
            if ($decision === 'reject') {
                $locked->update(['status' => 'rejected', 'pending_guard' => null, 'rejected_at' => now()]);

                return;
            }
            $count = $locked->approvals()->where('decision', 'approve')->count();
            if ($count >= $locked->required_approvals) {
                $locked->update(['status' => 'approved', 'execute_at' => now()->addHours((int) config('root_ownership.override_delay_hours', 24))]);
            }
        });
        $override->refresh();
        $this->notifyParties($override, 'override_'.$decision, 'Emergency Root Admin override updated', 'An emergency override received an '.$decision.' decision.');
    }

    // @function processDue: Pinoproseso ang due sa Root Override flow.
    // @useIn processDue: routes/console.php
    public function processDue(): void
    {
        RootOverrideRequest::query()->whereIn('status', ['pending', 'approved'])->get()->each(function (RootOverrideRequest $override) {
            if (now()->gte($override->expires_at)) {
                $override->update(['status' => 'expired', 'pending_guard' => null]);
                $this->audit->record('override_expired', null, $override->toUser, [], null, null, $override->id);

                return;
            }
            if ($override->status !== 'approved' || ! $override->execute_at || now()->lt($override->execute_at)) {
                return;
            }
            DB::transaction(function () use ($override) {
                $locked = RootOverrideRequest::query()->lockForUpdate()->findOrFail($override->id);
                if ($locked->status !== 'approved') {
                    return;
                }
                $revocation = $this->swap->swap($locked->fromUser, $locked->toUser);
                $locked->update(['status' => 'completed', 'pending_guard' => null, 'completed_at' => now()]);
                $this->audit->record('override_executed', null, $locked->toUser, ['reason' => $locked->reason, 'old_owner_access_revocation' => $revocation], null, null, $locked->id);
            });
            $this->notifyParties($override, 'override_executed', 'Emergency Root Admin override completed', 'The delayed emergency ownership override has completed.');
        });
    }

    // @function isEligibleApprover: Sinusuri kung eligible approver para sa Root Override.
    // @useIn isEligibleApprover: RootOverrideService::decide (app/Services/RootOverrideService.php)
    private function isEligibleApprover(User $user): bool
    {
        if ($user->trashed() || strtolower((string) $user->role) !== 'admin') {
            return false;
        }
        $emails = array_map('strtolower', config('root_ownership.designated_approver_emails', []));

        return $emails === [] || in_array(strtolower($user->email), $emails, true);
    }

    // @function notifyParties: Nagnonotify ang parties sa Root Override flow.
    // @useIn notifyParties: RootOverrideService::request (app/Services/RootOverrideService.php)
    private function notifyParties(RootOverrideRequest $override, string $event, string $subject, string $message): void
    {
        Notification::send([$override->fromUser, $override->toUser], new RootOwnershipNotification($event, compact('subject', 'message') + [
            'requested_by' => $override->requester->email, 'requested_at' => $override->created_at?->toIso8601String(),
            'ip' => $override->request_ip, 'user_agent' => $override->request_user_agent, 'effective_at' => $override->execute_at?->toIso8601String(),
        ]));
    }
}
