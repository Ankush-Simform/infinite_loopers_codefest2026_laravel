<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Atomic, race-safe counter for reference_id — nextval() is guaranteed unique
        // even under concurrent report uploads, unlike a max()+1 read-then-write.
        DB::statement('CREATE SEQUENCE IF NOT EXISTS medical_reports_reference_id_seq START WITH 1000 INCREMENT BY 1 MINVALUE 1000');

        Schema::table('medical_reports', function (Blueprint $table) {
            $table->unsignedBigInteger('reference_id')->nullable()->after('id');
            // File is now attached after the report row is created as a draft
            // (to reserve the reference_id before the Azure upload happens).
            $table->text('file_url')->nullable()->change();
        });

        DB::statement("UPDATE medical_reports SET reference_id = nextval('medical_reports_reference_id_seq') WHERE reference_id IS NULL");

        Schema::table('medical_reports', function (Blueprint $table) {
            $table->unsignedBigInteger('reference_id')->nullable(false)->change();
            $table->unique('reference_id');
        });

        // Default every insert to the next sequence value, so any code path that creates a
        // MedicalReport without explicitly setting reference_id still gets a unique one.
        DB::statement("ALTER TABLE medical_reports ALTER COLUMN reference_id SET DEFAULT nextval('medical_reports_reference_id_seq')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medical_reports', function (Blueprint $table) {
            $table->dropUnique(['reference_id']);
            $table->dropColumn('reference_id');
            $table->text('file_url')->nullable(false)->change();
        });

        DB::statement('DROP SEQUENCE IF EXISTS medical_reports_reference_id_seq');
    }
};
