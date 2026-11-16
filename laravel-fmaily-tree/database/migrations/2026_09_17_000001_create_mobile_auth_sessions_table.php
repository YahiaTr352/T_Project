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
        Schema::create('mobile_auth_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('person_id')->nullable()->index();
            $table->string('auth_method', 32)->default('pin')->index();
            $table->string('access_token_hash', 64)->unique();
            $table->string('refresh_token_hash', 64)->nullable()->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('refresh_expires_at')->nullable()->index();
            $table->timestamp('last_used_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('person_id')->references('id')->on('persons')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mobile_auth_sessions');
    }
};
