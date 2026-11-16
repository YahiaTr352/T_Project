<?php

namespace App\Services\Chat;

use App\Models\ChatBlockedWord;
use App\Services\Chat\DTO\ContentVerdict;
use App\Support\Arabic\TextNormalizer;

/**
 * فحص محتوى الرسائل قبل إرسالها: كلمات ممنوعة، روابط، ومؤشرات سبام.
 *
 * ثلاثة مستويات للإجراء:
 *   block → يمنع الإرسال نهائياً
 *   mask  → يستبدل الكلمة بـ *** ويستمر الإرسال
 *   warn  → تمرّ الرسالة وتُسجَّل مخالفة على المُرسِل
 */
class ChatFilterService
{
    /**
     * الحروف المتكافئة في الكتابة العربية (تُبنى أنماط المطابقة منها).
     */
    protected const EQUIVALENTS = [
        'ا' => 'اأإآٱ',
        'ي' => 'يىئی',
        'و' => 'وؤۆ',
        'ه' => 'هةۃ',
        'ك' => 'كک',
        'ة' => 'هةۃ',
        'ت' => 'تٱ',
    ];

    /**
     * محارف مسموح بها بين حروف الكلمة (تحايل كتابي).
     */
    protected const SEPARATORS = '\p{Mn}\x{0640}\s\-_ـ';

    /**
     * @var array<int, array{word: string, severity: string}>|null
     */
    protected ?array $wordsCache = null;

    public function __construct(
        protected ChatSettingsService $settings
    ) {}

    /**
     * الفحص الكامل لمحتوى رسالة.
     */
    public function evaluate(?string $text): ContentVerdict
    {
        $text = trim((string) $text);

        if ($text === '') {
            return ContentVerdict::allow();
        }

        if (! $this->settings->bool('chat.filter.enabled', true)) {
            return ContentVerdict::allow();
        }

        $flags = [];
        $matched = [];

        // ── 1. الكلمات الممنوعة ──────────────────────────────────
        $hits = $this->matchBlockedWords($text);

        if ($hits !== []) {
            $matched = array_values(array_unique(array_map(fn ($h) => $h['word'], $hits)));
            $severities = array_unique(array_column($hits, 'severity'));

            if (in_array('block', $severities, true)) {
                return ContentVerdict::block(
                    'رسالتك تحتوي على كلمات غير مسموح بها، وتمنع إرسالها. راجع قوانين المحادثة.',
                    $matched,
                    $flags
                );
            }

            if (in_array('mask', $severities, true) && $this->settings->bool('chat.filter.mask_words', true)) {
                return ContentVerdict::mask(
                    'تم إخفاء بعض الكلمات في رسالتك لأنها غير مناسبة لقوانين المحادثة.',
                    $matched,
                    $this->maskText($text, $hits),
                    $flags
                );
            }

            if (in_array('warn', $severities, true)) {
                return ContentVerdict::warn(
                    'تم تسجيل تحذير على رسالتك لمخالفة قوانين المحادثة.',
                    $matched,
                    $flags
                );
            }
        }

        // ── 2. الروابط الخارجية ─────────────────────────────────
        $links = $this->extractLinks($text);

        if ($links !== [] && $this->settings->bool('chat.filter.links', false)) {
            $flags[] = 'link';

            if ($this->hasDisallowedLink($links)) {
                $action = $this->settings->get('chat.filter.link_action', 'warn');
                $message = 'روابط خارجية غير مسموح بها في هذه المحادثة.';

                return $action === 'block'
                    ? ContentVerdict::block($message, $matched, $flags)
                    : ContentVerdict::warn($message, $matched, $flags);
            }
        }

        // ── 3. مؤشرات السبام ─────────────────────────────────────
        $spam = $this->detectSpam($text);

        if ($spam !== []) {
            $flags = array_merge($flags, $spam);

            return ContentVerdict::warn(
                'تم تسجيل تحذير على رسالتك لاحتوائها على نمط غير طبيعي.',
                $matched,
                $flags
            );
        }

        return new ContentVerdict(
            ContentVerdict::ACTION_ALLOW,
            '',
            $matched,
            $flags
        );
    }

    /**
     * كشف الكلمات الممنوعة داخل النص.
     *
     * @return array<int, array{word: string, severity: string}>
     */
    public function matchBlockedWords(string $text): array
    {
        if (! $this->settings->bool('chat.filter.blocked_words', true)) {
            return [];
        }

        $hits = [];

        foreach ($this->activeWords() as $entry) {
            $word = $entry['word'];

            if ($word === '') {
                continue;
            }

            if ($this->matchesWord($text, $word)) {
                $hits[] = $entry;
            }
        }

        return $hits;
    }

    /**
     * استبدال الكلمات الضبط بـ *** مع الحفاظ على بقية النص.
     *
     * @param  array<int, array{word: string, severity: string}>  $hits
     */
    public function maskText(string $text, array $hits): string
    {
        foreach ($hits as $hit) {
            if ($hit['severity'] !== 'mask') {
                continue;
            }

            $pattern = $this->buildPattern($hit['word']);

            if ($pattern !== null) {
                $text = preg_replace($pattern, '***', $text) ?? $text;
            }
        }

        return $text;
    }

    /**
     * @return array<int, array{word: string, severity: string}>
     */
    protected function activeWords(): array
    {
        if ($this->wordsCache !== null) {
            return $this->wordsCache;
        }

        $this->wordsCache = ChatBlockedWord::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['word', 'severity'])
            ->map(fn (ChatBlockedWord $item) => [
                'word' => trim((string) $item->word),
                'severity' => (string) $item->severity,
            ])
            ->values()
            ->all();

        return $this->wordsCache;
    }

    public function flushWordsCache(): void
    {
        $this->wordsCache = null;
    }

    /**
     * مطابقة كلمة داخل نص مع مراعاة التحايل الكتابي.
     */
    protected function matchesWord(string $text, string $word): bool
    {
        $pattern = $this->buildPattern($word);

        if ($pattern !== null && preg_match($pattern, $text)) {
            return true;
        }

        // مطابقة احتياطية على النص المطبّع (يكشف الفروق بين الألفات والياء)
        return TextNormalizer::contains($text, $word);
    }

    /**
     * بناء نمط منتظم مرن للكلمة (يسمح بحروف بديلة وفواصل بين الحروف).
     */
    protected function buildPattern(string $word): ?string
    {
        $normalized = TextNormalizer::normalize($word);

        if ($normalized === '') {
            return null;
        }

        $letters = preg_split('//u', $normalized, -1, PREG_SPLIT_NO_EMPTY);

        if (! $letters) {
            return null;
        }

        $parts = [];
        $last = count($letters) - 1;

        foreach ($letters as $index => $letter) {
            $class = self::EQUIVALENTS[$letter] ?? $letter;
            $parts[] = '['.preg_quote($class, '/').']';

            // الفواصل مسموحة بين الحروف فقط (لا بعد آخر حرف) للحفاظ على المسافات
            if ($index < $last) {
                $parts[] = '['.self::SEPARATORS.']*';
            }
        }

        return '/(?:'.implode('', $parts).')/u';
    }

    /**
     * استخراج الروابط من النص.
     *
     * @return array<int, string>
     */
    public function extractLinks(string $text): array
    {
        $pattern = '/(?:https?:\/\/|www\.)[^\s<>"\']+/iu';

        preg_match_all($pattern, $text, $matches);

        return array_values(array_unique($matches[0] ?? []));
    }

    /**
     * هل يحتوي النص على رابط خارج النطاقات المسموح بها؟
     *
     * @param  array<int, string>  $links
     */
    protected function hasDisallowedLink(array $links): bool
    {
        $allowed = $this->settings->array('chat.filter.allowed_link_domains', []);

        if ($allowed === []) {
            return true;
        }

        $allowed = array_map('strtolower', array_map('trim', $allowed));

        foreach ($links as $link) {
            $host = strtolower((string) parse_url(
                str_starts_with(strtolower($link), 'http') ? $link : 'https://'.$link,
                PHP_URL_HOST
            ));

            if ($host === '') {
                return true;
            }

            $matched = false;

            foreach ($allowed as $domain) {
                if ($domain === '') {
                    continue;
                }

                if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                    $matched = true;
                    break;
                }
            }

            if (! $matched) {
                return true;
            }
        }

        return false;
    }

    /**
     * كشف مؤشرات السبام البسيطة.
     *
     * @return array<int, string>
     */
    protected function detectSpam(string $text): array
    {
        if (! $this->settings->bool('chat.filter.spam_patterns', true)) {
            return [];
        }

        $flags = [];

        $minRepeated = (int) config('chat.spam.min_repeated_chars', 12);

        if ($minRepeated > 0 && preg_match('/(.)\1{'.($minRepeated - 1).',}/u', $text)) {
            $flags[] = 'repeated_chars';
        }

        $maxMentions = (int) $this->settings->int('chat.filter.max_mentions_per_message', config('chat.spam.max_mentions', 10));

        if ($maxMentions > 0 && substr_count($text, '@') > $maxMentions) {
            $flags[] = 'too_many_mentions';
        }

        $maxLinks = (int) config('chat.spam.max_links', 3);

        if ($maxLinks > 0 && count($this->extractLinks($text)) > $maxLinks) {
            $flags[] = 'too_many_links';
        }

        return $flags;
    }
}
