<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AwsFaceRecognitionService
{
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
            $client = new \Aws\Rekognition\RekognitionClient([
                'version' => 'latest',
                'region' => config('services.aws_rekognition.region', config('services.ses.region', 'us-east-1')),
                'credentials' => [
                    'key' => config('services.aws_rekognition.key', env('AWS_ACCESS_KEY_ID')),
                    'secret' => config('services.aws_rekognition.secret', env('AWS_SECRET_ACCESS_KEY')),
                ],
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

    private function decodeDataUrl(string $dataUrl): ?string
    {
        $base64 = preg_replace('/^data:[^;]+;base64,/', '', $dataUrl);
        $bytes = base64_decode((string) $base64, true);

        return $bytes === false ? null : $bytes;
    }
}
