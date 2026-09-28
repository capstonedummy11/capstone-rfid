<?php

namespace App\Services;

use Aws\Rekognition\RekognitionClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AwsFaceLivenessService
{
    private const SESSION_LIFETIME_SECONDS = 180;

    private const TOKEN_LIFETIME_SECONDS = 300;

    private const SUPPORTED_REGIONS = [
        'ap-northeast-1',
        'ap-south-1',
        'ap-southeast-5',
        'ap-southeast-7',
        'eu-west-1',
        'sa-east-1',
        'us-east-1',
        'us-west-2',
    ];

    public function availability(): array
    {
        if (! config('services.aws_rekognition.liveness.enabled', false)) {
            return ['available' => false, 'enabled' => false, 'message' => 'AWS Face Liveness is disabled.'];
        }

        $region = (string) config('services.aws_rekognition.liveness.region');
        $identityPoolId = (string) config('services.aws_rekognition.liveness.identity_pool_id');

        if (! class_exists(RekognitionClient::class)) {
            return ['available' => false, 'enabled' => true, 'message' => 'AWS Rekognition SDK is not installed.'];
        }

        if (! config('services.aws_rekognition.key') || ! config('services.aws_rekognition.secret')) {
            return ['available' => false, 'enabled' => true, 'message' => 'AWS backend credentials are missing.'];
        }

        if (! in_array($region, self::SUPPORTED_REGIONS, true)) {
            return ['available' => false, 'enabled' => true, 'message' => "AWS Face Liveness is not supported in region {$region}."];
        }

        if ($identityPoolId === '') {
            return ['available' => false, 'enabled' => true, 'message' => 'The Cognito Identity Pool ID is missing.'];
        }

        return [
            'available' => true,
            'enabled' => true,
            'message' => 'AWS Face Liveness is configured.',
            'region' => $region,
            'identity_pool_id' => $identityPoolId,
        ];
    }

    public function createSession(Request $request, string $purpose, string $subjectKey): array
    {
        $availability = $this->availability();
        if (! $availability['available']) {
            return $availability;
        }

        $result = $this->client()->createFaceLivenessSession([
            'ClientRequestToken' => str_replace('-', '', (string) Str::uuid()),
            'Settings' => [
                'AuditImagesLimit' => 0,
                'ChallengePreferences' => [[
                    'Type' => 'FaceMovementAndLightChallenge',
                ]],
            ],
        ]);

        $sessionId = (string) $result->get('SessionId');
        $sessions = $request->session()->get('face_liveness_sessions', []);
        $sessions[$sessionId] = [
            'purpose' => $purpose,
            'subject_key' => $subjectKey,
            'user_id' => $request->user()?->getAuthIdentifier(),
            'expires_at' => now()->addSeconds(self::SESSION_LIFETIME_SECONDS)->timestamp,
            'consumed' => false,
        ];
        $request->session()->put('face_liveness_sessions', $sessions);

        return [
            'available' => true,
            'enabled' => true,
            'session_id' => $sessionId,
            'region' => $availability['region'],
            'identity_pool_id' => $availability['identity_pool_id'],
        ];
    }

    public function completeSession(Request $request, string $sessionId): array
    {
        $sessions = $request->session()->get('face_liveness_sessions', []);
        $boundSession = $sessions[$sessionId] ?? null;

        if (! is_array($boundSession)
            || ($boundSession['consumed'] ?? true)
            || now()->timestamp > (int) ($boundSession['expires_at'] ?? 0)
            || ($boundSession['user_id'] ?? null) !== $request->user()?->getAuthIdentifier()) {
            return ['ok' => false, 'message' => 'This liveness session is invalid or expired.'];
        }

        $result = $this->client()->getFaceLivenessSessionResults(['SessionId' => $sessionId]);
        $status = (string) $result->get('Status');
        $confidence = (float) $result->get('Confidence');
        $threshold = (float) config('services.aws_rekognition.liveness.confidence_threshold', 90);
        $referenceImage = $result->get('ReferenceImage');
        $bytes = is_array($referenceImage) ? (string) ($referenceImage['Bytes'] ?? '') : '';

        $sessions[$sessionId]['consumed'] = true;
        $request->session()->put('face_liveness_sessions', $sessions);

        if ($status !== 'SUCCEEDED' || $confidence < $threshold || $bytes === '') {
            return [
                'ok' => false,
                'message' => 'Liveness verification did not pass. Please try again.',
                'confidence' => $confidence,
                'threshold' => $threshold,
            ];
        }

        $token = Str::random(64);
        $path = "face-liveness/{$token}.jpg";
        Storage::disk('local')->put($path, $bytes);

        $tokens = $request->session()->get('face_liveness_tokens', []);
        $tokens[hash('sha256', $token)] = [
            'purpose' => $boundSession['purpose'],
            'subject_key' => $boundSession['subject_key'],
            'path' => $path,
            'expires_at' => now()->addSeconds(self::TOKEN_LIFETIME_SECONDS)->timestamp,
        ];
        $request->session()->put('face_liveness_tokens', $tokens);

        return [
            'ok' => true,
            'token' => $token,
            'confidence' => $confidence,
            'threshold' => $threshold,
        ];
    }

    public function consumeReferenceImage(Request $request, string $token, string $purpose, string $subjectKey): ?string
    {
        $tokens = $request->session()->get('face_liveness_tokens', []);
        $tokenHash = hash('sha256', $token);
        $boundToken = $tokens[$tokenHash] ?? null;
        unset($tokens[$tokenHash]);
        $request->session()->put('face_liveness_tokens', $tokens);

        if (! is_array($boundToken)
            || ! hash_equals((string) ($boundToken['purpose'] ?? ''), $purpose)
            || ! hash_equals((string) ($boundToken['subject_key'] ?? ''), $subjectKey)
            || now()->timestamp > (int) ($boundToken['expires_at'] ?? 0)) {
            return null;
        }

        $path = (string) ($boundToken['path'] ?? '');
        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        $bytes = Storage::disk('local')->get($path);
        Storage::disk('local')->delete($path);

        return 'data:image/jpeg;base64,'.base64_encode($bytes);
    }

    private function client(): RekognitionClient
    {
        $credentials = [
            'key' => config('services.aws_rekognition.key'),
            'secret' => config('services.aws_rekognition.secret'),
        ];

        if (config('services.aws_rekognition.token')) {
            $credentials['token'] = config('services.aws_rekognition.token');
        }

        return new RekognitionClient([
            'version' => 'latest',
            'region' => config('services.aws_rekognition.liveness.region'),
            'credentials' => $credentials,
        ]);
    }
}
