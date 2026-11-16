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
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')
                ->nullable()
                ->constrained('persons')
                ->onDelete('cascade');
            $table->string('expo_push_token')->unique();
            $table->string('device_name')->nullable();
            $table->string('device_model')->nullable();
            $table->string('os_platform')->nullable();
            $table->string('os_version')->nullable();
            $table->string('app_version')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->index('person_id');
            $table->index('expo_push_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
