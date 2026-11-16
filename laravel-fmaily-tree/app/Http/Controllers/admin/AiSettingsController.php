<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\AiProvider;
use App\Models\AiSetting;
use App\Services\AI\AiManager;
use App\Services\AI\AiUsageTracker;
use App\Support\AiSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiSettingsController extends Controller
{
    public function __construct(
        private readonly AiManager $aiManager,
        private readonly AiUsageTracker $usageTracker,
    ) {
    }

    public function index(): View
    {
        $providers = AiProvider::query()->orderBy('sort_order')->orderBy('id')->get();
        $features = AiSettings::features();
        $featureMeta = config('ai.features', []);
        $budget = AiSettings::budget();
        $rateLimit = AiSettings::rateLimit();
        $prompts = AiSettings::prompts();
        $meta = AiSettings::meta();
        $monthSpend = $this->usageTracker->monthToDateSpendUsd();
        $daily = $this->usageTracker->dailySpend(30);
        $byFeature = $this->usageTracker->spendByFeature();

        $knownDrivers = [
            'openai' => 'OpenAI',
            'anthropic' => 'Anthropic (Claude)',
            'gemini' => 'Google Gemini',
        ];

        $modelPresets = config('ai.model_presets', []);
        $budgetPresets = config('ai.budget_presets', [10, 20, 50, 100]);

        $activeProviders = $providers->where('is_active', true);
        $hasActiveProviderWithKey = $activeProviders->contains(fn (AiProvider $p) => $p->hasApiKey());
        $chatEnabled = AiSettings::isFeatureEnabled('chat');
        $setupComplete = $hasActiveProviderWithKey && $chatEnabled;
        $embeddingsCount = \App\Models\AiEmbedding::query()->count();

        return view('dashboard.ai.index', compact(
            'providers',
            'features',
            'featureMeta',
            'budget',
            'rateLimit',
            'prompts',
            'meta',
            'monthSpend',
            'daily',
            'byFeature',
            'knownDrivers',
            'modelPresets',
            'budgetPresets',
            'hasActiveProviderWithKey',
            'chatEnabled',
            'setupComplete',
            'activeProviders',
            'embeddingsCount',
        ));
    }

    public function updateProviders(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'providers' => 'required|array',
            'providers.*.id' => 'nullable|integer|exists:ai_providers,id',
            'providers.*.driver' => 'required|string|in:openai,anthropic,gemini',
            'providers.*.name' => 'required|string|max:120',
            'providers.*.api_key' => 'nullable|string|max:500',
            'providers.*.is_active' => 'nullable|boolean',
            'providers.*.sort_order' => 'nullable|integer|min:0|max:1000',
            'providers.*.models.chat' => 'nullable|string|max:120',
            'providers.*.models.embedding' => 'nullable|string|max:120',
            'providers.*.models.transcription' => 'nullable|string|max:120',
        ]);

        foreach ($validated['providers'] as $row) {
            $provider = isset($row['id'])
                ? AiProvider::query()->find($row['id'])
                : AiProvider::query()->firstOrNew(['driver' => $row['driver']]);

            if (! $provider) {
                $provider = new AiProvider(['driver' => $row['driver']]);
            }

            $provider->driver = $row['driver'];
            $provider->name = $row['name'];
            $provider->is_active = (bool) ($row['is_active'] ?? false);
            $provider->sort_order = (int) ($row['sort_order'] ?? 0);
            $provider->models = [
                'chat' => $row['models']['chat'] ?? null,
                'embedding' => $row['models']['embedding'] ?? null,
                'transcription' => $row['models']['transcription'] ?? null,
            ];

            $apiKey = $row['api_key'] ?? null;
            if (filled($apiKey) && $apiKey !== '********') {
                $provider->api_key = $apiKey;
            }

            $provider->save();
        }

        $this->touchMeta($request);
        $this->aiManager->forgetCache();
        AiSettings::forgetCache();

        activity()
            ->causedBy($request->user())
            ->withProperties(['section' => 'providers'])
            ->log('ai.settings.providers_updated');

        return redirect()
            ->route('dashboard.ai.index')
            ->with('success', 'تم حفظ مزودي الذكاء الاصطناعي بنجاح.');
    }

    public function storeProvider(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'driver' => 'required|string|in:openai,anthropic,gemini|unique:ai_providers,driver',
            'name' => 'required|string|max:120',
            'api_key' => 'nullable|string|max:500',
        ]);

        $defaults = config('ai.models.'.$validated['driver'], []);

        AiProvider::query()->create([
            'driver' => $validated['driver'],
            'name' => $validated['name'],
            'api_key' => $validated['api_key'] ?? null,
            'models' => $defaults,
            'is_active' => false,
            'sort_order' => (int) AiProvider::query()->max('sort_order') + 1,
        ]);

        $this->touchMeta($request);
        $this->aiManager->forgetCache();

        return redirect()
            ->route('dashboard.ai.index')
            ->with('success', 'تمت إضافة المزود.');
    }

    public function updateFeatures(Request $request): RedirectResponse
    {
        $featureKeys = array_keys(config('ai.features', []));

        $rules = [
            'budget.monthly_usd' => 'nullable|numeric|min:0|max:100000',
            'budget.soft_stop' => 'nullable|boolean',
            'rate_limit.per_user_per_minute' => 'nullable|integer|min:1|max:1000',
            'rate_limit.per_ip_per_minute' => 'nullable|integer|min:1|max:5000',
        ];

        foreach ($featureKeys as $key) {
            $rules["features.{$key}.enabled"] = 'nullable|boolean';
            $rules["features.{$key}.model"] = 'nullable|string|max:120';
        }

        $validated = $request->validate($rules);

        $features = AiSettings::features();
        foreach ($featureKeys as $key) {
            $features[$key] = [
                'enabled' => (bool) data_get($validated, "features.{$key}.enabled", false),
                'model' => data_get($validated, "features.{$key}.model") ?: null,
            ];
        }

        if ($request->has('tts_voice')) {
            AiSetting::setValue('tts_voice', $request->input('tts_voice'));
        }
        AiSetting::setValue('features', $features);
        AiSetting::setValue('budget', [
            'monthly_usd' => (float) data_get($validated, 'budget.monthly_usd', AiSettings::budget()['monthly_usd']),
            'soft_stop' => (bool) data_get($validated, 'budget.soft_stop', true),
        ]);
        AiSetting::setValue('rate_limit', [
            'per_user_per_minute' => (int) data_get($validated, 'rate_limit.per_user_per_minute', 10),
            'per_ip_per_minute' => (int) data_get($validated, 'rate_limit.per_ip_per_minute', 20),
        ]);

        $this->touchMeta($request);
        AiSettings::forgetCache();

        activity()
            ->causedBy($request->user())
            ->withProperties(['section' => 'features'])
            ->log('ai.settings.features_updated');

        return redirect()
            ->route('dashboard.ai.index')
            ->with('success', 'تم حفظ إعدادات المميزات والميزانية.');
    }

    public function updatePrompts(Request $request): RedirectResponse
    {
        $promptKeys = array_keys(config('ai.prompts', []));
        $rules = [];
        foreach ($promptKeys as $key) {
            $rules["prompts.{$key}"] = 'nullable|string|max:20000';
        }

        $validated = $request->validate($rules);
        $prompts = AiSettings::prompts();

        foreach ($promptKeys as $key) {
            if (array_key_exists($key, $validated['prompts'] ?? [])) {
                $prompts[$key] = (string) $validated['prompts'][$key];
            }
        }

        AiSetting::setValue('prompts', $prompts);
        $this->touchMeta($request);
        AiSettings::forgetCache();

        activity()
            ->causedBy($request->user())
            ->withProperties(['section' => 'prompts'])
            ->log('ai.settings.prompts_updated');

        return redirect()
            ->route('dashboard.ai.index')
            ->with('success', 'تم حفظ قوالب البرومبت.');
    }

    public function testProvider(Request $request, AiProvider $provider): JsonResponse
    {
        try {
            $driver = $this->aiManager->makeDriver($provider);
            $result = $driver->testConnection();

            return response()->json($result, ($result['ok'] ?? false) ? 200 : 422);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    public function indexNow(Request $request): JsonResponse
    {
        try {
            \Artisan::call('ai:index', ['--type' => 'all', '--sync' => false]);

            return response()->json([
                'ok' => true,
                'message' => 'بدأت فهرسة المحتوى في الخلفية. قد تستغرق دقائق حسب حجم البيانات.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'تعذر بدء الفهرسة: '.$e->getMessage(),
            ], 422);
        }
    }

    private function touchMeta(Request $request): void
    {
        AiSetting::setValue('meta', [
            'updated_by' => $request->user()?->id,
            'updated_by_name' => $request->user()?->name,
            'updated_at' => now()->toIso8601String(),
        ]);
    }
}
