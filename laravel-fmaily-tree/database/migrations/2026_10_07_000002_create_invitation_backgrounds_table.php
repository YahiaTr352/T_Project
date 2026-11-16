<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitation_backgrounds', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('image_path');
            $table->foreignId('uploaded_by')->nullable()->constrained('persons')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->boolean('is_template')->default(false);
            $table->timestamps();
            $table->index(['status', 'is_template']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitation_backgrounds');
    }
};