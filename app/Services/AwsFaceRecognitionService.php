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
    // @useIn compareBase64WithStoredImage: app/Http/Controllers/AttendanceController.php
    /**
     * @feature   Face Recognition
     * @actor     Shared / Core
     * @flow      Ipinapadala sa AWS CompareFaces ang enrolled source bytes at captured target bytes. Pass kapag similarity >= services.aws_rekognition.similarity_threshold (default 90); fail o null result kapag walang match o may provider error.
     * @uses      resources/js/components/CameraCapture.vue; app/Services/AwsFaceRecognitionService.php: AwsFaceRecognitionService::compareBase64WithStoredImage
     * @related   Authentication, Attendance, Reports
     * @disable   1) I-off ang SystemSetting::FACE_RECOGNITION_ENABLED sa app/Models/SystemSetting.php.
     * @disable   2) Itago ang face action sa resources/js/pages/AttendanceControlPanel.vue.
     * @disable   3) Alisin ang AttendanceController::studentFaceCheck at OnlineClassController::join face calls bago ihinto ang app/Services/AwsFaceRecognitionService.php: compareBase64WithStoredImage. Side effect: kailangang sundin ang documented fallback o titigil ang face-required flows.
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
