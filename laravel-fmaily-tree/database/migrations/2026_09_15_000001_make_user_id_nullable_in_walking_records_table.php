<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop foreign key constraint on user_id first (MySQL error 1553 requires this before dropping index)
        try {
            Schema::table('walking_records', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        } catch (\Throwable $e) {
            try {
                Schema::table('walking_records', function (Blueprint $table) {
                    $table->dropForeign('walking_records_user_id_foreign');
                });
            } catch (\Throwable $e) {}
        }

        // 2. Drop the old unique index on (user_id, date)
        try {
            Schema::table('walking_records', function (Blueprint $table) {
                $table->dropUnique('walking_records_user_id_date_unique');
            });
        } catch (\Throwable $e) {}

        // 3. Make user_id nullable and add index for foreign key performance
        Schema::table('walking_records', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        // 4. Re-add foreign key with nullOnDelete
        try {
            Schema::table('walking_records', function (Blueprint $table) {
                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        try {
            Schema::table('walking_records', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        } catch (\Throwable $e) {}

        Schema::table('walking_records', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
            $table->unique(['user_id', 'date'], 'walking_records_user_id_date_unique');
        });
    }
};

