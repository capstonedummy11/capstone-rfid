<?php

namespace App\Services\Auth;

final readonly class AccessRevocationResult
{
    // @function __construct: Tinatanggap ang dependencies ng Access Revocation Result sa pagbuo ng object.
    // @useIn __construct: Laravel dependency injection kapag ginagamit ang AccessRevocationResult
    public function __construct(
        public bool $rememberRotated,
        public ?int $sessionsCleared,
        public int $passportRevoked,
        public int $passportRefreshRevoked,
        public int $sanctumRevoked,
        public array $skippedReasons,
    ) {}

    // @function toArray: Kinukuha ang to array result para sa Access Revocation Result.
    // @useIn toArray: TODO(verify): walang direct caller na nakita sa static search
    public function toArray(): array
    {
        return [
            'remember_rotated' => $this->rememberRotated,
            'sessions_cleared' => $this->sessionsCleared,
            'passport_revoked' => $this->passportRevoked,
            'passport_refresh_revoked' => $this->passportRefreshRevoked,
            'sanctum_revoked' => $this->sanctumRevoked,
            'skipped_reasons' => $this->skippedReasons,
        ];
    }
}
