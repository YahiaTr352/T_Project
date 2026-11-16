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
        Schema::table('persons', function (Blueprint $table) {
            if (!Schema::hasColumn('persons', 'privacy_settings')) {
                $table->json('privacy_settings')->nullable()->after('biography');
            }
        });

        Schema::table('person_contact_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('person_contact_accounts', 'visibility')) {
                $table->string('visibility', 20)->default('family')->after('value');
                $table->index(['person_id', 'visibility']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('person_contact_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('person_contact_accounts', 'visibility')) {
                $table->dropIndex(['person_id', 'visibility']);
                $table->dropColumn('visibility');
            }
        });

        Schema::table('persons', function (Blueprint $table) {
            if (Schema::hasColumn('persons', 'privacy_settings')) {
                $table->dropColumn('privacy_settings');
            }
        });
    }
};
