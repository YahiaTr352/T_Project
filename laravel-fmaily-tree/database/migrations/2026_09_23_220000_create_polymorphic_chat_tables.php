<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ──────────────────────────────────────────────────────────────
        // 1. conversations: Polymorphic chat rooms / channels
        // ──────────────────────────────────────────────────────────────
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();

            // Polymorphic link to any chatable entity (WalkingGroup, FamilyEvent, etc.)
            $table->string('conversable_type', 120);
            $table->unsignedBigInteger('conversable_id');

            $table->string('title')->nullable();
            $table->string('type', 40)->default('group'); // group, direct, channel
            $table->boolean('is_active')->default(true);

            // Pre-aggregated metadata for fast sorting and previews
            $table->timestamp('last_message_at')->nullable()->index();
            $table->string('last_message_preview', 255)->nullable();
            $table->unsignedInteger('messages_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['conversable_type', 'conversable_id']);
        });

        // ──────────────────────────────────────────────────────────────
        // 2. chat_messages: Individual messages inside a conversation
        // ──────────────────────────────────────────────────────────────
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();

            $table->foreignId('sender_person_id')
                ->constrained('persons')
                ->cascadeOnDelete();

            $table->string('type', 30)->default('text'); // text, image, system
            $table->text('message')->nullable();
            $table->string('attachment_url', 500)->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Composite index for fast cursor pagination
            $table->index(['conversation_id', 'id']);
            $table->index('sender_person_id');
        });

        // ──────────────────────────────────────────────────────────────
        // 3. conversation_participants: Read markers, mute status, roles
        // ──────────────────────────────────────────────────────────────
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();

            $table->foreignId('person_id')
                ->constrained('persons')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->boolean('is_muted')->default(false);
            $table->string('role', 30)->default('member');
            $table->timestamp('joined_at')->useCurrent();

            $table->timestamps();

            $table->unique(['conversation_id', 'person_id'], 'conv_part_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('conversations');
    }
};
