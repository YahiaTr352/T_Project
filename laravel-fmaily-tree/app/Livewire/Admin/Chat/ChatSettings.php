<?php

namespace App\Livewire\Admin\Chat;

use App\Services\Chat\ChatSettingsService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * إعدادات نظام المحادثات العامة.
 *
 * ملاحظة: مفاتيح الإعدادات تحتوي نقاطاً (مثل chat.max_message_length)،
 * وعند Livewire تُترجم النقاط إلى مسارات متداخلة، لذلك تُستخدم
 * بنية متداخلة في النموذج (form.chat.max_message_length).
 */
class ChatSettings extends Component
{
    use AuthorizesRequests;

    /**
     * المفاتيح الرقمية (قابلة للتعديل من اللوحة).
     */
    public const NUMERIC_KEYS = [
        'chat.max_message_length',
        'chat.slow_mode_seconds',
        'chat.attachment_max_size_kb',
        'chat.participant_rate_limit_per_minute',
        'chat.retention_days',
        'chat.filter.max_mentions_per_message',
        'chat.escalation.warnings_before_flag',
        'chat.escalation.auto_ban_after_reports',
        'chat.escalation.auto_ban_duration_days',
        'chat.moderation.ai_sample_rate',
        'chat.moderation.ai_threshold',
    ];

    /**
     * المفاتيح المنطقية (تبديل).
     */
    public const BOOLEAN_KEYS = [
        'chat.enabled',
        'chat.allow_attachments',
        'chat.filter.enabled',
        'chat.filter.blocked_words',
        'chat.filter.mask_words',
        'chat.filter.links',
        'chat.filter.spam_patterns',
        'chat.moderation.ai_enabled',
        'chat.moderation.ai_reported_only',
    ];

    /**
     * @var array<string, mixed>
     */
    public array $form = [];

    public string $filterLinkAction = 'warn';

    public string $escalationAutoBanScope = 'conversation';

    public function mount(): void
    {
        $this->authorize('chat.moderation.settings');

        $this->loadSettings();
    }

    public function loadSettings(): void
    {
        $settings = app(ChatSettingsService::class);

        $this->form = [];

        foreach (self::NUMERIC_KEYS as $key) {
            $this->setNested($key, (string) $settings->get($key, ''));
        }

        foreach (self::BOOLEAN_KEYS as $key) {
            $this->setNested($key, $settings->bool($key, false));
        }

        $this->filterLinkAction = (string) $settings->get('chat.filter.link_action', 'warn');
        $this->escalationAutoBanScope = (string) $settings->get('chat.escalation.auto_ban_scope', 'conversation');
    }

    public function save(): void
    {
        $this->authorize('chat.moderation.settings');

        $settings = app(ChatSettingsService::class);

        $numeric = [];

        foreach (self::NUMERIC_KEYS as $key) {
            $value = $this->getNested($key);
            $numeric[$key] = is_numeric($value) ? (string) (int) $value : '0';
        }

        $booleans = [];

        foreach (self::BOOLEAN_KEYS as $key) {
            $booleans[$key] = (bool) $this->getNested($key);
        }

        $settings->setMany($numeric, 'text');
        $settings->setMany($booleans, 'boolean');
        $settings->set('chat.filter.link_action', $this->filterLinkAction, 'text');
        $settings->set('chat.escalation.auto_ban_scope', $this->escalationAutoBanScope, 'text');

        $settings->flush();

        $this->loadSettings();

        session()->flash('success', 'تم حفظ إعدادات المحادثات بنجاح.');

        $this->dispatch('chat-moderation-flash', message: 'تم حفظ الإعدادات.', type: 'success');
    }

    public function resetToDefaults(): void
    {
        $this->authorize('chat.moderation.settings');

        $settings = app(ChatSettingsService::class);

        $settings->set('chat.enabled', '1', 'boolean');
        $settings->set('chat.allow_attachments', '1', 'boolean');
        $settings->set('chat.max_message_length', '2000', 'text');
        $settings->set('chat.slow_mode_seconds', '0', 'text');
        $settings->set('chat.participant_rate_limit_per_minute', '60', 'text');
        $settings->set('chat.filter.enabled', '1', 'boolean');
        $settings->set('chat.filter.blocked_words', '1', 'boolean');
        $settings->set('chat.filter.mask_words', '1', 'boolean');
        $settings->set('chat.filter.links', '0', 'boolean');
        $settings->set('chat.filter.max_mentions_per_message', '10', 'text');
        $settings->set('chat.escalation.warnings_before_flag', '3', 'text');
        $settings->set('chat.escalation.auto_ban_after_reports', '0', 'text');
        $settings->set('chat.escalation.auto_ban_duration_days', '7', 'text');
        $settings->set('chat.moderation.ai_enabled', '0', 'boolean');
        $settings->set('chat.moderation.ai_reported_only', '1', 'boolean');
        $settings->set('chat.moderation.ai_sample_rate', '0', 'text');
        $settings->set('chat.moderation.ai_threshold', '0.7', 'text');

        $settings->flush();

        $this->loadSettings();

        session()->flash('success', 'تمت استعادة الإعدادات الافتراضية.');

        $this->dispatch('chat-moderation-flash', message: 'تمت الاستعادة.', type: 'success');
    }

    // ── مساعدات المسارات المتداخلة ───────────────────────────────

    protected function getNested(string $dottedKey): mixed
    {
        $value = $this->form;

        foreach (explode('.', $dottedKey) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    protected function setNested(string $dottedKey, mixed $value): void
    {
        $segments = explode('.', $dottedKey);
        $last = array_pop($segments);

        $target = &$this->form;

        foreach ($segments as $segment) {
            if (! isset($target[$segment]) || ! is_array($target[$segment])) {
                $target[$segment] = [];
            }

            $target = &$target[$segment];
        }

        $target[$last] = $value;
    }
}
