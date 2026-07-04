<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Profile;
use App\Models\UserDevice;
use App\Models\Notification;
use App\Models\MedicalReport;
use App\Jobs\SendPushNotificationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;
    protected Profile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'phone' => '+1234567890',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        $this->profile = $this->user->profiles()->create([
            'name' => 'John Doe',
            'relation' => 'self',
        ]);

        $this->token = app(\App\Services\JwtService::class)->generateToken($this->user);
    }

    /**
     * Test registering a device token.
     */
    public function test_device_token_registration(): void
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->postJson('/api/v1/devices', [
                'fcm_token' => 'mock_fcm_token_123',
                'device_name' => 'iPhone 15 Pro',
                'platform' => 'ios',
                'app_version' => '1.0.0',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.fcm_token', 'mock_fcm_token_123');

        $this->assertDatabaseHas('user_devices', [
            'user_id' => $this->user->id,
            'fcm_token' => 'mock_fcm_token_123',
            'platform' => 'ios',
            'is_active' => true,
        ]);
    }

    /**
     * Test duplicate fcm_token overwrites ownership (shared device scenario).
     */
    public function test_device_token_reassignment(): void
    {
        // First user registers
        UserDevice::create([
            'user_id' => $this->user->id,
            'fcm_token' => 'shared_token',
            'device_name' => 'Pixel 8',
            'platform' => 'android',
            'is_active' => true,
        ]);

        // Second user
        $otherUser = User::create([
            'name' => 'Jane Doe',
            'email' => 'janedoe@example.com',
            'phone' => '+1987654321',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        $otherToken = app(\App\Services\JwtService::class)->generateToken($otherUser);

        // Second user registers same token
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $otherToken])
            ->postJson('/api/v1/devices', [
                'fcm_token' => 'shared_token',
                'device_name' => 'Pixel 8',
                'platform' => 'android',
            ]);

        $response->assertOk();

        // Assert record updated to second user and only 1 record exists in DB
        $this->assertDatabaseHas('user_devices', [
            'user_id' => $otherUser->id,
            'fcm_token' => 'shared_token',
        ]);

        $this->assertEquals(1, UserDevice::where('fcm_token', 'shared_token')->count());
    }

    /**
     * Test de-registering a device token.
     */
    public function test_device_token_deregistration(): void
    {
        UserDevice::create([
            'user_id' => $this->user->id,
            'fcm_token' => 'token_to_delete',
            'is_active' => true,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->deleteJson('/api/v1/devices', [
                'fcm_token' => 'token_to_delete',
            ]);

        $response->assertOk();
        $this->assertDatabaseMissing('user_devices', [
            'fcm_token' => 'token_to_delete',
        ]);
    }

    /**
     * Test listing and reading notifications.
     */
    public function test_notification_operations(): void
    {
        // Seed notifications
        $n1 = Notification::create([
            'user_id' => $this->user->id,
            'type' => 'test_type',
            'title' => 'Unread Notification',
            'message' => 'This is unread',
        ]);

        $n2 = Notification::create([
            'user_id' => $this->user->id,
            'type' => 'test_type',
            'title' => 'Read Notification',
            'message' => 'This is read',
            'read_at' => now(),
        ]);

        // 1. Get all notifications
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        // 2. Get unread only
        $unreadResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/v1/notifications?unread_only=true');

        $unreadResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $n1->id);

        // 3. Mark notification as read
        $readResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->patchJson("/api/v1/notifications/{$n1->id}/read");

        $readResponse->assertOk();
        $this->assertNotNull($readResponse->json('data.read_at'));

        $this->assertNotNull($n1->refresh()->read_at);
    }

    /**
     * Test marking all unread notifications as read.
     */
    public function test_mark_all_notifications_as_read(): void
    {
        Notification::create([
            'user_id' => $this->user->id,
            'type' => 'test_type',
            'title' => 'Unread 1',
        ]);

        Notification::create([
            'user_id' => $this->user->id,
            'type' => 'test_type',
            'title' => 'Unread 2',
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->postJson('/api/v1/notifications/read-all');

        $response->assertOk();

        $unreadCount = Notification::where('user_id', $this->user->id)->whereNull('read_at')->count();
        $this->assertEquals(0, $unreadCount);
    }

    /**
     * Test saving staged report triggers database notification & push notification job dispatch.
     */
    public function test_report_save_triggers_notifications(): void
    {
        Queue::fake();

        $uploadId = 'test-staged-upload-uuid';
        $stagedData = [
            'created_at' => now()->timestamp,
            'profile_id' => $this->profile->id,
            'file_url' => 'https://res.cloudinary.com/demo/image/upload/v1570975200/sample.pdf',
            'file_hash' => 'dummy_hash',
            'report_type' => 'pdf',
            'report' => [
                'title' => 'Staged Report Title',
                'report_type' => 'pdf',
                'doctor_name' => 'Dr. Miller',
                'hospital_name' => 'Lab',
                'report_date' => '2026-07-01',
            ],
            'knowledge' => [
                'summary' => 'Summary text',
                'risk_level' => 'Low',
                'recommendations' => ['Drink water'],
                'confidence_score' => 99.0,
            ],
            'entities' => [],
            'tags' => [],
        ];

        Cache::put('temp_upload_' . $uploadId, $stagedData, now()->addDay());

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->postJson("/api/v1/reports/upload/{$uploadId}/save", [
                'profile_id' => $this->profile->id,
                'report' => $stagedData['report'],
                'entities' => [],
                'tags' => [],
            ]);

        $response->assertStatus(201);

        // Assert Notification record is created in database
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => 'report_processed',
            'title' => 'Medical Report Processed',
        ]);

        $notification = Notification::where('user_id', $this->user->id)->first();
        $this->assertNotNull($notification);

        // Assert Push Notification Job is dispatched
        Queue::assertPushed(SendPushNotificationJob::class, function ($job) use ($notification) {
            return $job->notification->id === $notification->id;
        });
    }
}
