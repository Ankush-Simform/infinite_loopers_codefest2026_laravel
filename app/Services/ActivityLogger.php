<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use App\Enums\ActivityType;

class ActivityLogger
{
    /**
     * Log user activity.
     */
    public static function log(
        int|string|null $userId,
        ?string $reportId,
        ?string $reportProfileId,
        string $action,
        ?string $ip = null,
        ?string $userAgent = null
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
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('ActivityLogger: failed to log activity', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
