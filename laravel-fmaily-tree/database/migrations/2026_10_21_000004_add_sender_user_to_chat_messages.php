<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * السماح للإدارة بنشر رسائل رسمية داخل المحادثة:
     * sender_user_id (المشرف) + جعل sender_person_id اختيارياً.
     */
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('sender_user_id')->nullable()->after('sender_person_id')->index();
        });

        // فك الارتباط ثم جعل مرسل الشخص اختيارياً ثم إعادة الارتباط
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropForeign(['sender_person_id']);
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('sender_person_id')->nullable()->change();
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->foreign('sender_person_id')
                ->references('id')
                ->on('persons')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn('sender_user_id');
        });
    }
};
