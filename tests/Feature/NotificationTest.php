<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\ReportProfile;
use App\Models\UserDevice;
use App\Models\Notification;
use App\Models\MedicalReport;
use App\Jobs\SendPushNotificationJob;
use App\Jobs\ProcessMedicalReportJob;
use App\Events\ReportUploaded;
use App\Events\OcrStarted;
use App\Events\OcrCompleted;
use App\Events\AiProcessing;
use App\Events\ReportProcessingCompleted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;
    protected ReportProfile $profile;

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

        $this->profile = $this->user->reportProfiles()->create([
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
            'report_profile_id' => $this->profile->id,
            'file_url' => 'https://amrvblobstorage.blob.core.windows.net/amrv-container/staging/sample.pdf',
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
                'report_profile_id' => $this->profile->id,
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

    /**
     * Test the full asynchronous report processing flow (WebSockets, Queues, Webhooks).
     */
    public function test_asynchronous_report_processing_flow(): void
    {
        Queue::fake([ProcessMedicalReportJob::class, SendPushNotificationJob::class]);
        Event::fake([
            ReportUploaded::class,
            OcrStarted::class,
            OcrCompleted::class,
            AiProcessing::class,
            ReportProcessingCompleted::class
        ]);

        // Mock AzureBlobService
        $this->mock(\App\Services\AzureBlobService::class, function ($mock) {
            $mock->shouldReceive('uploadFile')
                ->andReturn([
                    'url' => 'https://amrvblobstorage.blob.core.windows.net/amrv-container/medical_reports/sample.pdf',
                    'public_id' => 'medical_reports/sample.pdf',
                    'format' => 'pdf',
                    'bytes' => 500000,
                ]);
        });

        $file = UploadedFile::fake()->create('report.pdf', 500, 'application/pdf');

        // 1. Trigger POST /v1/reports
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->postJson('/api/v1/reports', [
                'report_profile_id' => $this->profile->id,
                'title' => 'Monthly Checkup Report',
                'file' => $file,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'uploaded');

        $reportId = $response->json('report_id');

        // Assert report exists in database with UPLOADED status
        $this->assertDatabaseHas('medical_reports', [
            'id' => $reportId,
            'status' => \App\Enums\ReportStatus::UPLOADED->value,
            'file_url' => 'https://amrvblobstorage.blob.core.windows.net/amrv-container/medical_reports/sample.pdf',
        ]);

        // Assert ReportUploaded event was broadcasted
        Event::assertDispatched(ReportUploaded::class, function ($event) use ($reportId) {
            return $event->report->id === $reportId;
        });

        // Assert ProcessMedicalReportJob was queued
        Queue::assertPushed(ProcessMedicalReportJob::class, function ($job) use ($reportId) {
            return $job->reportId === $reportId;
        });

        // 2. Simulate Webhook call from the ML service
        $webhookResponse = $this->postJson('/api/webhooks/report-processing-complete', [
            'report_id' => $reportId,
            'summary' => 'This is a complete AI generated summary of the report.',
            'report_type' => 'blood_test',
            'extracted_text' => 'Some raw extracted ocr text.',
            'risk_level' => 'Low',
            'confidence_score' => 95.5,
            'recommendations' => ['Drink more water', 'Schedule follow up'],
            'medical_entities' => [
                [
                    'entity_type' => 'vital',
                    'entity_name' => 'Systolic Blood Pressure',
                    'value' => '120',
                    'unit' => 'mmHg',
                ]
            ],
        ]);

        $webhookResponse->assertOk()
            ->assertJsonPath('success', true);

        // Assert report status updated to COMPLETED in database
        $this->assertDatabaseHas('medical_reports', [
            'id' => $reportId,
            'status' => \App\Enums\ReportStatus::COMPLETED->value,
        ]);

        // Assert knowledge was stored
        $this->assertDatabaseHas('medical_knowledge', [
            'report_id' => $reportId,
            'summary' => 'This is a complete AI generated summary of the report.',
            'risk_level' => 'Low',
        ]);

        // Assert entities were stored
        $this->assertDatabaseHas('medical_entities', [
            'report_id' => $reportId,
            'entity_name' => 'Systolic Blood Pressure',
            'value' => '120',
        ]);

        // Assert ReportProcessingCompleted event was broadcasted
        Event::assertDispatched(ReportProcessingCompleted::class, function ($event) use ($reportId) {
            return $event->report->id === $reportId;
        });

        // Assert Notification record is created in database
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => 'report_processed',
            'title' => 'Medical Report Processed',
        ]);

        $notification = Notification::where('user_id', $this->user->id)
            ->where('type', 'report_processed')
            ->first();
        $this->assertNotNull($notification);

        // Assert Push Notification Job is dispatched
        Queue::assertPushed(SendPushNotificationJob::class, function ($job) use ($notification) {
            return $job->notification->id === $notification->id;
        });
    }

    /**
     * Test secure report file proxy download.
     */
    public function test_download_report_file(): void
    {
        $category = \App\Models\ReportCategory::create([
            'name' => 'Blood Test',
            'slug' => 'blood-test',
        ]);

        $report = MedicalReport::create([
            'report_profile_id' => $this->profile->id,
            'report_category_id' => $category->id,
            'title' => 'Secure Blood Report',
            'report_type' => 'pdf',
            'file_url' => 'https://amrvblobstorage.blob.core.windows.net/amrv-container/medical_reports/secure.pdf',
            'file_hash' => 'hash123',
            'status' => \App\Enums\ReportStatus::COMPLETED,
        ]);

        $this->mock(\App\Services\AzureBlobService::class, function ($mock) {
            $mock->shouldReceive('getFile')
                ->with('medical_reports/secure.pdf')
                ->once()
                ->andReturn([
                    'content' => 'fake-pdf-content-stream-bytes',
                    'mime_type' => 'application/pdf',
                ]);
        });

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->get("/api/v1/reports/{$report->id}/file");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="secure.pdf"');

        $this->assertEquals('fake-pdf-content-stream-bytes', $response->getContent());
    }

    /**
     * Test secure report file proxy download using token in query parameter.
     */
    public function test_download_report_file_using_query_token(): void
    {
        $category = \App\Models\ReportCategory::create([
            'name' => 'Blood Test Query',
            'slug' => 'blood-test-query',
        ]);

        $report = MedicalReport::create([
            'report_profile_id' => $this->profile->id,
            'report_category_id' => $category->id,
            'title' => 'Query Token Blood Report',
            'report_type' => 'pdf',
            'file_url' => 'https://amrvblobstorage.blob.core.windows.net/amrv-container/medical_reports/secure_query.pdf',
            'file_hash' => 'hash123_query',
            'status' => \App\Enums\ReportStatus::COMPLETED,
        ]);

        $this->mock(\App\Services\AzureBlobService::class, function ($mock) {
            $mock->shouldReceive('getFile')
                ->with('medical_reports/secure_query.pdf')
                ->once()
                ->andReturn([
                    'content' => 'fake-query-pdf-content-stream-bytes',
                    'mime_type' => 'application/pdf',
                ]);
        });

        // Make request without Authorization header but passing ?token= query parameter
        $response = $this->get("/api/v1/reports/{$report->id}/file?token=" . $this->token);

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="secure_query.pdf"');

        $this->assertEquals('fake-query-pdf-content-stream-bytes', $response->getContent());
    }
}
