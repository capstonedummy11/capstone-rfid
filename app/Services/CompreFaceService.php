<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CompreFaceService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.compreface.url', 'http://localhost:8000'), '/');
        $this->apiKey = (string) config('services.compreface.api_key', '');
    }

    /**
     * Enroll a face image for a subject (student_number is used as the subject name).
     * Returns the CompreFace image_id on success, null on failure.
     */
    public function enrollFace(string $subject, string $filePath): ?string
    {
        if (!$this->apiKey) {
            return null;
        }

        try {
            $response = Http::withHeaders(['x-api-key' => $this->apiKey])
                ->attach('file', file_get_contents($filePath), basename($filePath))
                ->post("{$this->baseUrl}/api/v1/recognition/faces?subject=" . urlencode($subject));

            if ($response->successful()) {
                return $response->json('image_id');
            }

            Log::warning('CompreFace enrollFace failed', [
                'subject' => $subject,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('CompreFace enrollFace exception', ['message' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Recognize who is in the image (base64 data URL from the webcam capture).
     * Returns ['subject' => 'student_number', 'similarity' => 0.98] or null if no match / error.
     */
    public function recognizeBase64(string $base64DataUrl): ?array
    {
        if (!$this->apiKey) {
            return null;
        }

        // Strip the data:image/jpeg;base64, prefix if present
        $base64 = preg_replace('/^data:[^;]+;base64,/', '', $base64DataUrl);

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/api/v1/recognition/recognize", [
                'file' => $base64,
            ]);

            if (!$response->successful()) {
                Log::warning('CompreFace recognize failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $results = $response->json('result') ?? [];
            if (empty($results)) {
                return null;
            }

            // Take the best match from the first detected face
            $subjects = $results[0]['subjects'] ?? [];
            if (empty($subjects)) {
                return null; // Face detected but no match in collection
            }

            $best = $subjects[0];
            return [
                'subject'    => $best['subject'],
                'similarity' => (float) ($best['similarity'] ?? 0),
            ];
        } catch (\Throwable $e) {
            Log::error('CompreFace recognize exception', ['message' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Delete all enrolled faces for a subject.
     * Called when a student is deleted or their face images are cleared.
     */
    public function deleteSubject(string $subject): bool
    {
        if (!$this->apiKey) {
            return false;
        }

        try {
            $response = Http::withHeaders(['x-api-key' => $this->apiKey])
                ->delete("{$this->baseUrl}/api/v1/recognition/subjects/" . urlencode($subject));

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('CompreFace deleteSubject exception', ['message' => $e->getMessage()]);
        }

        return false;
    }
}
