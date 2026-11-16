<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ──────────────────────────────────────────────────────────────
        // 1. walking_treks: Virtual tours & collective milestone challenges
        // ──────────────────────────────────────────────────────────────
        Schema::create('walking_treks', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();

            // Total distance in KM (auto-calculated from waypoints sum)
            $table->decimal('total_distance_km', 10, 2)->default(0);

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->enum('status', ['active', 'scheduled', 'inactive', 'upcoming', 'completed', 'archived'])
                ->default('active')
                ->index();

            // Target audience:
            // - family_community: all eligible family members contribute collectively
            // - group: tied to a specific walking group / challenge
            $table->enum('target_type', ['family_community', 'group'])
                ->default('family_community')
                ->index();

            $table->foreignId('walking_group_id')
                ->nullable()
                ->constrained('walking_groups')
                ->nullOnDelete();

            // Loop flag: route returns back to starting point (circular journey)
            $table->boolean('is_loop')->default(true);

            // Audit
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('created_by_person_id')
                ->nullable()
                ->constrained('persons')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });

        // ──────────────────────────────────────────────────────────────
        // 2. walking_trek_waypoints: Milestones & stops along the journey
        // ──────────────────────────────────────────────────────────────
        Schema::create('walking_trek_waypoints', function (Blueprint $table) {
            $table->id();

            $table->foreignId('walking_trek_id')
                ->constrained('walking_treks')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('sort_order')->default(1);

            $table->string('city_name');
            $table->string('location_title');
            $table->string('location_description')->nullable();

            // High precision GPS coordinates for Google Maps
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);

            // Distance from previous waypoint in KM (first waypoint = 0)
            $table->decimal('distance_from_prev_km', 8, 2)->default(0);

            // Cumulative distance from start to this waypoint in KM
            $table->decimal('cumulative_km', 10, 2)->default(0);

            // Icon & display style: home, landmark, mosque, city
            $table->string('icon_type', 30)->default('home');
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique(['walking_trek_id', 'sort_order'], 'trek_waypoint_order_unique');
            $table->index(['walking_trek_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('walking_trek_waypoints');
        Schema::dropIfExists('walking_treks');
    }
};
