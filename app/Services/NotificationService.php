<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendPushNotificationJob;
use App\Models\Notification;
use App\Models\User;

final class NotificationService
{
    /**
     * Store and deliver a notification (both in-app and push channels).
     */
    public function send(User $user, string $type, string $title, ?string $message = null, array $data = []): Notification
    {
        // 1. Save to database (becomes available for in-app UI)
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);

        // 2. Dispatch the background push notification job
        SendPushNotificationJob::dispatch($notification);

        return $notification;
    }
}
