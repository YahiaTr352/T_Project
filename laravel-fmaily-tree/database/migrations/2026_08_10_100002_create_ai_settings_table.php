<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        $now = now();

        $features = [];
        foreach (config('ai.features', []) as $key => $meta) {
            $features[$key] = [
                'enabled' => (bool) ($meta['default_enabled'] ?? false),
                'model' => null,
            ];
        }

        DB::table('ai_settings')->insert([
            [
                'key' => 'features',
                'value' => json_encode($features, JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'budget',
                'value' => json_encode([
                    'monthly_usd' => (float) config('ai.budget.monthly_usd', 20),
                    'soft_stop' => (bool) config('ai.budget.soft_stop', true),
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'rate_limit',
                'value' => json_encode(config('ai.rate_limit', [
                    'per_user_per_minute' => 10,
                    'per_ip_per_minute' => 20,
                ]), JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'prompts',
                'value' => json_encode(config('ai.prompts', []), JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'meta',
                'value' => json_encode([
                    'updated_by' => null,
                    'updated_by_name' => null,
                    'updated_at' => null,
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
    }
};
