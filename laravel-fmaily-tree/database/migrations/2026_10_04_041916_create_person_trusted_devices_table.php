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
        Schema::create('person_trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')
                ->constrained('persons')
                ->onDelete('cascade');
            $table->string('device_uuid', 64)->index();
            $table->string('device_name', 100)->nullable();
            $table->string('device_model', 100)->nullable();
            $table->string('os_platform', 30)->nullable();
            $table->string('os_version', 30)->nullable();
            $table->string('app_version', 30)->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->timestamp('verified_at')->nullable()->index();
            $table->timestamp('whatsapp_grace_expires_at')->nullable()->index();
            $table->boolean('admin_alerted')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['person_id', 'device_uuid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('person_trusted_devices');
    }
};
