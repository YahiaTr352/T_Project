<?php

namespace App\Services\Invitations;

use App\Exceptions\InvitationException;
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
    private const LAYOUT_RENDER_VERSION = 4;
    private const ITALIC_SHEAR = .32;

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
            if (! empty($filters['creative_card_rendered']) || isset($filters['creative_design_mode'])) {
                return null;
            }
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

    public function renderTemplatePreview(CreativeTemplate $template, bool $allowOverflow = true): ?string
    {
        if ($template->designMode() !== CreativeTemplate::MODE_OVERLAY || ! $template->invitation_type || ! $template->image_path) {
            return null;
        }
        $preset = app(InvitationMessageService::class)->templateByKey($template->invitation_type);
        if (! $preset) {
            return null;
        }
        if ($allowOverflow && ! $template->text_layout && $template->design_mode === null) {
            $template = clone $template;
            $template->design_mode = CreativeTemplate::MODE_OVERLAY;
        }

        return $this->renderPreview($template, $preset['title'], $preset['body'], [
            'template_vars' => CreativeTextLayout::placeholders($template->invitation_type),
            'allow_placeholder_overflow' => $allowOverflow,
        ]);
    }

    public function renderPreview(InvitationBackground|CreativeTemplate $background, string $title, string $body, array $typography = []): ?string
    {
        try {
            if ($background instanceof CreativeTemplate && $background->invitation_type !== null) {
                $typography['invitation_type'] = $background->invitation_type;
                if ($background->design_mode === CreativeTemplate::MODE_OVERLAY) {
                    $type = $background->invitation_type;
                    $preset = app(InvitationMessageService::class)->templateByKey($type);
                    $typography['text_layout'] = CreativeTextLayout::messageLayout($type, $background->text_layout);
                    $typography['inline_segments'] = CreativeTextLayout::segments($type, $preset['body'] ?? '', $typography['template_vars'] ?? []);
                    $title = '';
                    $body = implode('', array_column($typography['inline_segments'], 'text'));
                } else {
                    $typography['text_layout'] = $background->text_layout
                        ?? ($background->invitation_type === 'wedding' ? CreativeTextLayout::defaults('wedding') : null);
                }
            }
            $key = 'preview_' . $background->id . '_' . hash('sha256', serialize([$background->image_path, $title, $body, $typography]));

            return $this->render($background->image_path, $title, $body, $key, $typography);
        } catch (InvitationException $e) {
            throw $e;
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
        $layout = $typography['text_layout'] ?? null;
        $cacheKey .= '_' . md5(serialize([$sourcePath, $family, $weight, $layout]));
        if ($layout) {
            $cacheKey .= '_v' . self::LAYOUT_RENDER_VERSION;
        }
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

            $type = $typography['invitation_type'] ?? null;
            $canvas = $layout ? $this->fitArtwork($image) : $this->cover($image);
            imagedestroy($image);

            try {
                if ($type === null) {
                    $this->overlay($canvas);
                    $this->border($canvas);
                }

                $title = $this->clean($title);
                $body = $this->clean($body);
                if (isset($layout['message'], $typography['inline_segments'])) {
                    $this->drawInlineMessage(
                        $canvas, $fonts, $weight ?? 'regular', $layout['message'], $typography['inline_segments'],
                        $typography['allow_placeholder_overflow'] ?? false,
                    );
                } elseif ($layout) {
                    $fields = CreativeTextLayout::content($layout, ['title' => $title, 'message' => $body] + ($typography['template_vars'] ?? []));
                    if ($fields === []) {
                        return null;
                    }
                    $this->drawLayout($canvas, $fonts, $weight ?? 'regular', $layout, $fields, $typography['allow_placeholder_overflow'] ?? false);
                } else {
                    if ($title === '' && $body === '') {
                        return null;
                    }
                    $this->drawBlock($canvas, $fonts, $title, $body, $weight);
                }

                ob_start();
                try {
                    $written = imagejpeg($canvas, null, 88);
                } finally {
                    $jpeg = ob_get_clean();
                }
            } finally {
                imagedestroy($canvas);
            }

            return $written && $disk->put($relative, $jpeg) ? $relative : null;
        } catch (InvitationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::channel('single')->error('[InvitationCard] render failed: ' . $e->getMessage());

            return null;
        }
    }

    protected function fitArtwork($image)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(self::WIDTH / $width, 1920 / $height);

        return imagescale($image, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
    }

    private function drawInlineMessage($canvas, array $fonts, string $weight, array $box, array $segments, bool $allowOverflow): void
    {
        $width = imagesx($canvas);
        $height = imagesy($canvas);
        $shortSide = min($width, $height);
        $boxWidth = $width * $box['width'] / 100;
        $boxHeight = $height * $box['height'] / 100;
        $minimum = $allowOverflow ? 1 : max(4, (int) ceil($shortSide * .015));
        $base = $box + ['bold' => $weight === 'bold', 'italic' => false, 'underline' => false];
        $tokens = [];
        foreach ($segments as $segment) {
            $format = ($box['inline'][$segment['key']] ?? []) + $base;
            $dx = (float) ($format['offsetX'] ?? 0) * $width / 100;
            $dy = (float) ($format['offsetY'] ?? 0) * $height / 100;
            $style = [
                $fonts[$format['bold'] ? 'bold' : 'regular'], (bool) $format['italic'], (bool) $format['underline'],
                $format['fontSize'] / $box['fontSize'], $dx, $dy, ($dx || $dy) ? $segment['label'] : null,
            ];
            $parts = preg_split('/(\n|[^\S\n]+)/u', str_replace(["\r\n", "\r"], "\n", $segment['text']), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            foreach ($parts as $part) {
                $tokens[] = [$part, $style];
            }
        }

        for ($size = max($minimum, (int) round($shortSide * $box['fontSize'] / 100 * .75)); $size >= $minimum; $size--) {
            $lines = [];
            $current = [];
            $space = null;
            foreach ($tokens as [$word, $style]) {
                if ($word === "\n") {
                    $lines[] = $this->inlineLine($current, $size);
                    $current = [];
                    $space = null;
                    continue;
                }
                if (trim($word) === '') {
                    $space = [' ', $style];
                    continue;
                }
                $candidate = $current;
                if ($space && $candidate) {
                    $candidate[] = $space;
                }
                $candidate[] = [$word, $style];
                if ($current && $this->inlineLine($candidate, $size)['width'] > $boxWidth - 6) {
                    $lines[] = $this->inlineLine($current, $size);
                    $current = [[$word, $style]];
                } else {
                    $current = $candidate;
                }
                $space = null;
            }
            if ($current) {
                $lines[] = $this->inlineLine($current, $size);
            }
            if ($lines === []) {
                return;
            }

            $step = (int) ceil($size * 1.95);
            $top = $lines[0]['top'];
            $bottom = 0;
            foreach ($lines as $index => $line) {
                $lines[$index]['baseline'] = $index === 0 ? 0 : max($baseline + $step, $bottom - $line['top'] + 2);
                $baseline = $lines[$index]['baseline'];
                $bottom = $baseline + $line['bottom'];
            }
            if ($bottom - $top <= $boxHeight - 6 && max(array_column($lines, 'width')) <= $boxWidth - 6) {
                $white = imagecolorallocate($canvas, 255, 255, 255);
                $shadow = imagecolorallocatealpha($canvas, 0, 0, 0, 80);
                $baseY = $height * $box['y'] / 100 + ($boxHeight - ($bottom - $top)) / 2 - $top;
                $right = $width * ($box['x'] + $box['width']) / 100 - 3;
                foreach ($lines as $line) {
                    $left = $right - $line['width'];
                    foreach ($line['runs'] as [$text, $font, $italic, $underline, $bounds, $inkLeft, $advance, $shift, $runSize, $dx, $dy, $label, $inkBottom]) {
                        $x = (int) round($left - $inkLeft + $dx);
                        $y = (int) round($baseY + $line['baseline'] + $dy);
                        if (! $allowOverflow && $label && ($left + $dx < 0 || $left + $dx + $advance > $width
                            || $y + $bounds[2] < 0 || $y + $inkBottom > $height)) {
                            throw new InvitationException('العنصر «'.$label.'» خارج إطار الصورة. عدّل إزاحته.', 'CREATIVE_TEXT_OVERFLOW', 422);
                        }
                        if ($italic) {
                            $this->drawItalicLine($canvas, $font, $runSize, $text, $x, $y, $bounds);
                        } else {
                            imagettftext($canvas, $runSize, 0, $x + 2, $y + 2, $shadow, $font, $text);
                            imagettftext($canvas, $runSize, 0, $x, $y, $white, $font, $text);
                        }
                        if ($underline) {
                            $lineY = $y + max(0, $bounds[3]) + max(1, (int) round($runSize * .10));
                            imagefilledrectangle($canvas, $x + $bounds[0] + $shift, $lineY, $x + $bounds[1] + $shift,
                                $lineY + max(1, (int) round($runSize * .12)) - 1, $white);
                        }
                        $left += $advance;
                    }
                }

                return;
            }
        }

        throw new InvitationException('يتجاوز نص الدعوة إطار الصورة. وسّع المساحة أو صغّر الخط.', 'CREATIVE_TEXT_OVERFLOW', 422);
    }

    private function inlineLine(array $tokens, int $size): array
    {
        $groups = [];
        foreach ($tokens as [$text, $style]) {
            $last = array_key_last($groups);
            if ($last !== null && $groups[$last][1] === $style) {
                $groups[$last][0] .= $text;
            } else {
                $groups[] = [$text, $style];
            }
        }

        $line = ['runs' => [], 'width' => 0, 'top' => 0, 'bottom' => 0];
        foreach (array_reverse($groups) as [$logical, [$font, $italic, $underline, $ratio, $dx, $dy, $label]]) {
            $runSize = max(1, (int) round($size * $ratio));
            $text = (str_ends_with($logical, ' ') ? ' ' : '').$this->shape(trim($logical)).(str_starts_with($logical, ' ') ? ' ' : '');
            $box = imagettfbbox($runSize, 0, $font, $text);
            $xs = [$box[0], $box[2], $box[4], $box[6]];
            $ys = [$box[1], $box[3], $box[5], $box[7]];
            $bounds = [min($xs), max($xs), min($ys), max($ys)];
            [$minX, $maxX, $minY, $maxY] = $bounds;
            $left = $minX - ($italic ? (int) round($maxY * self::ITALIC_SHEAR) : 0);
            $right = $maxX - ($italic ? (int) round($minY * self::ITALIC_SHEAR) : 0);
            $advance = $right - $left;
            $shift = $italic ? (int) round(($minY + $maxY) / -2 * self::ITALIC_SHEAR) : 0;
            $line['width'] += $advance;
            $line['top'] = min($line['top'], $minY);
            $bottom = $underline ? max(0, $maxY) + max(1, (int) round($runSize * .10)) + max(1, (int) round($runSize * .12)) : $maxY;
            $line['runs'][] = [$text, $font, $italic, $underline, $bounds, $left, $advance, $shift, $runSize, $dx, $dy, $label, $bottom];
            $line['bottom'] = max($line['bottom'], $bottom);
        }

        return $line;
    }

    protected function drawLayout($canvas, array $fonts, string $weight, array $layout, array $fields, bool $allowOverflow = false): void
    {
        $width = imagesx($canvas);
        $height = imagesy($canvas);
        $shortSide = min($width, $height);
        $minimum = $allowOverflow ? 1 : max(4, (int) ceil($shortSide * .015));
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $shadow = imagecolorallocatealpha($canvas, 0, 0, 0, 80);

        foreach ($fields as $key => $value) {
            $box = $layout[$key];
            $text = $this->clean($value);
            if ($text === '') {
                continue;
            }
            $font = $fonts[array_key_exists('bold', $box) ? ($box['bold'] ? 'bold' : 'regular') : $weight];
            $italic = $box['italic'] ?? false;
            $underline = $box['underline'] ?? false;
            $boxWidth = $width * $box['width'] / 100;
            $boxHeight = $height * $box['height'] / 100;
            $size = max($minimum, (int) round($shortSide * $box['fontSize'] / 100 * .75));
            do {
                $slant = $italic ? (int) ceil($size * .6) : 0;
                $underlineGap = $underline ? max(1, (int) round($size * .10)) : 0;
                $underlineWeight = $underline ? max(1, (int) round($size * .12)) : 0;
                $lines = $this->wrap($font, $size, $text, $boxWidth - 6 - $slant);
                if ($lines === []) {
                    continue 2;
                }
                $step = (int) ceil($size * 1.7) + $underlineGap + $underlineWeight;
                $metrics = array_map(function (string $line) use ($font, $size) {
                    $shaped = $this->shape($line);
                    $bounds = imagettfbbox($size, 0, $font, $shaped);
                    $xs = [$bounds[0], $bounds[2], $bounds[4], $bounds[6]];
                    $ys = [$bounds[1], $bounds[3], $bounds[5], $bounds[7]];

                    return [$shaped, min($xs), max($xs), min($ys), max($ys)];
                }, $lines);
                $top = min(array_column($metrics, 3));
                $bottom = max(array_map(
                    fn ($line, $index) => ($underline ? max(0, $line[4]) + $underlineGap + $underlineWeight : $line[4]) + $index * $step,
                    $metrics, array_keys($metrics),
                ));
                $fits = $bottom - $top <= $boxHeight - 6 && max(array_map(
                    fn ($line) => $line[2] - $line[1] + ($italic ? ceil(($line[4] - $line[3]) * self::ITALIC_SHEAR) : 0),
                    $metrics,
                )) <= $boxWidth - 6;
                if ($fits) {
                    break;
                }
            } while (--$size >= $minimum);

            if (! $fits) {
                throw new InvitationException('يتجاوز النص مساحة الحقل داخل إطار الصورة. وسّع الحقل أو صغّر الخط في المحرر، أو اختر قالبًا آخر.', 'CREATIVE_TEXT_OVERFLOW', 422);
            }

            $left = $width * $box['x'] / 100;
            $baseline = $height * $box['y'] / 100 + ($boxHeight - ($bottom - $top)) / 2 - $top;
            foreach ($metrics as [$shaped, $minX, $maxX, $minY, $maxY]) {
                $inkCenter = ($minX + $maxX) / 2 - ($italic ? ($minY + $maxY) / 2 * self::ITALIC_SHEAR : 0);
                $x = (int) round($left + $boxWidth / 2 - $inkCenter);
                $y = (int) round($baseline);
                if ($italic) {
                    $this->drawItalicLine($canvas, $font, $size, $shaped, $x, $y, [$minX, $maxX, $minY, $maxY]);
                } else {
                    imagettftext($canvas, $size, 0, $x + 2, $y + 2, $shadow, $font, $shaped);
                    imagettftext($canvas, $size, 0, $x, $y, $white, $font, $shaped);
                }
                if ($underline) {
                    $lineY = $y + max(0, $maxY) + $underlineGap;
                    $offset = $italic ? (int) round(($minY + $maxY) / -2 * self::ITALIC_SHEAR) : 0;
                    imagefilledrectangle($canvas, $x + $minX + $offset, $lineY, $x + $maxX + $offset, $lineY + $underlineWeight - 1, $white);
                }
                $baseline += $step;
            }
        }
    }

    protected function drawItalicLine($canvas, string $font, int $size, string $text, int $x, int $y, array $bounds): void
    {
        [$minX, $maxX, $minY, $maxY] = $bounds;
        $width = $maxX - $minX + 8;
        $height = $maxY - $minY + 8;
        $line = imagecreatetruecolor($width, $height);
        imagealphablending($line, false);
        imagefill($line, 0, 0, imagecolorallocatealpha($line, 0, 0, 0, 127));
        imagesavealpha($line, true);
        $baseline = 2 - $minY;
        $origin = 2 - $minX;
        try {
            imagettftext($line, $size, 0, $origin + 2, $baseline + 2, imagecolorallocatealpha($line, 0, 0, 0, 80), $font, $text);
            imagettftext($line, $size, 0, $origin, $baseline, imagecolorallocate($line, 255, 255, 255), $font, $text);
            // Offset the rows around the baseline so the existing Arabic glyph shaping stays intact.
            for ($row = 0; $row < $height; $row++) {
                $shift = (int) round(($baseline - $row) * self::ITALIC_SHEAR);
                imagecopy($canvas, $line, $x - $origin + $shift, $y - $baseline + $row, 0, $row, $width, 1);
            }
        } finally {
            imagedestroy($line);
        }
    }

    protected function clean(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/\{[a-z_]+\}(*SKIP)(*F)|[*_~`]/', '', $text);
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

    protected function wrap(string $font, int $size, string $text, float $maxWidth = self::MAX_WIDTH): array
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
                if ($this->lineWidth($font, $size, $this->shape($candidate)) <= $maxWidth) {
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
        $x = (int) ((imagesx($canvas) - ($maxX - $minX)) / 2 - $minX);

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
