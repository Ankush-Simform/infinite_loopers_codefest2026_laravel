<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Notification;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly Notification $notification
    ) {}

    /**
     * Execute the job.
     */
    public function handle(FcmService $fcmService): void
    {
        $user = $this->notification->user;
        if (! $user) {
            Log::warning('SendPushNotificationJob aborted: Notification has no associated user.', [
                'notification_id' => $this->notification->id,
            ]);

            return;
        }

        // Get active devices of this user
        $devices = $user->devices()->where('is_active', true)->get();

        if ($devices->isEmpty()) {
            Log::info('SendPushNotificationJob: No active devices registered for user.', [
                'user_id' => $user->id,
                'notification_id' => $this->notification->id,
            ]);

            return;
        }

        $dataPayload = $this->notification->data ?? [];
        $dataPayload['notification_id'] = (string) $this->notification->id;
        $dataPayload['type'] = $this->notification->type;

        foreach ($devices as $device) {
            Log::info('Attempting to send push notification to device', [
                'device_id' => $device->id,
                'user_id' => $user->id,
            ]);

            $success = $fcmService->sendNotification(
                $device->fcm_token,
                $this->notification->title,
                $this->notification->message,
                $dataPayload
            );

            if (! $success) {
                // Token is unregistered or invalid, deactivate it
                Log::info('Deactivating invalid or expired FCM token', [
                    'device_id' => $device->id,
                    'fcm_token' => substr($device->fcm_token, 0, 15).'...',
                ]);
                $device->update(['is_active' => false]);
            }
        }
    }
}
