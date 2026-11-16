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
        Schema::table('walking_trek_waypoints', function (Blueprint $table) {
            $table->longText('route_polyline')->nullable()->after('metadata');
            $table->json('route_metadata')->nullable()->after('route_polyline');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('walking_trek_waypoints', function (Blueprint $table) {
            $table->dropColumn(['route_polyline', 'route_metadata']);
        });
    }
};
