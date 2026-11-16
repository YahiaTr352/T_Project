<?php

namespace App\Services\AI;

use App\Models\AiMessage;
use App\Services\AI\Exceptions\AiException;
use App\Support\AiSettings;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TtsService
{
    public function __construct(
        private readonly AiManager $aiManager,
        private readonly AiFeatureGate $gate,
        private readonly AiUsageTracker $usageTracker,
    ) {
    }

    /**
     * Synthesize audio for text or an existing AI message on demand.
     * Caches audio on disk so identical requests cost 0 tokens.
     *
     * @return array{url: string, cached: bool, duration_approx: int, voice: string}
     */
    public function synthesize(
        string $text,
        ?AiMessage $message = null,
        ?string $voice = null,
        ?int $userId = null,
        ?string $sessionKey = null,
        ?string $ip = null,
    ): array {
        $this->gate->authorize('tts', $userId, $ip);

        $cleanText = $this->prepareTextForSpeech($text);
        if ($cleanText === '') {
            throw new AiException('النص فارغ ولا يمكن تحويله إلى صوت.');
        }

        $voice = $voice ?: AiSettings::ttsVoice();
        $model = AiSettings::featureModel('tts') ?: config('ai.models.openai.tts', 'tts-1');

        // Check message cache first
        if ($message && filled(data_get($message->meta, 'audio_url'))) {
            $existingUrl = data_get($message->meta, 'audio_url');
            $relPath = Str::after($existingUrl, '/storage/');
            if (Storage::disk('public')->exists($relPath)) {
                return [
                    'url' => $existingUrl,
                    'cached' => true,
                    'duration_approx' => $this->estimateDurationSec($cleanText),
                    'voice' => data_get($message->meta, 'audio_voice', $voice),
                ];
            }
        }

        // Cache key based on md5 hash of (text + voice + model)
        $hash = md5("{$cleanText}|{$voice}|{$model}");
        $filename = "ai_audio/{$hash}.mp3";

        if (Storage::disk('public')->exists($filename)) {
            $url = asset("storage/{$filename}");
            if ($message) {
                $meta = $message->meta ?? [];
                $meta['audio_url'] = $url;
                $meta['audio_voice'] = $voice;
                $message->meta = $meta;
                $message->save();
            }

            return [
                'url' => $url,
                'cached' => true,
                'duration_approx' => $this->estimateDurationSec($cleanText),
                'voice' => $voice,
            ];
        }

        // Generate via OpenAI TTS driver
        $driver = $this->aiManager->resolve(null, 'tts');
        if (! method_exists($driver, 'textToSpeech')) {
            throw new AiException('المزود المحدد لا يدعم توليد الصوت.');
        }

        $audioBytes = $driver->textToSpeech($cleanText, [
            'model' => $model,
            'voice' => $voice,
        ]);

        Storage::disk('public')->put($filename, $audioBytes);
        $url = asset("storage/{$filename}");

        // Record usage for budget tracking
        $this->usageTracker->recordTts($model, mb_strlen($cleanText), $userId, $sessionKey, [
            'voice' => $voice,
            'message_id' => $message?->id,
        ]);

        if ($message) {
            $meta = $message->meta ?? [];
            $meta['audio_url'] = $url;
            $meta['audio_voice'] = $voice;
            $meta['audio_generated_at'] = now()->toIso8601String();
            $message->meta = $meta;
            $message->save();
        }

        return [
            'url' => $url,
            'cached' => false,
            'duration_approx' => $this->estimateDurationSec($cleanText),
            'voice' => $voice,
        ];
    }

    /**
     * Clean Markdown formatting, URLs, and symbols for natural spoken Arabic.
     */
    /**
     * Clean Markdown formatting, URLs, and convert digits into fluent spoken Arabic words.
     */
    private function prepareTextForSpeech(string $text): string
    {
        // Remove URLs
        $text = preg_replace('/https?:\/\/\S+/u', '', $text) ?? $text;
        // Remove citations like [مصدر score=0.8] or [1]
        $text = preg_replace('/\[[^\]]*\]/u', '', $text) ?? $text;
        // Remove record ID parentheses like (#309) or (#123) or (معرف: #12)
        $text = preg_replace('/\s*\((?:معرف\s*|شخص\s*)?#?\d+\)/u', '', $text) ?? $text;
        // Remove trailing source lines like "المصدر: ..." or "المصادر: ..."
        $text = preg_replace('/(?:\n|^)\s*(?:\*\*)?(?:المصدر|المصادر)(?:\*\*)?\s*:[^\n]*/u', '', $text) ?? $text;
        // Remove markdown headings, bold, asterisks, hashtags
        $text = preg_replace('/[#*\x60_~]/u', '', $text) ?? $text;
        // Remove bullet dashes at start of line
        $text = preg_replace('/^[-•\d+\.]\s*/mu', '', $text) ?? $text;

        // Convert Eastern Arabic numerals to standard digits first
        $eastern = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
        $western = ['0','1','2','3','4','5','6','7','8','9'];
        $text = str_replace($eastern, $western, $text);

        // Convert numbers (years, dates, counts) to Arabic words so TTS reads them fluently
        $text = preg_replace_callback('/\b\d{1,5}\b/u', function ($matches) {
            return $this->numberToArabicWords((int) $matches[0]);
        }, $text);

        // Normalize whitespace
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim(mb_substr($text, 0, 4000));
    }

    private function numberToArabicWords(int $number): string
    {
        if ($number === 0) return 'صفر';

        $ones = [
            1 => 'واحد', 2 => 'اثنان', 3 => 'ثلاثة', 4 => 'أربعة', 5 => 'خمسة',
            6 => 'ستة', 7 => 'سبعة', 8 => 'ثمانية', 9 => 'تسعة', 10 => 'عشرة',
            11 => 'أحد عشر', 12 => 'اثنا عشر', 13 => 'ثلاثة عشر', 14 => 'أربعة عشر',
            15 => 'خمسة عشر', 16 => 'ستة عشر', 17 => 'سبعة عشر', 18 => 'ثمانية عشر',
            19 => 'تسعة عشر',
        ];

        $tens = [
            20 => 'عشرون', 30 => 'ثلاثون', 40 => 'أربعون', 50 => 'خمسون',
            60 => 'ستون', 70 => 'سبعون', 80 => 'ثمانون', 90 => 'تسعون',
        ];

        $hundreds = [
            100 => 'مائة', 200 => 'مائتان', 300 => 'ثلاثمائة', 400 => 'أربعمائة',
            500 => 'خمسمائة', 600 => 'ستمائة', 700 => 'سبعمائة', 800 => 'ثمانمائة',
            900 => 'تسعمائة',
        ];

        if ($number < 20) {
            return $ones[$number];
        }

        if ($number < 100) {
            $ten = (int) (floor($number / 10) * 10);
            $one = $number % 10;
            return $one > 0 ? "{$ones[$one]} و{$tens[$ten]}" : $tens[$ten];
        }

        if ($number < 1000) {
            $hundred = (int) (floor($number / 100) * 100);
            $remainder = $number % 100;
            return $remainder > 0 ? "{$hundreds[$hundred]} و" . $this->numberToArabicWords($remainder) : $hundreds[$hundred];
        }

        if ($number < 2000) {
            $remainder = $number % 1000;
            return $remainder > 0 ? "ألف و" . $this->numberToArabicWords($remainder) : 'ألف';
        }

        if ($number < 3000) {
            $remainder = $number % 1000;
            return $remainder > 0 ? "ألفان و" . $this->numberToArabicWords($remainder) : 'ألفان';
        }

        if ($number < 11000) {
            $thousands = (int) floor($number / 1000);
            $remainder = $number % 1000;
            $word = $ones[$thousands] . ' آلاف';
            return $remainder > 0 ? "{$word} و" . $this->numberToArabicWords($remainder) : $word;
        }

        return (string) $number;
    }

    private function estimateDurationSec(string $text): int
    {
        $words = count(preg_split('/\s+/u', trim($text)) ?: []);
        return max(3, (int) round(($words / 130) * 60));
    }
}
