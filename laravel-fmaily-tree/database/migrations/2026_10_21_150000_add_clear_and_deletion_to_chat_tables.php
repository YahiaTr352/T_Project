<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add cleared_at to conversation_participants for "Clear Chat for Me"
        if (Schema::hasTable('conversation_participants')) {
            Schema::table('conversation_participants', function (Blueprint $table) {
                if (!Schema::hasColumn('conversation_participants', 'cleared_at')) {
                    $table->timestamp('cleared_at')->nullable()->after('last_read_at');
                }
            });
        }

        // 2. Create chat_message_hidden_users for individual "Delete for Me"
        if (!Schema::hasTable('chat_message_hidden_users')) {
            Schema::create('chat_message_hidden_users', function (Blueprint $table) {
                $table->id();

                $table->foreignId('chat_message_id')
                    ->constrained('chat_messages')
                    ->cascadeOnDelete();

                $table->foreignId('person_id')
                    ->constrained('persons')
                    ->cascadeOnDelete();

                $table->timestamp('created_at')->useCurrent();

                $table->unique(['chat_message_id', 'person_id'], 'msg_hidden_unique');
                $table->index(['person_id', 'chat_message_id'], 'msg_hidden_person_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_hidden_users');

        if (Schema::hasTable('conversation_participants')) {
            Schema::table('conversation_participants', function (Blueprint $table) {
                if (Schema::hasColumn('conversation_participants', 'cleared_at')) {
                    $table->dropColumn('cleared_at');
                }
            });
        }
    }
};
