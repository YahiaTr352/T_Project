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
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('type', 32)->default('problem'); // problem, inquiry, suggestion, complaint, other
            $table->string('subject')->nullable();
            $table->text('message')->nullable();
            $table->string('media_type', 32)->default('none'); // none, image, video, voice
            $table->string('media_path', 1024)->nullable();
            $table->string('media_mime', 128)->nullable();
            $table->unsignedBigInteger('media_size')->nullable();
            $table->string('source', 16)->default('app'); // app, web
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('status', 32)->default('pending'); // pending, in_progress, resolved, archived
            $table->text('admin_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
