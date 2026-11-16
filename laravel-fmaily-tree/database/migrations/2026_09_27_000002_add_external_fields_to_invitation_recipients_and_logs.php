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
        Schema::table('notification_recipients', function (Blueprint $table) {
            $table->unsignedBigInteger('person_id')->nullable()->change();
            $table->string('recipient_name')->nullable()->after('person_id');
            $table->string('recipient_phone')->nullable()->after('recipient_name');
        });

        Schema::table('ultramsg_send_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('person_id')->nullable()->change();
            $table->string('recipient_name')->nullable()->after('person_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_recipients', function (Blueprint $table) {
            $table->dropColumn(['recipient_name', 'recipient_phone']);
            $table->unsignedBigInteger('person_id')->nullable(false)->change();
        });

        Schema::table('ultramsg_send_logs', function (Blueprint $table) {
            $table->dropColumn(['recipient_name']);
            $table->unsignedBigInteger('person_id')->nullable(false)->change();
        });
    }
};
