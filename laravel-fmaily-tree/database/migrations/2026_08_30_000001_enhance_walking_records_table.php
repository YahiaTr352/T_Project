<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('walking_records', function (Blueprint $table) {
            // Associate record with a Family Tree person (Face/WhatsApp authenticated identity)
            $table->foreignId('person_id')
                ->nullable()
                ->after('user_id')
                ->constrained('persons')
                ->nullOnDelete()
                ->index();

            // Estimated calories burned
            $table->unsignedSmallInteger('calories')->nullable()->after('duration_minutes');

            // Source of the step data for auditability and deduplication
            $table->string('source', 30)
                ->default('manual')
                ->after('calories')
                ->comment('apple_health | health_connect | google_fit | device | manual');

            $table->string('device_info', 120)->nullable()->after('source');
        });

        // Unique constraint per person per day (mirrors the user_id+date constraint)
        Schema::table('walking_records', function (Blueprint $table) {
            $table->unique(['person_id', 'date'], 'walking_records_person_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('walking_records', function (Blueprint $table) {
            $table->dropUnique('walking_records_person_date_unique');
            $table->dropForeign(['person_id']);
            $table->dropColumn(['person_id', 'calories', 'source', 'device_info']);
        });
    }
};
