<?php

namespace App\Services\Chat\DTO;

/**
 * نتيجة فحص محتوى رسالة قبل إرسالها.
 */
class ContentVerdict
{
    public const ACTION_ALLOW = 'allow';

    public const ACTION_BLOCK = 'block';

    public const ACTION_MASK = 'mask';

    public const ACTION_WARN = 'warn';

    /**
     * @param  array<int, string>  $matchedWords  الكلمات الممنوعة التي تم ضبطها
     * @param  array<int, string>  $flags  أسباب إضافية (رابط / سبام / منشن)
     */
    public function __construct(
        public readonly string $action = self::ACTION_ALLOW,
        public readonly string $message = '',
        public readonly array $matchedWords = [],
        public readonly array $flags = [],
        public readonly ?string $cleanText = null,
    ) {}

    public static function allow(): self
    {
        return new self(self::ACTION_ALLOW);
    }

    public static function block(string $message, array $matchedWords = [], array $flags = []): self
    {
        return new self(self::ACTION_BLOCK, $message, $matchedWords, $flags);
    }

    public static function mask(string $message, array $matchedWords, string $cleanText, array $flags = []): self
    {
        return new self(self::ACTION_MASK, $message, $matchedWords, $flags, $cleanText);
    }

    public static function warn(string $message, array $matchedWords = [], array $flags = []): self
    {
        return new self(self::ACTION_WARN, $message, $matchedWords, $flags);
    }

    public function isAllowed(): bool
    {
        return $this->action === self::ACTION_ALLOW;
    }

    public function isBlocked(): bool
    {
        return $this->action === self::ACTION_BLOCK;
    }

    public function needsMasking(): bool
    {
        return $this->action === self::ACTION_MASK && $this->cleanText !== null;
    }

    public function hasWarnings(): bool
    {
        return $this->action === self::ACTION_WARN;
    }

    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'message' => $this->message,
            'matched_words' => $this->matchedWords,
            'flags' => $this->flags,
        ];
    }
}
