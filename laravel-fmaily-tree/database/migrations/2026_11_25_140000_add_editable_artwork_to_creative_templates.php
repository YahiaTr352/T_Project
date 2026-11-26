<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creative_templates', function (Blueprint $table) {
            $table->string('editable_image_path')->nullable();
            $table->json('editable_text')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('creative_templates', function (Blueprint $table) {
            $table->dropColumn(['editable_image_path', 'editable_text']);
        });
    }
};
