<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $rows = [
            [
                'driver' => 'openai',
                'name' => 'OpenAI',
                'api_key' => null,
                'models' => json_encode(config('ai.models.openai', []), JSON_UNESCAPED_UNICODE),
                'is_active' => false,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'driver' => 'anthropic',
                'name' => 'Anthropic Claude',
                'api_key' => null,
                'models' => json_encode(config('ai.models.anthropic', []), JSON_UNESCAPED_UNICODE),
                'is_active' => false,
                'sort_order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'driver' => 'gemini',
                'name' => 'Google Gemini',
                'api_key' => null,
                'models' => json_encode(config('ai.models.gemini', []), JSON_UNESCAPED_UNICODE),
                'is_active' => false,
                'sort_order' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($rows as $row) {
            $exists = DB::table('ai_providers')->where('driver', $row['driver'])->exists();
            if (! $exists) {
                DB::table('ai_providers')->insert($row);
            }
        }
    }

    public function down(): void
    {
        DB::table('ai_providers')->whereIn('driver', ['openai', 'anthropic', 'gemini'])->delete();
    }
};
