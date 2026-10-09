<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AwsFaceRecognitionService
{
    // @function availability: Kinukuha ang availability result para sa Aws Face Recognition.
    // @useIn availability: AwsFaceRecognitionService::isAvailable (app/Services/AwsFaceRecognitionService.php)
    public function availability(): array
    {
        if (! class_exists(\Aws\Rekognition\RekognitionClient::class)) {
            return [
                'available' => false,
                'message' => 'AWS Rekognition SDK is not installed.',
            ];
        }

        $key = config('services.aws_rekognition.key', env('AWS_ACCESS_KEY_ID'));
        $secret = config('services.aws_rekognition.secret', env('AWS_SECRET_ACCESS_KEY'));
        $region = config('services.aws_rekognition.region', config('services.ses.region', 'us-east-1'));

        if (! $key || ! $secret || ! $region) {
            return [
                'available' => false,
                'message' => 'AWS Rekognition credentials or region are missing.',
            ];
        }

        if (config('services.aws_rekognition.liveness.enabled', false)) {
            $liveness = (new AwsFaceLivenessService)->availability();
            if (! $liveness['available']) {
                return [
                    'available' => false,
                    'message' => $liveness['message'],
                ];
            }
        }

        return [
            'available' => true,
            'message' => 'AWS Rekognition is configured.',
        ];
    }

    // @function isAvailable: Sinusuri kung available para sa Aws Face Recognition.
    // @useIn isAvailable: TODO(verify): walang direct caller na nakita sa static search
    public function isAvailable(): bool
    {
        return (bool) $this->availability()['available'];
    }

    // @function compareBase64WithStoredImage: Kinukuha ang compare base64 with stored image result para sa Aws Face Recognition.
    // @useIn compareBase64WithStoredImage: app/Http/Controllers/Shared/Attendance/AttendanceController.php
    /**
     * @feature     Face Recognition
     * @actor       Shared / Core
     * @flow        Ipinapadala sa AWS CompareFaces ang enrolled source bytes at captured target bytes. Pass kapag similarity >= services.aws_rekognition.similarity_threshold (default 90); fail o null result kapag walang match o may provider error.
     * @uses        resources/js/components/CameraCapture.vue; app/Services/AwsFaceRecognitionService.php: AwsFaceRecognitionService::compareBase64WithStoredImage
     * @related     Attendance, Instructor verification, at online-class face-required flows.
     * @disable     1) Suriin ang Face Recognition callers, pending work, at dependent screens; Needs developer check: i-verify muna ang permitted fallback sa bawat face-required flow bago i-off ang FACE_RECOGNITION_ENABLED.
     * @disable     2) Magdagdag at subukan ng feature-specific server guard sa named actions; panatilihin ang shared route/method para sa ibang feature. Itago pagkatapos ang controls sa `resources/js/components/CameraCapture.vue`.
     * @disable     3) I-check ang affected user flow, reports, pending jobs, at historical read access; huwag burahin ang existing records/files bilang bahagi ng disable.
     * @sideEffects Tumatawag sa AWS CompareFaces; maaaring mag-store ng attendance evidence at verification grant sa caller.
     * @dependsOn   Attendance, Instructor verification, at online-class face-required flows.
     * @performance Needs developer check: sukatin ang request/provider/worker work bago at pagkatapos; UI hide lang ay walang nakumpirmang bilis na dagdag.
     * @dataImpact  Walang data deletion sa nakasaad na disable steps; mananatili ang records/files pero maaaring hindi mabuksan sa hidden UI.
     * @reEnable    1) Ibalik ang server guard/action. 2) Ibalik ang UI controls. 3) I-test ang actor access, dependencies, pending work, at historical data.
     * @editable    Admin System Settings: face switch; threshold ay server config.
     */
    public function compareBase64WithStoredImage(string $capturedDataUrl, string $storedPath): ?array
    {
        if (! class_exists(\Aws\Rekognition\RekognitionClient::class)) {
            Log::warning('AWS Rekognition SDK is not installed.');

            return null;
        }

        if (! Storage::disk('public')->exists($storedPath)) {
            return null;
        }

        $capturedBytes = $this->decodeDataUrl($capturedDataUrl);
        $storedBytes = Storage::disk('public')->get($storedPath);

        if (! $capturedBytes || ! $storedBytes) {
            return null;
        }

        try {
            $credentials = [
                'key' => config('services.aws_rekognition.key', env('AWS_ACCESS_KEY_ID')),
                'secret' => config('services.aws_rekognition.secret', env('AWS_SECRET_ACCESS_KEY')),
            ];
            if (config('services.aws_rekognition.token')) {
                $credentials['token'] = config('services.aws_rekognition.token');
            }

            $client = new \Aws\Rekognition\RekognitionClient([
                'version' => 'latest',
                'region' => config('services.aws_rekognition.region', config('services.ses.region', 'us-east-1')),
                'credentials' => $credentials,
            ]);

            $threshold = (float) config('services.aws_rekognition.similarity_threshold', 90);

            $result = $client->compareFaces([
                'SourceImage' => ['Bytes' => $storedBytes],
                'TargetImage' => ['Bytes' => $capturedBytes],
                'SimilarityThreshold' => $threshold,
            ]);

            $matches = $result->get('FaceMatches') ?? [];
            $similarity = empty($matches) ? 0 : (float) ($matches[0]['Similarity'] ?? 0);

            return [
                'verified' => $similarity >= $threshold,
                'similarity' => $similarity,
                'threshold' => $threshold,
                'provider' => 'aws_rekognition',
            ];
        } catch (\Throwable $e) {
            Log::error('AWS Rekognition compareFaces failed', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    // @function decodeDataUrl: Binubuo ang decode data url string para sa Aws Face Recognition.
    // @useIn decodeDataUrl: AwsFaceRecognitionService::compareBase64WithStoredImage (app/Services/AwsFaceRecognitionService.php)
    private function decodeDataUrl(string $dataUrl): ?string
    {
        $base64 = preg_replace('/^data:[^;]+;base64,/', '', $dataUrl);
        $bytes = base64_decode((string) $base64, true);

        return $bytes === false ? null : $bytes;
    }
}
