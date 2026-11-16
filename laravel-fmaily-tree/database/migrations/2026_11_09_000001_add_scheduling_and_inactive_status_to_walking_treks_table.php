<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Expand the ENUM values for status to support 'scheduled' and 'inactive' (MySQL specific)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `walking_treks` MODIFY COLUMN `status` ENUM('active', 'scheduled', 'inactive', 'upcoming', 'completed', 'archived') NOT NULL DEFAULT 'inactive'");
        }

        // 2. Add scheduled_at timestamp and auto_start_counting flag
        Schema::table('walking_treks', function (Blueprint $table) {
            if (! Schema::hasColumn('walking_treks', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable()->after('end_date');
            }

            if (! Schema::hasColumn('walking_treks', 'auto_start_counting')) {
                $table->boolean('auto_start_counting')->default(true)->after('scheduled_at');
            }

            $table->index(['status', 'scheduled_at'], 'trek_status_scheduled_index');
        });
    }

    public function down(): void
    {
        Schema::table('walking_treks', function (Blueprint $table) {
            $table->dropIndex('trek_status_scheduled_index');

            if (Schema::hasColumn('walking_treks', 'auto_start_counting')) {
                $table->dropColumn('auto_start_counting');
            }

            if (Schema::hasColumn('walking_treks', 'scheduled_at')) {
                $table->dropColumn('scheduled_at');
            }
        });

        // Revert invalid values before shrinking enum back
        DB::table('walking_treks')
            ->whereIn('status', ['scheduled', 'inactive'])
            ->update(['status' => 'upcoming']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `walking_treks` MODIFY COLUMN `status` ENUM('active', 'upcoming', 'completed', 'archived') NOT NULL DEFAULT 'active'");
        }
    }
};
