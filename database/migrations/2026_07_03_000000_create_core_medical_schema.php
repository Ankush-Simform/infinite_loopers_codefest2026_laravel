<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Profiles
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('relation')->nullable();
            $table->string('blood_group', 5)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->decimal('height_cm', 5, 2)->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->string('tags')->nullable();
            $table->string('profile_photo_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Report Categories
        Schema::create('report_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 3. Medical Reports
        Schema::create('medical_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_category_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('title');
            $table->string('report_type');
            $table->string('doctor_name')->nullable();
            $table->string('hospital_name')->nullable();
            $table->date('report_date')->nullable();
            $table->text('file_url');
            $table->string('file_hash');
            $table->string('status');
            $table->timestamps();
            $table->softDeletes();
        });

        // 4. Medical Knowledge
        Schema::create('medical_knowledge', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')
                ->unique()
                ->constrained('medical_reports')
                ->cascadeOnDelete();

            $table->longText('summary');
            $table->string('risk_level')->nullable();
            $table->longText('recommendations')->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->integer('processing_time_ms')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 5. Medical Entities
        Schema::create('medical_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')
                ->constrained('medical_reports')
                ->cascadeOnDelete();

            $table->string('entity_type', 50);
            $table->string('entity_name');
            $table->string('value')->nullable();
            $table->string('unit')->nullable();
            $table->string('reference_range', 100)->nullable();
            $table->string('status')->nullable();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 6. Timeline Events
        Schema::create('timeline_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_id')
                ->nullable()
                ->constrained('medical_reports')
                ->nullOnDelete();

            $table->string('event_type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('event_date');
            $table->integer('importance')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 7. Report Tags
        Schema::create('report_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')
                ->constrained('medical_reports')
                ->cascadeOnDelete();

            $table->string('tag');
            $table->timestamps();
            $table->softDeletes();
        });

        // 8. Emergency Cards
        Schema::create('emergency_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table->string('qr_token')->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_generated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 9. Chat Sessions
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 10. Chat Messages
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_id')
                ->nullable()
                ->constrained('medical_reports')
                ->nullOnDelete();

            $table->string('role');
            $table->longText('content');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['chat_session_id', 'created_at']);
        });

        // 11. Notifications
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('title');
            $table->text('message')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'read_at']);
        });

        // 12. Activity Logs
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 100);
            $table->string('activity_type');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('properties')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_sessions');
        Schema::dropIfExists('emergency_cards');
        Schema::dropIfExists('report_tags');
        Schema::dropIfExists('timeline_events');
        Schema::dropIfExists('medical_entities');
        Schema::dropIfExists('medical_knowledge');
        Schema::dropIfExists('medical_reports');
        Schema::dropIfExists('report_categories');
        Schema::dropIfExists('profiles');
    }
};
