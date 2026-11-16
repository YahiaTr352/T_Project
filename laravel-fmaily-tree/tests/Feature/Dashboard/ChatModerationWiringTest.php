<?php

namespace Tests\Feature\Dashboard;

use App\Livewire\Admin\Chat\ChatAuditLog;
use App\Livewire\Admin\Chat\ChatBans;
use App\Livewire\Admin\Chat\ChatBlockedWords;
use App\Livewire\Admin\Chat\ChatConversationDetail;
use App\Livewire\Admin\Chat\ChatConversations;
use App\Livewire\Admin\Chat\ChatOverview;
use App\Livewire\Admin\Chat\ChatReports;
use App\Livewire\Admin\Chat\ChatSettings;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

/**
 * يتحقق من أن كل توجيهات wire: في واجهات مكوّنات لوحة المراقبة
 * تشير إلى أعضاء موجودة فعلاً (يمنع أخطاء مثل wire:poll="render").
 */
class ChatModerationWiringTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: class-string}>
     */
    public static function componentViews(): array
    {
        return [
            'chat-overview' => ['chat-overview', ChatOverview::class],
            'chat-conversations' => ['chat-conversations', ChatConversations::class],
            'chat-conversation-detail' => ['chat-conversation-detail', ChatConversationDetail::class],
            'chat-reports' => ['chat-reports', ChatReports::class],
            'chat-bans' => ['chat-bans', ChatBans::class],
            'chat-blocked-words' => ['chat-blocked-words', ChatBlockedWords::class],
            'chat-settings' => ['chat-settings', ChatSettings::class],
            'chat-audit-log' => ['chat-audit-log', ChatAuditLog::class],
        ];
    }

    /**
     * @dataProvider componentViews
     */
    public function test_wire_method_calls_reference_existing_methods(string $view, string $component): void
    {
        $methods = array_map(
            fn ($method) => $method->getName(),
            (new ReflectionClass($component))->getMethods(ReflectionMethod::IS_PUBLIC)
        );

        $html = $this->bladeSource($view);

        preg_match_all('/wire:(?:click|change|submit|blur|keyup)\s*=\s*"([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $html, $matches);

        foreach (array_unique($matches[1]) as $method) {
            $this->assertContains(
                $method,
                $methods,
                "[{$view}] استدعاء wire: على دالة غير موجودة: {$method}()"
            );
        }
    }

    /**
     * @dataProvider componentViews
     */
    public function test_wire_models_reference_existing_public_properties(string $view, string $component): void
    {
        $properties = array_map(
            fn ($property) => $property->getName(),
            (new ReflectionClass($component))->getProperties(ReflectionProperty::IS_PUBLIC)
        );
        $html = $this->bladeSource($view);

        preg_match_all('/wire:model[^"]*=\s*"([A-Za-z_][A-Za-z0-9_]*)/', $html, $matches);

        foreach (array_unique($matches[1]) as $property) {
            $this->assertContains(
                $property,
                $properties,
                "[{$view}] ربط wire:model لخاصية غير موجودة: \${$property}"
            );
        }
    }

    /**
     * @dataProvider componentViews
     */
    public function test_no_wire_directive_targets_the_render_method(string $view, string $component): void
    {
        // Livewire 3 يحذف "render" من قائمة الدوال القابلة للاستدعاء،
        // فاستدعاؤها عبر wire: يسبب MethodNotFoundException في الإنتاج.
        $html = $this->bladeSource($view);

        $this->assertDoesNotMatchRegularExpression(
            '/wire:[a-z:.-]*\s*=\s*"render/',
            $html,
            "[{$view}] لا يجوز استدعاء render عبر wire: — استخدم wire:poll.Xs بدون قيمة"
        );
    }

    /**
     * @dataProvider componentViews
     */
    public function test_poll_directives_do_not_pass_a_method_name(string $view, string $component): void
    {
        $html = $this->bladeSource($view);

        preg_match_all('/wire:poll(?:\.[^"\s=]+)*\s*=\s*"([^"]*)"/', $html, $matches);

        foreach ($matches[1] as $value) {
            $this->assertSame(
                '',
                trim($value),
                "[{$view}] wire:poll لا يقبل اسم دالة — استخدم wire:poll.Xs بدون قيمة"
            );
        }
    }

    protected function bladeSource(string $view): string
    {
        $path = __DIR__.'/../../../resources/views/livewire/admin/chat/'.$view.'.blade.php';

        $this->assertFileExists($path, "الواجهة غير موجودة: {$path}");

        return (string) file_get_contents($path);
    }
}
