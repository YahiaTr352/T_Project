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
        if (!Schema::hasTable('notification_dismissals')) {
            Schema::create('notification_dismissals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete();
                $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['notification_id', 'person_id'], 'notif_dismiss_unique');
                $table->index(['person_id', 'notification_id'], 'notif_dismiss_lookup');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_dismissals');
    }
};