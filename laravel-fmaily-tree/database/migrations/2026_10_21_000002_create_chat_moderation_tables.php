<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * جداول نظام إشراف المحادثات: الحظر، البلاغات، الكلمات الممنوعة، سجل التدقيق.
     */
    public function up(): void
    {
        // ── chat_bans: حظر شخص من محادثة محددة أو من كل المحادثات ──────
        Schema::create('chat_bans', function (Blueprint $table) {
            $table->id();

            $table->foreignId('person_id')
                ->constrained('persons')
                ->cascadeOnDelete();

            // NULL = حظر عام من كل المحادثات
            $table->foreignId('conversation_id')
                ->nullable()
                ->constrained('conversations')
                ->cascadeOnDelete();

            $table->enum('scope', ['conversation', 'global'])->default('conversation')->index();

            $table->text('reason')->nullable();

            $table->foreignId('banned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('banned_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();

            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('revoke_reason', 500)->nullable();

            $table->timestamps();

            $table->index(['person_id', 'is_active']);
            $table->index(['conversation_id', 'is_active']);
        });

        // ── chat_reports: بلاغات المستخدمين على الرسائل ────────────────
        Schema::create('chat_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reporter_person_id')
                ->constrained('persons')
                ->cascadeOnDelete();

            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();

            $table->foreignId('message_id')
                ->nullable()
                ->constrained('chat_messages')
                ->nullOnDelete();

            $table->enum('reason', [
                'spam', 'abuse', 'inappropriate', 'personal_info', 'impersonation', 'other',
            ])->default('other')->index();

            $table->text('details')->nullable();

            $table->enum('status', ['pending', 'reviewing', 'resolved', 'dismissed'])
                ->default('pending')
                ->index();

            $table->foreignId('handled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->text('admin_note')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['conversation_id', 'status']);
            $table->index('message_id');
        });

        // ── chat_blocked_words: الكلمات الممنوعة ───────────────────────
        Schema::create('chat_blocked_words', function (Blueprint $table) {
            $table->id();

            $table->string('word', 150)->unique();

            // block = يمنع الإرسال | mask = يخفي الكلمة | warn = ينجح مع تسجيل تحذير
            $table->enum('severity', ['block', 'mask', 'warn'])->default('block')->index();

            $table->boolean('is_active')->default(true)->index();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });

        // ── chat_moderation_logs: سجل تدقيق إجراءات الإشراف ───────────
        Schema::create('chat_moderation_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')
                ->nullable()
                ->constrained('conversations')
                ->nullOnDelete();

            $table->foreignId('message_id')
                ->nullable()
                ->constrained('chat_messages')
                ->nullOnDelete();

            // الشخص المخالف / المتأثر بالإجراء
            $table->foreignId('person_id')
                ->nullable()
                ->constrained('persons')
                ->nullOnDelete();

            // الإداري الذي نفّذ الإجراء
            $table->foreignId('admin_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('action', 60)->index();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['person_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_moderation_logs');
        Schema::dropIfExists('chat_blocked_words');
        Schema::dropIfExists('chat_reports');
        Schema::dropIfExists('chat_bans');
    }
};
