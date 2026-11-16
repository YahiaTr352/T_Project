<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('walking_group_members', function (Blueprint $table) {
            $table->boolean('is_anonymous')->default(false)->after('status')->index();
            $table->boolean('share_details')->default(true)->after('is_anonymous');
            $table->boolean('notifications_enabled')->default(true)->after('share_details');
        });
    }

    public function down(): void
    {
        Schema::table('walking_group_members', function (Blueprint $table) {
            $table->dropColumn(['is_anonymous', 'share_details', 'notifications_enabled']);
        });
    }
};
