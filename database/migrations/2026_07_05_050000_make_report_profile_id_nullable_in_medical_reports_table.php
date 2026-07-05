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
        Schema::table('medical_reports', function (Blueprint $table) {
            $table->foreignUlid('report_profile_id')->nullable()->change();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('storage_provider')->nullable()->default('azure_blob');
            $table->string('original_file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medical_reports', function (Blueprint $table) {
            $table->dropForeign(['medical_reports_user_id_foreign']);
            $table->dropColumn(['user_id', 'storage_provider', 'original_file_name', 'mime_type', 'file_size']);
            $table->foreignUlid('report_profile_id')->nullable(false)->change();
        });
    }
};
