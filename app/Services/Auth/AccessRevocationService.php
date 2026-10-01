<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AccessRevocationService
{
    public function revokeAccess(User $user): AccessRevocationResult
    {
        return DB::transaction(function () use ($user): AccessRevocationResult {
            $skippedReasons = [];

            $user->forceFill(['remember_token' => Str::random(60)])->save();
            $sessionsCleared = $this->clearDatabaseSessions($user, $skippedReasons);
            [$passportRevoked, $passportRefreshRevoked] = $this->revokePassportTokens($user, $skippedReasons);
            $sanctumRevoked = $this->revokeSanctumTokens($user, $skippedReasons);

            return new AccessRevocationResult(
                rememberRotated: true,
                sessionsCleared: $sessionsCleared,
                passportRevoked: $passportRevoked,
                passportRefreshRevoked: $passportRefreshRevoked,
                sanctumRevoked: $sanctumRevoked,
                skippedReasons: $skippedReasons,
            );
        });
    }

    protected function clearDatabaseSessions(User $user, array &$skippedReasons): ?int
    {
        if (config('session.driver') !== 'database') {
            $reason = 'Sessions were not force-cleared because the configured session driver is not database. Remember-token rotation remains active; deployments using file or Redis sessions should also enforce an authentication-version check.';
            $skippedReasons[] = $reason;
            Log::warning('User session revocation could not clear server-side sessions.', [
                'user_id' => $user->getKey(),
                'session_driver' => config('session.driver'),
                'fallback' => 'remember_token rotation; authentication-version enforcement when available',
            ]);

            return null;
        }

        $table = (string) config('session.table', 'sessions');
        if (! Schema::hasTable($table)) {
            $skippedReasons[] = "Database session table [{$table}] does not exist.";

            return null;
        }

        return DB::table($table)->where('user_id', $user->getKey())->delete();
    }

    protected function revokePassportTokens(User $user, array &$skippedReasons): array
    {
        if (! Schema::hasTable('oauth_access_tokens')) {
            $skippedReasons[] = 'Passport access-token table is not installed.';

            return [0, 0];
        }

        $accessTokenIds = DB::table('oauth_access_tokens')
            ->where('user_id', $user->getKey())
            ->pluck('id');

        $accessCount = DB::table('oauth_access_tokens')
            ->whereIn('id', $accessTokenIds)
            ->update(['revoked' => true]);

        $refreshCount = 0;
        if (Schema::hasTable('oauth_refresh_tokens')) {
            $refreshCount = DB::table('oauth_refresh_tokens')
                ->whereIn('access_token_id', $accessTokenIds)
                ->update(['revoked' => true]);
        } else {
            $skippedReasons[] = 'Passport refresh-token table is not installed.';
        }

        return [$accessCount, $refreshCount];
    }

    protected function revokeSanctumTokens(User $user, array &$skippedReasons): int
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            $skippedReasons[] = 'Sanctum token table is not installed.';

            return 0;
        }

        return DB::table('personal_access_tokens')
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->getKey())
            ->delete();
    }
}
