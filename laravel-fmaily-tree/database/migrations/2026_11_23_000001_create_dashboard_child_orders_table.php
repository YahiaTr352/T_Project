<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_child_orders', function (Blueprint $table) {
            $table->foreignId('parent_id')->primary()->constrained('persons')->cascadeOnDelete();
            $table->json('positions');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_child_orders');
    }
};
