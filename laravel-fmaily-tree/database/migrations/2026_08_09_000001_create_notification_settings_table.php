<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        $now = now();

        DB::table('notification_settings')->insert([
            [
                'key' => 'channels',
                'value' => json_encode(['whatsapp' => true, 'push' => true]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'ultramsg',
                'value' => json_encode(['instance_id' => null, 'token' => null]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'limits',
                'value' => json_encode([
                    'daily_limit' => 0,
                    'send_delay_ms' => 0,
                    'auto_retry' => false,
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'meta',
                'value' => json_encode([
                    'updated_by' => null,
                    'updated_by_name' => null,
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
    }
};
