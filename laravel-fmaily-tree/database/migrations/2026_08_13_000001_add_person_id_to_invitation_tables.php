<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_groups', function (Blueprint $table) {
            if (! Schema::hasColumn('notification_groups', 'person_id')) {
                $table->foreignId('person_id')
                    ->nullable()
                    ->constrained('persons')
                    ->nullOnDelete();
            }
        });

        try {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        } catch (\Throwable) {
            // SQLite or already dropped
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        try {
            Schema::table('notifications', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        } catch (\Throwable) {
        }

        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('notifications', 'person_id')) {
                $table->foreignId('person_id')
                    ->nullable()
                    ->constrained('persons')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (Schema::hasColumn('notifications', 'person_id')) {
                $table->dropConstrainedForeignId('person_id');
            }
        });

        Schema::table('notification_groups', function (Blueprint $table) {
            if (Schema::hasColumn('notification_groups', 'person_id')) {
                $table->dropConstrainedForeignId('person_id');
            }
        });
    }
};
