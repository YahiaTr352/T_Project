<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ──────────────────────────────────────────────────────────────
        // walking_groups: challenges / group competitions
        // ──────────────────────────────────────────────────────────────
        Schema::create('walking_groups', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();

            // Challenge target — null means "open" (no fixed target)
            $table->unsignedInteger('target_steps')->nullable();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            /**
             * Privacy flag:
             *  false (private) → visible only to creator + invited members
             *  true  (public)  → visible to all platform users; anyone can join
             */
            $table->boolean('is_public')->default(false)->index();

            // Status machine
            $table->enum('status', ['active', 'upcoming', 'completed', 'archived'])
                ->default('active')
                ->index();

            // Optional share-link / invite code for private groups
            $table->string('invite_code', 20)->nullable()->unique();

            // Creator can be a family-tree person (mobile) OR an admin user (dashboard)
            $table->foreignId('created_by_person_id')
                ->nullable()
                ->constrained('persons')
                ->nullOnDelete();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });

        // ──────────────────────────────────────────────────────────────
        // walking_group_members: person membership in a group/challenge
        // ──────────────────────────────────────────────────────────────
        Schema::create('walking_group_members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('walking_group_id')
                ->constrained('walking_groups')
                ->cascadeOnDelete();

            $table->foreignId('person_id')
                ->constrained('persons')
                ->cascadeOnDelete();

            $table->enum('role', ['admin', 'member'])->default('member');
            $table->enum('status', ['accepted', 'invited', 'pending'])->default('accepted');

            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['walking_group_id', 'person_id'], 'wgm_group_person_unique');
            $table->index(['person_id', 'walking_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('walking_group_members');
        Schema::dropIfExists('walking_groups');
    }
};
