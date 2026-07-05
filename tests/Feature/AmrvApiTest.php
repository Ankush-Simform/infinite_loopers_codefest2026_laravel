<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\ProfileRelation;
use App\Models\ReportCategory;
use App\Models\User;
use App\Services\AzureBlobService;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AmrvApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected ReportProfile $profile;

    protected ReportCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed some categories
        $this->category = ReportCategory::create([
            'name' => 'Blood Test',
            'slug' => 'blood-test',
            'description' => 'Blood Test Reports',
        ]);

        // Create a default verified user
        $this->user = User::create([
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'phone' => '+1234567890',
            'password' => Hash::make('password'),
        ]);
        $this->user->markEmailAsVerified();

        // Create user profile
        $this->profile = $this->user->reportProfiles()->create([
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'relation' => ProfileRelation::SELF->value,
            'blood_group' => 'A+',
            'date_of_birth' => '1995-10-15',
            'gender' => Gender::MALE->value,
            'height_cm' => 178.50,
            'weight_kg' => 72.40,
        ]);
    }

    /**
     * Test user registration and login flows.
     */
    public function test_authentication_flow(): void
    {
        Notification::fake();

        // 1. Test Registration
        $registerResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'janedoe@example.com',
            'phone' => '+1987654321',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $registerResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('data.token')
            ->assertJsonMissingPath('data.verification_token')
            ->assertJsonPath('data.verification_required', false);

        $registeredUser = User::where('email', 'janedoe@example.com')->firstOrFail();
        Notification::assertNotSentTo($registeredUser, VerifyEmailWithJwt::class);

        $this->assertTrue($registeredUser->refresh()->hasVerifiedEmail());

        $verifiedLoginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'janedoe@example.com',
            'password' => 'Password123!',
        ]);

        $verifiedLoginResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token']]);

        // 2. Test Login
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'johndoe@example.com',
            'password' => 'password',
        ]);

        $loginResponse->assertOk()
            ->assertJsonPath('success', true);

        // 3. Test Me
        $token = $loginResponse->json('data.token');
        $meResponse = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/v1/auth/me');

        $meResponse->assertOk()
            ->assertJsonPath('data.email', 'johndoe@example.com');
    }

    /**
     * Test plural profiles resource CRUD.
     */
    public function test_profiles_crud(): void
    {
        $token = app(JwtService::class)->generateToken($this->user);

        // 1. List profiles
        $listResponse = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/v1/report-profiles');
        $listResponse->assertOk()
            ->assertJsonCount(1, 'data');

        // 2. Create family profile
        $createResponse = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/report-profiles', [
                'name' => 'Spouse Doe',
                'relation' => 'family',
                'email' => 'spouse@example.com',
            ]);
        $createResponse->assertStatus(201);
        $spouseProfileId = $createResponse->json('data.id');

        // 3. Get profile details
        $showResponse = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/v1/report-profiles/'.$spouseProfileId);
        $showResponse->assertOk()
            ->assertJsonPath('data.name', 'Spouse Doe');

        // 4. Update profile details
        $updateResponse = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/report-profiles/'.$spouseProfileId, [
                '_method' => 'PUT',
                'name' => 'Spouse Doe Updated',
            ]);
        $updateResponse->assertOk()
            ->assertJsonPath('data.name', 'Spouse Doe Updated');

        // 5. Delete profile
        $deleteResponse = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->deleteJson('/api/v1/report-profiles/'.$spouseProfileId);
        $deleteResponse->assertOk();
    }

    /**
     * Test home dashboard aggregation.
     */
    public function test_home_dashboard(): void
    {
        $token = app(JwtService::class)->generateToken($this->user);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'profile',
                    'stats' => ['total_reports', 'latest_report_date', 'total_chats'],
                    'latest_reports',
                    'latest_timeline',
                    'recent_chat',
                ],
            ]);
    }

    /**
     * Test categories listing.
     */
    public function test_categories_listing(): void
    {
        $token = app(JwtService::class)->generateToken($this->user);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/v1/categories');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /**
     * Test staged reports review-and-save workflow.
     */
    public function test_staged_reports_review_workflow(): void
    {
        $token = app(JwtService::class)->generateToken($this->user);
        Storage::fake('public');

        $this->mock(AzureBlobService::class, function ($mock) {
            $mock->shouldReceive('uploadFile')
                ->andReturn([
                    'url' => 'https://amrvblobstorage.blob.core.windows.net/amrv-container/staging/sample.pdf',
                    'public_id' => 'staging/sample.pdf',
                    'format' => 'pdf',
                    'bytes' => 500000,
                ]);
        });

        $file = UploadedFile::fake()->create('report.pdf', 500, 'application/pdf');

        // 1. Stage 1: Upload File
        $uploadResponse = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/reports/upload', [
                'report_profile_id' => $this->profile->id,
                'file' => $file,
            ]);

        $uploadResponse->assertStatus(201)
            ->assertJsonPath('success', true);

        $uploadId = $uploadResponse->json('upload_id');

        // 2. Stage 2: Check Status
        $statusResponse = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/v1/reports/upload/'.$uploadId.'/status');
        $statusResponse->assertOk();

        // 3. Stage 3: Get Review data
        $reviewResponse = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/v1/reports/upload/'.$uploadId.'/review');
        $reviewResponse->assertOk()
            ->assertJsonPath('upload_id', $uploadId);

        // 4. Stage 4: Save report
        $saveResponse = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/reports/upload/'.$uploadId.'/save', [
                'report_profile_id' => $this->profile->id,
                'report' => [
                    'title' => 'My Reviewed Staged Blood Report',
                    'report_type' => 'pdf',
                    'doctor_name' => 'Dr. Andrew Miller',
                    'hospital_name' => 'Central Health Laboratory',
                    'report_date' => '2026-07-01',
                ],
                'entities' => [
                    [
                        'entity_type' => 'vital',
                        'entity_name' => 'Systolic Blood Pressure',
                        'value' => '120',
                        'unit' => 'mmHg',
                    ],
                ],
                'tags' => ['blood-test'],
            ]);

        $saveResponse->assertStatus(201)
            ->assertJsonPath('success', true);

        $reportId = $saveResponse->json('report_id');

        // Assert report exists in DB
        $this->assertDatabaseHas('medical_reports', [
            'id' => $reportId,
            'title' => 'My Reviewed Staged Blood Report',
        ]);
    }

    /**
     * Test timeline events operations.
     */
    public function test_timeline_crud(): void
    {
        $token = app(JwtService::class)->generateToken($this->user);

        // 1. Create Event
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/timelines', [
                'report_profile_id' => $this->profile->id,
                'event_type' => 'checkup',
                'title' => 'Annual Health Checkup',
                'description' => 'General physical checkup',
                'event_date' => '2026-06-10',
                'importance' => 2,
            ]);

        $response->assertStatus(201);
        $eventId = $response->json('data.id');

        // 2. List & search
        $list = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/v1/timelines?search=Annual');
        $list->assertOk()->assertJsonCount(1, 'data');

        // 3. Delete
        $delete = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->deleteJson('/api/v1/timelines/'.$eventId);
        $delete->assertOk();
    }

    /**
     * Test Chat CRUD and message sending (Mock AI responses).
     */
    public function test_chat_crud(): void
    {
        $token = app(JwtService::class)->generateToken($this->user);

        // 1. Create Chat Session
        $sessionResponse = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/chats', [
                'title' => 'My Doctor Chat',
            ]);

        $sessionResponse->assertStatus(201);
        $sessionId = $sessionResponse->json('data.id');

        // 2. Rename session
        $renameResponse = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->putJson('/api/v1/chats/'.$sessionId, [
                'title' => 'My Renamed Chat',
            ]);

        $renameResponse->assertOk()
            ->assertJsonPath('data.title', 'My Renamed Chat');

        // 3. Send message
        $messageResponse = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->post('/api/v1/chats/'.$sessionId.'/messages', [
                'content' => 'hello',
            ]);

        $messageResponse->assertOk();
        $this->assertNotEmpty($messageResponse->streamedContent());

        // Assert database records
        $this->assertDatabaseHas('chat_messages', [
            'chat_session_id' => $sessionId,
            'role' => 'user',
            'content' => 'hello',
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'chat_session_id' => $sessionId,
            'role' => 'assistant',
        ]);

        // 3.1 Verify messages retrieval includes correct schema keys
        $listMessagesResponse = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/v1/chats/'.$sessionId.'/messages');

        $listMessagesResponse->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'sender',
                        'message',
                        'created_at',
                        'attachments',
                    ],
                ],
            ]);

        // Verify index list uses Resource structure
        $listSessionsResponse = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/v1/chats');
        $listSessionsResponse->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'title',
                        'last_message_at',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);

        // 4. Delete Chat Session
        $deleteResponse = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->deleteJson('/api/v1/chats/'.$sessionId);

        $deleteResponse->assertOk();
    }

    public function test_new_profile_and_user_edit_routes(): void
    {
        // 1. Create a user and check "self" profile exists
        $user = User::create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'password' => Hash::make('password'),
        ]);

        $token = app(JwtService::class)->generateToken($user);

        // Ensure "self" profile exists via ensureSelfProfileExists logic in login
        $this->postJson('/api/v1/auth/login', [
            'email' => 'original@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->assertDatabaseHas('report_profiles', [
            'user_id' => $user->id,
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'relation' => ProfileRelation::SELF->value,
        ]);

        // 2. Test GET profiles/enums
        $enumsResponse = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/v1/report-profiles/enums');

        $enumsResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['relations', 'genders']]);

        // 3. Test PUT auth/me to update user profile and sync with "self" profile
        $updateResponse = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->putJson('/api/v1/auth/me', [
                'name' => 'Updated Name',
                'phone' => '+111111111',
                'emergency_contact_name' => 'Emergency Name',
                'emergency_contact_phone' => '+222222222',
            ]);

        $updateResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.phone', '+111111111')
            ->assertJsonPath('data.emergency_contact_name', 'Emergency Name')
            ->assertJsonPath('data.emergency_contact_phone', '+222222222');

        // Assert User model is updated
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'phone' => '+111111111',
            'emergency_contact_name' => 'Emergency Name',
            'emergency_contact_phone' => '+222222222',
        ]);

        // Assert Profile model is synced
        $this->assertDatabaseHas('report_profiles', [
            'user_id' => $user->id,
            'name' => 'Updated Name',
            'relation' => ProfileRelation::SELF->value,
        ]);
    }
}
