<?php

namespace App\Services\AI\Support;

use App\Services\AI\Exceptions\AiException;
use App\Services\AI\Exceptions\AiQuotaExceededException;
use Illuminate\Http\Client\Response;

class ProviderHttpError
{
    public static function throw(string $providerLabel, Response $response, string $action = 'الطلب'): void
    {
        $status = $response->status();
        $json = $response->json();
        $rawMessage = is_array($json)
            ? (string) (data_get($json, 'error.message') ?: data_get($json, 'error') ?: '')
            : '';

        if ($rawMessage === '') {
            $rawMessage = mb_substr((string) $response->body(), 0, 400);
        }

        $lower = mb_strtolower($rawMessage);

        if (
            $status === 429
            || str_contains($lower, 'resource_exhausted')
            || str_contains($lower, 'quota')
            || str_contains($lower, 'rate limit')
            || str_contains($lower, 'rate_limit')
        ) {
            throw new AiQuotaExceededException(
                "تم استنفاد حصة {$providerLabel} حالياً (حد الطلبات/التوكنز). انتظر حوالي دقيقة ثم أعد المحاولة، أو غيّر الموديل، أو فعّل مزوداً آخر مثل OpenAI من لوحة التحكم، أو راجع الفوترة على حساب المزود."
            );
        }

        if ($status === 401 || $status === 403) {
            throw new AiException(
                "مفتاح API لـ {$providerLabel} غير صالح أو بدون صلاحية. راجع المفتاح من لوحة إعدادات الذكاء الاصطناعي."
            );
        }

        throw new AiException(
            "فشل {$action} عبر {$providerLabel} (HTTP {$status}). ".mb_substr($rawMessage, 0, 220)
        );
    }
}
