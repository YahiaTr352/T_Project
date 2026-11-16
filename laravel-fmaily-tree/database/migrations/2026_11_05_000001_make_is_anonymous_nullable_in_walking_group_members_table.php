<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('walking_group_members', function (Blueprint $table) {
            $table->boolean('is_anonymous')->nullable()->default(null)->change();
        });

        // Migrate uncustomized existing records to NULL so they inherit global privacy settings
        DB::table('walking_group_members')
            ->where('is_anonymous', false)
            ->update(['is_anonymous' => null]);
    }

    public function down(): void
    {
        DB::table('walking_group_members')
            ->whereNull('is_anonymous')
            ->update(['is_anonymous' => false]);

        Schema::table('walking_group_members', function (Blueprint $table) {
            $table->boolean('is_anonymous')->nullable(false)->default(false)->change();
        });
    }
};
