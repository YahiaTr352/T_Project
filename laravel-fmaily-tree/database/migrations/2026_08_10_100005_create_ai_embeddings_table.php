<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_embeddings', function (Blueprint $table) {
            $table->id();
            $table->morphs('embeddable');
            $table->string('chunk_key', 80)->default('default');
            $table->string('content_hash', 64);
            $table->longText('content');
            $table->json('embedding');
            $table->string('model', 120)->nullable();
            $table->unsignedSmallInteger('dimensions')->nullable();
            $table->timestamps();

            $table->unique(['embeddable_type', 'embeddable_id', 'chunk_key'], 'ai_embeddings_unique_chunk');
            $table->index('content_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_embeddings');
    }
};
