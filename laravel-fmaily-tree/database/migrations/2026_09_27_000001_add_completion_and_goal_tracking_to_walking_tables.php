<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add daily_goal_notified_at to walking_records to prevent duplicate daily notifications
        if (Schema::hasTable('walking_records')) {
            Schema::table('walking_records', function (Blueprint $table) {
                if (! Schema::hasColumn('walking_records', 'daily_goal_notified_at')) {
                    $table->timestamp('daily_goal_notified_at')->nullable()->after('notes')->index();
                }
            });
        }

        // 2. Add completed_at to walking_group_members to track when a member achieved target_steps
        if (Schema::hasTable('walking_group_members')) {
            Schema::table('walking_group_members', function (Blueprint $table) {
                if (! Schema::hasColumn('walking_group_members', 'completed_at')) {
                    $table->timestamp('completed_at')->nullable()->after('joined_at')->index();
                }
            });
        }

        // 3. Add completion_notified_at to walking_groups for challenge-wide final announcement
        if (Schema::hasTable('walking_groups')) {
            Schema::table('walking_groups', function (Blueprint $table) {
                if (! Schema::hasColumn('walking_groups', 'completion_notified_at')) {
                    $table->timestamp('completion_notified_at')->nullable()->after('archived_reason')->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('walking_records')) {
            Schema::table('walking_records', function (Blueprint $table) {
                if (Schema::hasColumn('walking_records', 'daily_goal_notified_at')) {
                    $table->dropIndex(['daily_goal_notified_at']);
                    $table->dropColumn('daily_goal_notified_at');
                }
            });
        }

        if (Schema::hasTable('walking_group_members')) {
            Schema::table('walking_group_members', function (Blueprint $table) {
                if (Schema::hasColumn('walking_group_members', 'completed_at')) {
                    $table->dropIndex(['completed_at']);
                    $table->dropColumn('completed_at');
                }
            });
        }

        if (Schema::hasTable('walking_groups')) {
            Schema::table('walking_groups', function (Blueprint $table) {
                if (Schema::hasColumn('walking_groups', 'completion_notified_at')) {
                    $table->dropIndex(['completion_notified_at']);
                    $table->dropColumn('completion_notified_at');
                }
            });
        }
    }
};
