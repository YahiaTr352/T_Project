<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إضافة أعمدة الإشراف على المحادثات (قفل، وضع بطيء، إخفاء، قيود على المشاركين).
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // قفل المحادثة: القراءة مفتوحة والكتابة مغلقة إلا للمشرفين
            $table->boolean('is_locked')->default(false)->after('is_active')->index();
            $table->timestamp('locked_at')->nullable()->after('is_locked');
            $table->unsignedBigInteger('locked_by')->nullable()->after('locked_at')->index();
            $table->string('lock_reason', 500)->nullable()->after('locked_by');

            // إعدادات الوزن الخاصة بالمحادثة
            $table->unsignedInteger('slow_mode_seconds')->default(0)->after('lock_reason');
            $table->boolean('allow_attachments')->default(true)->after('slow_mode_seconds');
            $table->unsignedInteger('max_message_length')->default(2000)->after('allow_attachments');
            $table->boolean('auto_moderation_enabled')->default(true)->after('max_message_length');

            // الرسالة المثبتة
            $table->unsignedBigInteger('last_pinned_message_id')->nullable()->after('auto_moderation_enabled')->index();
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            // إخفاء الرسالة من المستخدمين مع الاحتفاظ بها في سجل التدقيق
            $table->boolean('is_hidden')->default(false)->after('metadata')->index();
            $table->timestamp('hidden_at')->nullable()->after('is_hidden');
            $table->unsignedBigInteger('hidden_by')->nullable()->after('hidden_at')->index();
            $table->string('moderation_note', 500)->nullable()->after('hidden_by');

            // الرصد التلقائي (فلتر الكلمات / الذكاء الاصطناعي)
            $table->boolean('is_flagged')->default(false)->after('moderation_note')->index();
            $table->string('flag_reason', 255)->nullable()->after('is_flagged');
            $table->decimal('ai_score', 5, 4)->nullable()->after('flag_reason');
            $table->timestamp('ai_reviewed_at')->nullable()->after('ai_score');

            // التعديل والرد
            $table->boolean('is_edited')->default(false)->after('ai_reviewed_at');
            $table->timestamp('edited_at')->nullable()->after('is_edited');
            $table->unsignedBigInteger('reply_to_message_id')->nullable()->after('edited_at')->index();

            // التفاعلات وعداد البلاغات
            $table->json('reaction_summary')->nullable()->after('reply_to_message_id');
            $table->unsignedInteger('reported_count')->default(0)->after('reaction_summary')->index();
        });

        Schema::table('conversation_participants', function (Blueprint $table) {
            // منع إرسال رسائل لمشارك واحد داخل هذه المحادثة
            $table->boolean('can_send')->default(true)->after('is_muted');
            $table->string('restriction_reason', 500)->nullable()->after('can_send');
            $table->timestamp('restricted_at')->nullable()->after('restriction_reason');
            $table->unsignedBigInteger('restricted_by')->nullable()->after('restricted_at')->index();

            // عدّاد التحذيرات
            $table->unsignedInteger('warnings_count')->default(0)->after('restricted_by');

            // آخر نشاط للمشارك داخل المحادثة
            $table->timestamp('last_message_at')->nullable()->after('warnings_count');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->dropColumn([
                'can_send', 'restriction_reason', 'restricted_at', 'restricted_by',
                'warnings_count', 'last_message_at',
            ]);
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn([
                'is_hidden', 'hidden_at', 'hidden_by', 'moderation_note',
                'is_flagged', 'flag_reason', 'ai_score', 'ai_reviewed_at',
                'is_edited', 'edited_at', 'reply_to_message_id',
                'reaction_summary', 'reported_count',
            ]);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn([
                'is_locked', 'locked_at', 'locked_by', 'lock_reason',
                'slow_mode_seconds', 'allow_attachments', 'max_message_length',
                'auto_moderation_enabled', 'last_pinned_message_id',
            ]);
        });
    }
};
