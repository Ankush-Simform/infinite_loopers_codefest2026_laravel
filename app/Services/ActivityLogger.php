<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ActivityType;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;

class ActivityLogger
{
    /**
     * Log user activity.
     *
     * @param  array<string, mixed>|null  $payload  Raw external payload to preserve for audit (e.g. an AI webhook response)
     */
    public static function log(
        int|string|null $userId,
        ?string $reportId,
        ?string $reportProfileId,
        string $action,
        ?string $ip = null,
        ?string $userAgent = null,
        ?array $payload = null
    ): void {
        try {
            ActivityLog::create([
                'user_id' => $userId ? (string) $userId : null,
                'method' => 'SYSTEM',
                'activity_type' => ActivityType::API,
                'subject_type' => 'medical_reports',
                'subject_id' => $reportId,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'properties' => [
                    'action' => $action,
                    'report_profile_id' => $reportProfileId,
                ],
                'payload' => $payload,
            ]);
        } catch (\Throwable $e) {
            Log::error('ActivityLogger: failed to log activity', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
