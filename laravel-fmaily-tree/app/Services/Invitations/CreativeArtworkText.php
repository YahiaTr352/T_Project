<?php

namespace App\Services\Invitations;

use App\Exceptions\InvitationException;
use App\Models\CreativeTemplate;
use GdImage;
use Illuminate\Support\Facades\Storage;

class CreativeArtworkText
{
    public function prepareTemplateBackground(CreativeTemplate $template, array $fields, ?string $background = null): GdImage
    {
        $source = $this->readImage($template->image_path);
        if ($background !== null) {
            $cleaned = $this->decodeImage($background);
            if (! $cleaned || imagesx($cleaned) * imagesy($source) !== imagesy($cleaned) * imagesx($source)
                && abs(imagesx($cleaned) / imagesy($cleaned) - imagesx($source) / imagesy($source)) > .002) {
                throw new InvitationException('اختر خلفية بنفس نسبة أبعاد التصميم الأصلي.', 'CREATIVE_BACKGROUND_DIMENSIONS');
            }

            return imagescale($cleaned, imagesx($source), imagesy($source));
        }

        return $this->cleanText($source, $fields);
    }

    public function prepareUpload(string $bytes, array $fields): GdImage
    {
        $source = $this->decodeImage($bytes);
        if (! $source) {
            throw new InvitationException('تعذّرت قراءة صورة التصميم.', 'CREATIVE_IMAGE_INVALID');
        }
        imagepalettetotruecolor($source);

        return $this->cleanText($source, array_filter($fields, fn (array $field) => $field['replacePrinted']), smooth: true);
    }

    private function cleanText(GdImage $source, array $fields, bool $smooth = false): GdImage
    {
        if ($fields === []) {
            return $source;
        }
        foreach ($fields as &$field) {
            if (! isset($field['sourceColor'])) {
                [$field['sourceColor']] = $this->sampleColors($source, ...$this->bounds($field, imagesx($source), imagesy($source)));
            }
        }
        unset($field);

        return $this->cleanRegions($source, $fields, $smooth);
    }

    public function restoreUneditedRegions(CreativeTemplate $template, GdImage $cleaned, array $editedRegions): GdImage
    {
        $image = $this->readImage($template->image_path);
        $width = imagesx($image);
        $height = imagesy($image);
        if (imagesx($cleaned) !== $width || imagesy($cleaned) !== $height) {
            $cleaned = imagescale($cleaned, $width, $height);
        }
        foreach ($editedRegions as $region) {
            [$left, $top, $right, $bottom] = $this->bounds($region, $width, $height);
            imagecopy($image, $cleaned, $left, $top, $left, $top, $right - $left + 1, $bottom - $top + 1);
        }

        return $image;
    }

    private function cleanRegions(GdImage $source, array $regions, bool $smooth = false): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $cleaned = imagecreatetruecolor($width, $height);
        imagealphablending($cleaned, false);
        imagesavealpha($cleaned, true);
        imagecopy($cleaned, $source, 0, 0, 0, 0, $width, $height);
        foreach ($regions as $region) {
            [$left, $top, $right, $bottom] = $this->bounds($region, $width, $height);
            if (! isset($region['sourceColor'])) {
                continue;
            }
            $profile = $smooth ? $this->smoothBackgroundProfile($source, $left, $top, $right, $bottom) : null;
            $edges = $profile['rows'] ?? null;
            $gradient = $profile['columns'] ?? null;
            $mask = $this->inkMask($source, $left, $top, $right, $bottom, $region['sourceColor'], $edges, $gradient);
            if (! $mask) {
                continue;
            }

            for ($x = $left; $x <= $right; $x++) {
                $maskX = $x - $left;
                $y = $top;
                while ($y <= $bottom) {
                    if (! imagecolorat($mask, $maskX, $y - $top)) {
                        $y++;
                        continue;
                    }
                    $from = $y++;
                    while ($y <= $bottom && imagecolorat($mask, $maskX, $y - $top)) {
                        $y++;
                    }
                    if ($gradient) {
                        $this->restoreGradientRun($cleaned, $x, $from, $y - 1, $edges, $gradient[$maskX]);
                    } else {
                        $this->restoreColumn($source, $cleaned, $x, $from, $y - 1);
                    }
                }
            }
        }

        return $cleaned;
    }

    private function smoothBackgroundProfile(GdImage $source, int $left, int $top, int $right, int $bottom): ?array
    {
        if ($top < 6 || $bottom > imagesy($source) - 7) {
            return null;
        }
        // A quiet border is a better reference for a flat fill than pixels beside each glyph.
        $edges = [$top - 4, $bottom + 4];
        $rows = [];
        foreach ($edges as $y) {
            $samples = [];
            foreach (range(0, 10) as $step) {
                $x = $left + (int) round(($right - $left) * $step / 10);
                $samples[] = $this->medianBackgroundPixel($source, $x, $y);
            }
            $rows[] = $samples;
        }
        foreach ([0, 1, 2, 3] as $channel) {
            $values = array_column([...$rows[0], ...$rows[1]], $channel);
            sort($values);
            if ($values[20] - $values[1] > 24) {
                return null;
            }
        }

        // Fit a smooth horizontal profile between medians so neighboring letters cannot tint an entire column.
        $ends = array_map(fn (array $row) => [$this->medianChannels(array_slice($row, 0, 5)), $this->medianChannels(array_slice($row, -5))], $rows);
        $columns = [];
        for ($x = $left; $x <= $right; $x++) {
            $weight = ($x - $left) / max(1, $right - $left);
            $columns[] = array_map(fn (array $edge) => array_map(
                fn (int $a, int $b) => (int) round($a + ($b - $a) * $weight),
                $edge[0], $edge[1],
            ), $ends);
        }

        return ['rows' => $edges, 'columns' => $columns];
    }

    private function medianBackgroundPixel(GdImage $source, int $x, int $y): array
    {
        $samples = [];
        foreach ([-8, -4, 0, 4, 8] as $dx) {
            $point = min(imagesx($source) - 1, max(0, $x + $dx));
            foreach ([-2, 0, 2] as $dy) {
                $pixel = imagecolorat($source, $point, min(imagesy($source) - 1, $y + $dy));
                $samples[] = [$pixel >> 24, ($pixel >> 16) & 255, ($pixel >> 8) & 255, $pixel & 255];
            }
        }

        return $this->medianChannels($samples);
    }

    private function medianChannels(array $samples): array
    {
        return array_map(static function (int $channel) use ($samples) {
            $values = array_column($samples, $channel);
            sort($values);
            return $values[intdiv(count($values), 2)];
        }, [0, 1, 2, 3]);
    }

    private function restoreGradientRun(GdImage $image, int $x, int $from, int $to, array $edges, array $colors): void
    {
        $delta = array_map(fn (int $a, int $b) => $b - $a, $colors[0], $colors[1]);
        for ($y = $from; $y <= $to; $y++) {
            $weight = ($y - $edges[0]) / ($edges[1] - $edges[0]);
            [$alpha, $red, $green, $blue] = array_map(fn (int $base, int $change) => (int) round($base + $change * $weight), $colors[0], $delta);
            imagesetpixel($image, $x, $y, ($alpha << 24) | ($red << 16) | ($green << 8) | $blue);
        }
    }

    private function sampleColors(GdImage $image, int $left, int $top, int $right, int $bottom): array
    {
        $stepX = max(1, intdiv($right - $left + 1, 100));
        $stepY = max(1, intdiv($bottom - $top + 1, 70));
        $edges = [];
        for ($x = $left; $x <= $right; $x += $stepX) {
            $edges[] = $this->luminance(imagecolorat($image, $x, max(0, $top - 1)));
            $edges[] = $this->luminance(imagecolorat($image, $x, min(imagesy($image) - 1, $bottom + 1)));
        }
        sort($edges);
        $background = $edges[intdiv(count($edges), 2)];
        $groups = [];
        $best = 0;
        $ink = null;
        $bestBucket = null;
        for ($y = $top; $y <= $bottom; $y += $stepY) {
            for ($x = $left; $x <= $right; $x += $stepX) {
                $pixel = imagecolorat($image, $x, $y);
                $contrast = abs($this->luminance($pixel) - $background);
                if ($contrast < 48) {
                    continue;
                }
                $bucket = $pixel & 0xf0f0f0;
                $groups[$bucket] ??= ['score' => 0, 'top' => $y, 'bottom' => $y];
                $groups[$bucket]['score'] += $contrast * $contrast;
                $groups[$bucket]['bottom'] = $y;
                if ($groups[$bucket]['score'] > $best) {
                    $best = $groups[$bucket]['score'];
                    $bestBucket = $bucket;
                    $ink = $pixel & 0xffffff;
                }
            }
        }

        $inkHeight = $bestBucket === null ? null : $groups[$bestBucket]['bottom'] - $groups[$bestBucket]['top'] + $stepY;

        return [$ink === null ? null : sprintf('#%06x', $ink), $background, $inkHeight];
    }

    private function luminance(int $pixel): float
    {
        return .299 * (($pixel >> 16) & 255) + .587 * (($pixel >> 8) & 255) + .114 * ($pixel & 255);
    }

    private function bounds(array $region, int $width, int $height): array
    {
        return [
            (int) floor($region['x'] * $width / 100),
            (int) floor($region['y'] * $height / 100),
            min($width - 1, (int) ceil(($region['x'] + $region['width']) * $width / 100) - 1),
            min($height - 1, (int) ceil(($region['y'] + $region['height']) * $height / 100) - 1),
        ];
    }

    private function inkMask(GdImage $source, int $left, int $top, int $right, int $bottom, string $ink, ?array $edges, ?array $gradient): ?GdImage
    {
        $mask = imagecreatetruecolor($right - $left + 1, $bottom - $top + 1);
        $color = hexdec(substr($ink, 1));
        $red = ($color >> 16) & 255;
        $green = ($color >> 8) & 255;
        $blue = $color & 255;
        $padding = $gradient ? 7 : 4;
        $found = false;
        for ($y = $top; $y <= $bottom; $y++) {
            $weight = $edges ? ($y - $edges[0]) / ($edges[1] - $edges[0]) : 0;
            for ($x = $left; $x <= $right; $x++) {
                $pixel = imagecolorat($source, $x, $y);
                $r = ($pixel >> 16) & 255;
                $g = ($pixel >> 8) & 255;
                $b = $pixel & 255;
                $relativeX = $x - $left;
                $distance = max(abs($r - $red), abs($g - $green), abs($b - $blue));
                // Pick up shadows and faint edges when the sampled backdrop is smooth enough to model.
                $differentFromBackdrop = false;
                if ($gradient && $distance > 58) {
                    [$upper, $lower] = $gradient[$relativeX];
                    $differentFromBackdrop = max(
                        abs($r - $upper[1] - ($lower[1] - $upper[1]) * $weight),
                        abs($g - $upper[2] - ($lower[2] - $upper[2]) * $weight),
                        abs($b - $upper[3] - ($lower[3] - $upper[3]) * $weight),
                    ) > 10;
                }
                if ($distance > 58 && ! $differentFromBackdrop) {
                    continue;
                }
                $relativeY = $y - $top;
                // Include antialiasing and JPEG colour bleed around the letter edges.
                imagefilledrectangle($mask, $relativeX - $padding, $relativeY - $padding, $relativeX + $padding, $relativeY + $padding, 1);
                $found = true;
            }
        }

        return $found ? $mask : null;
    }

    private function restoreColumn(GdImage $source, GdImage $cleaned, int $x, int $from, int $to): void
    {
        $last = imagesy($source) - 1;
        $above = imagecolorat($source, $x, $from > 0 ? $from - 1 : min($last, $to + 1));
        $below = imagecolorat($source, $x, $to < $last ? $to + 1 : max(0, $from - 1));
        if ($above === $below) {
            imageline($cleaned, $x, $from, $x, $to, $above);
            return;
        }
        for ($y = $from; $y <= $to; $y++) {
            $weight = ($y - $from + 1) / ($to - $from + 2);
            $red = (int) round((($above >> 16) & 255) * (1 - $weight) + (($below >> 16) & 255) * $weight);
            $green = (int) round((($above >> 8) & 255) * (1 - $weight) + (($below >> 8) & 255) * $weight);
            $blue = (int) round(($above & 255) * (1 - $weight) + ($below & 255) * $weight);
            $alpha = (int) round(($above >> 24) * (1 - $weight) + ($below >> 24) * $weight);
            imagesetpixel($cleaned, $x, $y, ($alpha << 24) | ($red << 16) | ($green << 8) | $blue);
        }
    }

    private function readImage(?string $path): GdImage
    {
        $disk = Storage::disk('public');
        if (! $path || filter_var($path, FILTER_VALIDATE_URL) || ! $disk->exists($path)) {
            throw new InvitationException('الصورة الأصلية غير متاحة للتعديل.', 'CREATIVE_IMAGE_UNAVAILABLE', 422);
        }
        $source = $this->decodeImage($disk->get($path));
        if (! $source) {
            throw new InvitationException('تعذرت قراءة صورة التصميم.', 'CREATIVE_IMAGE_INVALID', 422);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, 1920 / max($width, $height));
        $image = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        imagefill($image, 0, 0, 0xffffff);
        imagecopyresampled($image, $source, 0, 0, 0, 0, imagesx($image), imagesy($image), $width, $height);

        return $image;
    }

    private function decodeImage(string $bytes): ?GdImage
    {
        $image = @imagecreatefromstring($bytes);
        if (! $image || ! str_starts_with($bytes, "\xff\xd8") || ! function_exists('exif_read_data')) {
            return $image ?: null;
        }

        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $bytes);
        rewind($stream);
        try {
            $orientation = (int) (@exif_read_data($stream, 'IFD0', true)['IFD0']['Orientation'] ?? 1);
        } finally {
            fclose($stream);
        }
        if (in_array($orientation, [2, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        } elseif ($orientation === 4) {
            imageflip($image, IMG_FLIP_VERTICAL);
        }

        $angle = match ($orientation) {
            3 => 180, 5, 8 => 90, 6, 7 => -90, default => 0,
        };

        return $angle ? (imagerotate($image, $angle, 0xffffff) ?: $image) : $image;
    }
}
