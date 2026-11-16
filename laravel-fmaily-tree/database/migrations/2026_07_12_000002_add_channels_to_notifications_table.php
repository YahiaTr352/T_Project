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
        Schema::table('notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('notifications', 'channels')) {
                $table->json('channels')->nullable()->after('body'); // e.g., ["whatsapp", "push", "sms", "email"]
            }
            if (!Schema::hasColumn('notifications', 'targeting_filters')) {
                $table->json('targeting_filters')->nullable()->after('channels'); // criteria like location, gender, exclude_deceased, etc.
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn(['channels', 'targeting_filters']);
        });
    }
};
