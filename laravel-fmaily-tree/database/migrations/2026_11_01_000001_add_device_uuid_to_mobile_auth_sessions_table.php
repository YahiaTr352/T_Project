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
        Schema::table('mobile_auth_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('mobile_auth_sessions', 'device_uuid')) {
                $table->string('device_uuid', 64)->nullable()->after('auth_method')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mobile_auth_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('mobile_auth_sessions', 'device_uuid')) {
                $table->dropIndex(['device_uuid']);
                $table->dropColumn('device_uuid');
            }
        });
    }
};
