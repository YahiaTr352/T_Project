<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->boolean('is_muted')->default(true)->change();
        });

        // جعل جميع المشاركين الحاليين مكتومي الإشعارات افتراضياً
        DB::table('conversation_participants')->update(['is_muted' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->boolean('is_muted')->default(false)->change();
        });
    }
};
