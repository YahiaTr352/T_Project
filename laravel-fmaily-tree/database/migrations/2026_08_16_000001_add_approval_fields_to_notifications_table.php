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
        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('notifications', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('status');
            }

            if (! Schema::hasColumn('notifications', 'approved_by')) {
                $table->foreignId('approved_by')
                    ->nullable()
                    ->after('rejection_reason')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('notifications', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }

            if (! Schema::hasColumn('notifications', 'rejected_by')) {
                $table->foreignId('rejected_by')
                    ->nullable()
                    ->after('approved_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('notifications', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (Schema::hasColumn('notifications', 'approved_by')) {
                $table->dropConstrainedForeignId('approved_by');
            }

            if (Schema::hasColumn('notifications', 'rejected_by')) {
                $table->dropConstrainedForeignId('rejected_by');
            }

            $columnsToDrop = array_values(array_filter([
                Schema::hasColumn('notifications', 'rejection_reason') ? 'rejection_reason' : null,
                Schema::hasColumn('notifications', 'approved_at') ? 'approved_at' : null,
                Schema::hasColumn('notifications', 'rejected_at') ? 'rejected_at' : null,
            ]));

            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
