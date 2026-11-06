<?php

require __DIR__.'/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/..');
$dotenv->safeLoad();

$source = $argv[1] ?? null;
$target = $argv[2] ?? null;

if (! $source || ! $target) {
    fwrite(STDERR, "Usage: php scripts/test-rekognition.php <source-image> <target-image>\n");
    exit(1);
}

if (! is_file($source) || ! is_readable($source)) {
    fwrite(STDERR, "Source image is missing or unreadable: {$source}\n");
    exit(1);
}

if (! is_file($target) || ! is_readable($target)) {
    fwrite(STDERR, "Target image is missing or unreadable: {$target}\n");
    exit(1);
}

$key = $_ENV['AWS_ACCESS_KEY_ID'] ?? '';
$secret = $_ENV['AWS_SECRET_ACCESS_KEY'] ?? '';
$region = $_ENV['AWS_DEFAULT_REGION'] ?? 'ap-southeast-1';
$threshold = (float) ($_ENV['AWS_REKOGNITION_SIMILARITY_THRESHOLD'] ?? 90);

if (! $key || ! $secret || ! $region) {
    fwrite(STDERR, "AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, or AWS_DEFAULT_REGION is missing in .env\n");
    exit(1);
}

$client = new Aws\Rekognition\RekognitionClient([
    'version' => 'latest',
    'region' => $region,
    'credentials' => [
        'key' => $key,
        'secret' => $secret,
    ],
]);

try {
    $result = $client->compareFaces([
        'SourceImage' => ['Bytes' => file_get_contents($source)],
        'TargetImage' => ['Bytes' => file_get_contents($target)],
        'SimilarityThreshold' => $threshold,
    ]);

    $matches = $result->get('FaceMatches') ?? [];
    $similarity = empty($matches) ? 0 : (float) ($matches[0]['Similarity'] ?? 0);

    echo "AWS Rekognition API call succeeded.\n";
    echo 'Matched: '.($similarity >= $threshold ? 'yes' : 'no')."\n";
    echo "Similarity: {$similarity}\n";
    echo "Threshold: {$threshold}\n";
} catch (Throwable $e) {
    fwrite(STDERR, "AWS Rekognition API call failed.\n");
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}
