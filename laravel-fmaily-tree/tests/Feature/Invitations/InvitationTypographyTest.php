<?php

namespace Tests\Feature\Invitations;

use App\Models\CreativeTemplate;
use App\Models\Notification;
use App\Services\Invitations\InvitationCardRenderer;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvitationTypographyTest extends TestCase
{
    public function test_fonts_and_weights_generate_distinct_cards_and_cache_entries(): void
    {
        Storage::fake('public');
        $image = imagecreatetruecolor(1080, 1350);
        imagefill($image, 0, 0, imagecolorallocate($image, 30, 80, 60));
        ob_start();
        imagepng($image);
        $background = ob_get_clean();
        imagedestroy($image);
        Storage::disk('public')->put('test-background.png', $background);

        $renderer = app(InvitationCardRenderer::class);
        $paths = [];
        $hashes = [];
        foreach (['amiri', 'tajawal'] as $family) {
            foreach (['regular', 'bold'] as $weight) {
                $style = ['font_family' => $family, 'font_weight' => $weight];
                $path = $renderer->render('test-background.png', 'دعوة عائلية', 'أهلاً بكم في لقاء العائلة', 'same-key', $style);
                $this->assertNotNull($path);
                Storage::disk('public')->assertExists($path);
                $this->assertSame($path, $renderer->render('test-background.png', 'دعوة عائلية', 'أهلاً بكم في لقاء العائلة', 'same-key', $style));
                $paths[] = $path;
                $hashes[] = md5(Storage::disk('public')->get($path));
            }
        }

        $this->assertCount(4, array_unique($paths));
        $this->assertCount(4, array_unique($hashes));
    }

    public function test_creative_preview_and_delivery_forward_the_same_typography(): void
    {
        $renderer = new class extends InvitationCardRenderer {
            public array $calls = [];

            public function render(string $sourcePath, string $title, string $body, string $cacheKey, array $typography = []): ?string
            {
                $this->calls[] = compact('sourcePath', 'title', 'body', 'typography');

                return 'notifications/cards/test.jpg';
            }
        };
        $style = ['font_family' => 'tajawal', 'font_weight' => 'bold'];
        $creative = new CreativeTemplate(['image_path' => 'creative.jpg']);
        $creative->id = 7;
        $notification = new Notification([
            'title' => 'دعوة', 'media_path' => 'creative.jpg',
            'targeting_filters' => ['creative_id' => 7] + $style,
        ]);
        $notification->id = 1;

        $this->assertNotNull($renderer->renderPreview($creative, 'دعوة', 'مرحباً أحمد', $style));
        $this->assertNotNull($renderer->renderForNotification($notification, 'مرحباً أحمد', null, '+966500000001'));
        foreach (['sourcePath', 'title', 'body'] as $key) {
            $this->assertSame($renderer->calls[0][$key], $renderer->calls[1][$key]);
        }
        foreach ($style as $key => $value) {
            $this->assertSame($value, $renderer->calls[1]['typography'][$key]);
        }

        // Ordinary attachments must remain untouched.
        $notification->targeting_filters = $style;
        $this->assertNull($renderer->renderForNotification($notification, 'نص الرسالة', null, '+966500000001'));
        $this->assertCount(2, $renderer->calls);
    }
}
