<?php

namespace App\Services\AI;

use App\Models\AiProvider;
use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\Drivers\AnthropicDriver;
use App\Services\AI\Drivers\GeminiDriver;
use App\Services\AI\Drivers\OpenAiDriver;
use App\Services\AI\Exceptions\AiException;
use App\Support\AiSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class AiManager
{
    private const CACHE_KEY = 'ai.providers.active';

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return Collection<int, AiProvider>
     */
    public function activeProviders(): Collection
    {
        if (! Schema::hasTable('ai_providers')) {
            return collect();
        }

        return Cache::remember(self::CACHE_KEY, 60, function () {
            return AiProvider::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        });
    }

    public function resolve(?string $preferredDriver = null, ?string $purpose = null): AiProviderInterface
    {
        $providers = $this->activeProviders();

        if ($preferredDriver) {
            $preferred = $providers->firstWhere('driver', $preferredDriver);
            if ($preferred) {
                return $this->makeDriver($preferred);
            }
        }

        foreach ($providers as $provider) {
            if ($purpose === 'embedding' && ! $provider->modelFor('embedding')) {
                continue;
            }
            if ($purpose === 'transcription' && ! $provider->modelFor('transcription')) {
                continue;
            }

            try {
                return $this->makeDriver($provider);
            } catch (\Throwable) {
                continue;
            }
        }

        // Env fallback (dev / first boot)
        $envDriver = config('ai.default_provider', 'openai');
        $envKey = config("services.ai.{$envDriver}.api_key") ?: env(strtoupper($envDriver).'_API_KEY');

        if (filled($envKey)) {
            return $this->makeDriverFromParts($envDriver, (string) $envKey, config("ai.models.{$envDriver}", []));
        }

        throw new AiException('لا يوجد مزود ذكاء اصطناعي مفعّل بمفتاح صالح. راجع إعدادات الذكاء الاصطناعي.');
    }

    public function resolveForFeature(string $feature): AiProviderInterface
    {
        $modelOverride = AiSettings::featureModel($feature);

        // Prefer providers that can serve the feature purpose
        $purpose = match ($feature) {
            'transcription' => 'transcription',
            'semantic_search' => 'embedding',
            'tts' => 'tts',
            default => 'chat',
        };

        $driver = $this->resolve(null, $purpose);

        return $driver;
    }

    public function modelForFeature(string $feature, ?AiProviderInterface $driver = null): ?string
    {
        $override = AiSettings::featureModel($feature);
        if ($override) {
            return $override;
        }

        $purpose = match ($feature) {
            'transcription' => 'transcription',
            'semantic_search' => 'embedding',
            'tts' => 'tts',
            default => 'chat',
        };

        $driverName = $driver?->driver() ?? 'openai';

        try {
            $activeProvider = AiProvider::query()->where('is_active', true)->where('driver', $driverName)->first();
            if ($activeProvider && !empty($activeProvider->models[$purpose])) {
                return (string) $activeProvider->models[$purpose];
            }
        } catch (\Throwable) {
            // DB not ready or testing
        }

        return config("ai.models.{$driverName}.{$purpose}");
    }

    public function makeDriver(AiProvider $provider): AiProviderInterface
    {
        $key = $provider->getDecryptedApiKey()
            ?: config("services.ai.{$provider->driver}.api_key")
            ?: env(strtoupper($provider->driver).'_API_KEY');

        if (! filled($key)) {
            throw new AiException("مفتاح API غير مضبوط للمزود {$provider->driver}.");
        }

        return $this->makeDriverFromParts(
            $provider->driver,
            (string) $key,
            $provider->models ?? config("ai.models.{$provider->driver}", [])
        );
    }

    public function makeDriverFromParts(string $driver, string $apiKey, array $models = []): AiProviderInterface
    {
        return match ($driver) {
            'openai' => new OpenAiDriver($apiKey, $models),
            'anthropic' => new AnthropicDriver($apiKey, $models),
            'gemini' => new GeminiDriver($apiKey, $models),
            default => throw new AiException("مزود غير مدعوم: {$driver}"),
        };
    }

    /**
     * Try providers in sort_order until one succeeds.
     *
     * @template T
     * @param  callable(AiProviderInterface, AiProvider): T  $callback
     * @return T
     */
    public function withFallback(callable $callback, ?string $purpose = null)
    {
        $providers = $this->activeProviders();
        $errors = [];
        $triedDrivers = [];
        $quotaHit = false;

        foreach ($providers as $provider) {
            if ($purpose === 'embedding' && ! $provider->modelFor('embedding')) {
                continue;
            }
            if ($purpose === 'transcription' && ! $provider->modelFor('transcription')) {
                continue;
            }

            try {
                $driver = $this->makeDriver($provider);
                $triedDrivers[] = $provider->driver;

                return $callback($driver, $provider);
            } catch (\Throwable $e) {
                $triedDrivers[] = $provider->driver;
                $errors[] = $provider->name.': '.$e->getMessage();
                if ($e instanceof Exceptions\AiQuotaExceededException) {
                    $quotaHit = true;
                }
            }
        }

        // Last resort: env default — only if that driver was not already tried
        try {
            $driver = $this->resolve(null, $purpose);
            if (! in_array($driver->driver(), $triedDrivers, true)) {
                return $callback($driver, new AiProvider([
                    'driver' => $driver->driver(),
                    'name' => $driver->driver(),
                    'models' => config('ai.models.'.$driver->driver(), []),
                ]));
            }
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
            if ($e instanceof Exceptions\AiQuotaExceededException) {
                $quotaHit = true;
            }
        }

        $summary = $errors === []
            ? 'لا يوجد مزود مفعّل صالح.'
            : implode(' | ', array_unique($errors));

        if ($quotaHit) {
            throw new Exceptions\AiQuotaExceededException(
                'حصة مزود الذكاء الاصطناعي الحالي مستنفدة. انتظر دقيقة أو فعّل مزوداً احتياطياً (OpenAI) من لوحة التحكم، أو غيّر موديل Gemini / فعّل الفوترة على Google AI Studio. التفاصيل: '.$summary
            );
        }

        throw new AiException('فشلت جميع محاولات مزودي الذكاء الاصطناعي: '.$summary);
    }
}
