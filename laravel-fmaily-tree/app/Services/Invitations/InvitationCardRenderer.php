<?php

namespace App\Services\Invitations;

use App\Models\InvitationBackground;
use App\Models\CreativeTemplate;
use App\Models\Notification;
use ArPHP\I18N\Arabic as ArabicGlyphs;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InvitationCardRenderer
{
    public const WIDTH = 1080;
    public const HEIGHT = 1350;
    public const MAX_WIDTH = 920;

    protected function fonts(string $family = 'amiri'): array
    {
        $name = $family === 'tajawal' ? 'Tajawal' : 'Amiri';
        return [
            'bold' => public_path('fonts/' . $name . '-Bold.ttf'),
            'regular' => public_path('fonts/' . $name . '-Regular.ttf'),
        ];
    }

    protected function glyphs(): ArabicGlyphs
    {
        static $instance = null;
        if ($instance) {
            return $instance;
        }
        if (! class_exists(ArabicGlyphs::class, false)) {
            require_once app_path('Support/Arabic/Arabic.php');
        }
        $instance = new ArabicGlyphs();

        return $instance;
    }

    public function renderForNotification(
        Notification $notification,
        string $personalizedBody,
        ?int $personId,
        string $toNumber
    ): ?string {
        try {
            $filters = is_array($notification->targeting_filters) ? $notification->targeting_filters : [];
            if (trim((string) $notification->body) === '') {
                return null;
            }
            $backgroundId = $filters['background_id'] ?? null;
            if (! $backgroundId && empty($filters['creative_id'])) {
                return null;
            }
            $source = (string) ($notification->media_path ?: '');
            if ($source === '') {
                return null;
            }
            $key = 'n' . $notification->id . '_p' . ($personId ?: substr(md5($toNumber), 0, 10));
            $key .= '_' . md5((string) $notification->title . "\n" . $personalizedBody);

            return $this->render($source, (string) ($notification->title ?: ''), $personalizedBody, $key, $filters);
        } catch (\Throwable $e) {
            Log::channel('single')->error('[InvitationCard] renderForNotification failed: ' . $e->getMessage());

            return null;
        }
    }

    public function renderPreview(InvitationBackground|CreativeTemplate $background, string $title, string $body, array $typography = []): ?string
    {
        try {
            $key = 'preview_' . $background->id . '_' . md5($background->image_path . "\n" . $title . "\n" . $body);

            return $this->render($background->image_path, $title, $body, $key, $typography);
        } catch (\Throwable $e) {
            Log::channel('single')->error('[InvitationCard] renderPreview failed: ' . $e->getMessage());

            return null;
        }
    }

    public function render(string $sourcePath, string $title, string $body, string $cacheKey, array $typography = []): ?string
    {
        // Only supported bundled fonts are resolved; legacy cards keep their title/body weights.
        $family = ($typography['font_family'] ?? null) === 'tajawal' ? 'tajawal' : 'amiri';
        $weight = in_array($typography['font_weight'] ?? null, ['regular', 'bold'], true)
            ? $typography['font_weight'] : null;
        $cacheKey .= '_' . md5($sourcePath . '|' . $family . '|' . ($weight ?? 'legacy'));
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '', $cacheKey) ?: md5($cacheKey);
        $relative = 'notifications/cards/' . $safeKey . '.jpg';

        try {
            $disk = Storage::disk('public');
            if ($disk->exists($relative)) {
                return $relative;
            }
            if (! $disk->exists($sourcePath)) {
                return null;
            }

            $fonts = $this->fonts($family);
            if (! file_exists($fonts['bold']) || ! file_exists($fonts['regular'])) {
                return null;
            }

            $image = @imagecreatefromstring($disk->get($sourcePath));
            if (! $image) {
                return null;
            }

            $canvas = $this->cover($image);
            imagedestroy($image);

            $this->overlay($canvas);
            $this->border($canvas);

            $title = $this->clean($title);
            $body = $this->clean($body);
            if ($title === '' && $body === '') {
                imagedestroy($canvas);

                return null;
            }

            $this->drawBlock($canvas, $fonts, $title, $body, $weight);

            if (! $disk->exists('notifications/cards')) {
                $disk->makeDirectory('notifications/cards');
            }
            $tmp = tempnam(sys_get_temp_dir(), 'invc') . '.jpg';
            imagejpeg($canvas, $tmp, 88);
            imagedestroy($canvas);
            $disk->putFileAs('notifications/cards', new \Illuminate\Http\File($tmp), basename($relative));
            @unlink($tmp);

            return $relative;
        } catch (\Throwable $e) {
            Log::channel('single')->error('[InvitationCard] render failed: ' . $e->getMessage());

            return null;
        }
    }

    protected function clean(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = str_replace(['*', '_', '~', '`'], '', $text);
        $lines = array_map('trim', explode("\n", $text));
        $lines = array_values(array_filter($lines, fn ($l) => $l !== ''));

        return implode("\n", $lines);
    }

    protected function cover($image)
    {
        $sw = imagesx($image);
        $sh = imagesy($image);
        $scale = max(self::WIDTH / max(1, $sw), self::HEIGHT / max(1, $sh));
        $dw = (int) round($sw * $scale);
        $dh = (int) round($sh * $scale);

        $resized = imagecreatetruecolor($dw, $dh);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $dw, $dh, $sw, $sh);

        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagecopy($canvas, $resized, (int) ((self::WIDTH - $dw) / 2), (int) ((self::HEIGHT - $dh) / 2), 0, 0, $dw, $dh);
        imagedestroy($resized);

        return $canvas;
    }

    protected function overlay($canvas): void
    {
        $dark = imagecolorallocatealpha($canvas, 0, 0, 0, 64);
        imagefilledrectangle($canvas, 0, 0, self::WIDTH, self::HEIGHT, $dark);
    }

    protected function border($canvas): void
    {
        $gold = imagecolorallocate($canvas, 201, 162, 75);
        for ($t = 0; $t < 3; $t++) {
            imagerectangle($canvas, 36 + $t, 36 + $t, self::WIDTH - 37 - $t, self::HEIGHT - 37 - $t, $gold);
        }
    }

    protected function shape(string $line): string
    {
        return $this->glyphs()->utf8Glyphs($line, 500);
    }

    protected function lineWidth(string $font, int $size, string $shapedLine): int
    {
        $box = imagettfbbox($size, 0, $font, $shapedLine);

        return (int) (($box[2] ?? 0) - ($box[0] ?? 0));
    }

    protected function wrap(string $font, int $size, string $text): array
    {
        $out = [];
        foreach (explode("\n", $text) as $paragraph) {
            $words = preg_split('/\s+/u', $paragraph, -1, PREG_SPLIT_NO_EMPTY);
            if (! $words) {
                continue;
            }
            $current = '';
            foreach ($words as $word) {
                $candidate = $current === '' ? $word : $current . ' ' . $word;
                if ($this->lineWidth($font, $size, $this->shape($candidate)) <= self::MAX_WIDTH) {
                    $current = $candidate;
                } else {
                    if ($current !== '') {
                        $out[] = $current;
                    }
                    $current = $word;
                }
            }
            if ($current !== '') {
                $out[] = $current;
            }
        }

        return $out;
    }

    protected function drawCentered($canvas, string $font, int $size, string $logicalLine, int $baselineY, bool $fakeBold = false): void
    {
        $shaped = $this->shape($logicalLine);
        $box = imagettfbbox($size, 0, $font, $shaped);
        $minX = $box[0] ?? 0;
        $maxX = $box[2] ?? 0;
        $x = (int) ((self::WIDTH - ($maxX - $minX)) / 2 - $minX);

        $white = imagecolorallocate($canvas, 255, 255, 255);
        $shadow = imagecolorallocatealpha($canvas, 0, 0, 0, 90);

        $draw = function ($dx, $dy, $color) use ($canvas, $font, $size, $shaped, $x, $baselineY) {
            imagettftext($canvas, $size, 0, $x + $dx, $baselineY + $dy, $color, $font, $shaped);
        };

        $draw(3, 3, $shadow);
        $draw(0, 0, $white);
        if ($fakeBold) {
            $draw(1, 0, $white);
        }
    }

    protected function drawBlock($canvas, array $fonts, string $title, string $body, ?string $weight = null): void
    {
        $titleFont = $fonts[$weight ?? 'bold'];
        $bodyFont = $fonts[$weight ?? 'regular'];
        $titleSize = 78;
        $bodySize = 44;

        $titleLines = $title === '' ? [] : $this->wrap($titleFont, $titleSize, $title);
        $bodyLines = $body === '' ? [] : array_slice($this->wrap($bodyFont, $bodySize, $body), 0, 14);

        $titleStep = 118;
        $bodyStep = 74;
        $gap = 70;

        $total = count($titleLines) * $titleStep + (count($titleLines) && count($bodyLines) ? $gap : 0) + count($bodyLines) * $bodyStep;
        $y = (int) ((self::HEIGHT - $total) / 2) + 60;

        foreach ($titleLines as $line) {
            $y += $titleStep;
            $this->drawCentered($canvas, $titleFont, $titleSize, $line, $y, $weight === null);
        }

        if (count($titleLines) && count($bodyLines)) {
            $y += $gap;
        }

        foreach ($bodyLines as $line) {
            $y += $bodyStep;
            $this->drawCentered($canvas, $bodyFont, $bodySize, $line, $y);
        }
    }
}
