<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('walking_groups', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('status')->index();
            $table->string('archived_reason', 100)->nullable()->after('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('walking_groups', function (Blueprint $table) {
            $table->dropColumn(['archived_at', 'archived_reason']);
        });
    }
};
