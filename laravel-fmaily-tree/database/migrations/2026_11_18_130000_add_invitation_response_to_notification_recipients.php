<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_recipients', function (Blueprint $table) {
            $table->string('invitation_response', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('notification_recipients', function (Blueprint $table) {
            $table->dropColumn('invitation_response');
        });
    }
};
