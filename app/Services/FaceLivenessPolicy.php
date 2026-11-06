<?php

namespace App\Services;

class FaceLivenessPolicy
{
    public const STUDENT_ROLE = 'student';

    public const INSTRUCTOR_ROLE = 'instructor';

    private const DEFAULT_THRESHOLDS = [
        self::STUDENT_ROLE => 60.0,
        self::INSTRUCTOR_ROLE => 70.0,
    ];

    public function thresholdForRole(string $role): float
    {
        $normalizedRole = strtolower(trim($role));
        $default = self::DEFAULT_THRESHOLDS[$normalizedRole] ?? self::DEFAULT_THRESHOLDS[self::INSTRUCTOR_ROLE];
        $configured = config("services.aws_rekognition.liveness.minimum_confidence.{$normalizedRole}");

        if (! is_numeric($configured)) {
            return $default;
        }

        $threshold = (float) $configured;

        return $threshold >= 0 && $threshold <= 100 ? $threshold : $default;
    }
}
