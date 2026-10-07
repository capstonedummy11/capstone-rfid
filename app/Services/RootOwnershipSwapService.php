<?php

namespace App\Services;

use App\Models\User;
use App\Services\Auth\AccessRevocationService;
use Illuminate\Support\Facades\DB;

class RootOwnershipSwapService
{
    // @function __construct: Tinatanggap ang dependencies ng Root Ownership Swap sa pagbuo ng object.
    // @useIn __construct: Laravel dependency injection kapag ginagamit ang RootOwnershipSwapService
    public function __construct(private readonly AccessRevocationService $accessRevocation) {}

    // @function swap: Pinoproseso ang swap sa database transaction.
    // @useIn swap: app/Services/RootTransferService.php
    public function swap(User $oldOwner, User $newOwner): array
    {
        return DB::transaction(function () use ($oldOwner, $newOwner): array {
            $lockedOld = User::query()->lockForUpdate()->findOrFail($oldOwner->user_id);
            $lockedNew = User::query()->lockForUpdate()->findOrFail($newOwner->user_id);

            abort_unless($lockedOld->is_root_admin && strtolower((string) $lockedOld->role) === 'admin', 409, 'The current Root Admin changed before completion.');
            abort_unless(strtolower((string) $lockedNew->role) === 'admin' && ! $lockedNew->trashed(), 422, 'The new owner must be an active Admin.');

            User::query()->where('is_root_admin', true)->update(['is_root_admin' => false]);
            $lockedNew->forceFill(['is_root_admin' => true])->save();
            $revocation = $this->accessRevocation->revokeAccess($lockedOld);

            abort_unless(User::query()->where('is_root_admin', true)->count() === 1, 500, 'Root Admin ownership invariant failed.');

            return $revocation->toArray();
        });
    }
}
