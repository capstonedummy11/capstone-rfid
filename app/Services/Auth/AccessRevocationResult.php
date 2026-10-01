<?php

namespace App\Services\Auth;

final readonly class AccessRevocationResult
{
    public function __construct(
        public bool $rememberRotated,
        public ?int $sessionsCleared,
        public int $passportRevoked,
        public int $passportRefreshRevoked,
        public int $sanctumRevoked,
        public array $skippedReasons,
    ) {}

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
