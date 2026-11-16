<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('walking_daily_resets', function (Blueprint $table) {
            $table->id();
            $table->date('date')->comment('اليوم السعودي الذي تم تصفيره Y-m-d');
            $table->foreignId('person_id')->nullable()->constrained('persons')->nullOnDelete()->comment('null = تصفير الكل');
            $table->string('scope', 20)->default('all')->comment('person | all');
            $table->foreignId('reset_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20)->default('manual')->comment('manual | auto | dawn-cleanup');
            $table->string('reason', 255)->nullable();
            $table->unsignedInteger('affected_records')->default(0);
            $table->timestamps();

            $table->index(['date', 'person_id']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('walking_daily_resets');
    }
};