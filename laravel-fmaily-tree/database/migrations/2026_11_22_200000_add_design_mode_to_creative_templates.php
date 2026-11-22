<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creative_templates', function (Blueprint $table) {
            $table->string('design_mode', 24)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('creative_templates', function (Blueprint $table) {
            $table->dropIndex(['design_mode']);
            $table->dropColumn('design_mode');
        });
    }
};
