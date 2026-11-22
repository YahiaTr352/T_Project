<?php

namespace App\Services\Invitations;

use App\Models\CreativeTemplate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreativeTextLayout
{
    private const STYLES = ['bold', 'italic', 'underline'];

    public static function fields(string $type): array
    {
        $template = collect(config('invitation_templates.templates', []))->firstWhere('key', $type);
        if (! $template) {
            return [];
        }

        $labels = [
            'title' => 'عنوان الدعوة',
            'hosts' => 'الداعون',
            'groom' => 'اسم العريس',
            'date' => 'التاريخ',
            'time' => 'الوقت',
            'location' => 'المكان',
            'event_name' => 'اسم المناسبة',
            'occasion' => 'المناسبة',
            'announcement_title' => 'عنوان الإعلان',
            'announcement_details' => 'تفاصيل الإعلان',
            'topic' => 'الموضوع',
            'deceased_name' => 'اسم المتوفى',
            'urgent_matter' => 'موضوع التنبيه',
        ];
        preg_match_all('/\{([a-z_]+)\}/', $template['body'], $matches);
        $keys = array_values(array_intersect(array_unique($matches[1]), array_keys($labels)));
        array_unshift($keys, 'title');
        $step = 84 / count($keys);

        return array_map(function (string $key, int $index) use ($labels, $template, $step) {
            return [
                'key' => $key,
                'kind' => $key === 'title' ? 'text' : 'variable',
                'label' => $labels[$key],
                'sample' => $key === 'title' ? $template['title'] : '{'.$key.'}',
                'box' => [
                    'x' => 8, 'y' => round(8 + $step * $index, 2),
                    'width' => 84, 'height' => round($step - 1, 2),
                    'fontSize' => in_array($key, ['title', 'groom'], true) ? 8 : 6,
                ],
            ];
        }, $keys, array_keys($keys));
    }

    public static function defaults(string $type): array
    {
        return array_column(self::fields($type), 'box', 'key');
    }

    public static function editableFields(string $type): array
    {
        $template = collect(config('invitation_templates.templates', []))->firstWhere('key', $type);
        if (! $template) {
            return [];
        }

        $segments = self::segments($type, $template['body'], []);

        return [[
            'key' => 'message', 'kind' => 'text', 'label' => 'نص الدعوة',
            'sample' => implode('', array_column($segments, 'text')), 'segments' => $segments,
            'box' => ['x' => 8, 'y' => 10, 'width' => 84, 'height' => 80, 'fontSize' => 5],
        ]];
    }

    public static function placeholders(string $type): array
    {
        $fields = array_slice(self::fields($type), 1);
        $keys = [...array_column($fields, 'key'), 'name', 'first_name', 'full_name', 'sender_name'];

        return array_combine($keys, array_map(fn (string $key) => '{'.$key.'}', $keys));
    }

    public static function segments(string $type, string $body, array $values): array
    {
        $labels = array_column(self::fields($type), 'label', 'key') + [
            'name' => 'اسم المدعو', 'first_name' => 'الاسم الأول للمدعو',
            'full_name' => 'اسم المدعو الكامل', 'sender_name' => 'اسم المرسل',
        ];
        $parts = preg_split('/(\{[a-z_]+\})/u', $body, -1, PREG_SPLIT_DELIM_CAPTURE);
        $segments = [];
        foreach ($parts as $index => $part) {
            $variable = $index % 2 === 1;
            $key = $variable ? substr($part, 1, -1) : 'text_'.intdiv($index, 2);
            if ($part !== '') {
                $segments[] = [
                    'key' => $key, 'text' => $variable ? (string) ($values[$key] ?? $values[$part] ?? $part) : $part,
                    'label' => $variable ? ($labels[$key] ?? $key) : 'عبارة من نص الدعوة',
                    'variable' => $variable,
                ];
            }
        }

        return $segments;
    }

    public static function messageLayout(string $type, ?array $saved): array
    {
        $field = self::editableFields($type)[0] ?? null;
        if (! $field) {
            return [];
        }
        $message = is_array($saved['message'] ?? null) ? $saved['message'] : [];
        $box = ($message['enabled'] ?? true) ? array_intersect_key($message, $field['box'] + array_fill_keys(self::STYLES, true)) + $field['box'] : $field['box'];
        $inline = [];
        $styles = array_fill_keys(self::STYLES, true);
        $inlineProperties = $styles + ['offsetX' => true, 'offsetY' => true, 'fontSize' => true];
        foreach ($field['segments'] as $segment) {
            $key = $segment['key'];
            $current = $message['inline'][$key] ?? null;
            $legacy = $segment['variable'] ? ($saved[$key] ?? []) : [];
            $flags = is_array($current) ? array_intersect_key($current, $inlineProperties)
                : (is_array($legacy) ? array_intersect_key($legacy, $styles) : []);
            if ($flags) {
                $inline[$key] = $flags;
            }
        }
        if ($inline) {
            $box['inline'] = $inline;
        }

        return ['message' => $box];
    }

    public static function reclassify(CreativeTemplate $template, ?string $type): ?array
    {
        if ($template->design_mode !== CreativeTemplate::MODE_OVERLAY) {
            return null;
        }
        if ($type === null || ! $template->text_layout) {
            return $template->text_layout;
        }

        return self::messageLayout($type, $template->text_layout);
    }

    public static function content(array $layout, array $values): array
    {
        $content = [];
        foreach ($layout as $key => $box) {
            if ($key === 'note') {
                continue;
            }
            $value = $values[$key] ?? '';
            if (($box['enabled'] ?? true) && is_scalar($value) && trim((string) $value) !== '') {
                $content[$key] = (string) $value;
            }
        }

        return $content;
    }

    public static function validate(?string $json, string $type, ?string $designMode = null): ?array
    {
        if ($json === null) {
            return null;
        }

        $boxes = json_decode($json, true);
        $editable = $designMode === CreativeTemplate::MODE_OVERLAY || (is_array($boxes) && isset($boxes['message']));
        $fields = array_column($editable ? self::editableFields($type) : self::fields($type), 'label', 'key');
        if ($editable && is_array($boxes) && array_diff_key($boxes, $fields) && ! array_diff_key(
            $boxes,
            ['message' => true, 'note' => true] + self::defaults($type),
        )) {
            $boxes = self::messageLayout($type, $boxes);
        }
        if (! is_array($boxes) || ! $boxes || array_diff_key($boxes, $fields)
            || (! $editable && array_diff_key($fields, $boxes))) {
            self::invalid('توزيع النص لا يطابق حقول نوع الدعوة.');
        }

        $validator = Validator::make(['boxes' => $boxes], [
            'boxes.*' => 'required|array:x,y,width,height,fontSize,bold,italic,underline,enabled,inline',
            'boxes.*.x' => 'required|numeric',
            'boxes.*.y' => 'required|numeric',
            'boxes.*.width' => 'required|numeric',
            'boxes.*.height' => 'required|numeric',
            'boxes.*.fontSize' => 'required|numeric|min:2|max:18',
            'boxes.*.bold' => 'sometimes|boolean',
            'boxes.*.italic' => 'sometimes|boolean',
            'boxes.*.underline' => 'sometimes|boolean',
            'boxes.*.enabled' => 'sometimes|boolean',
            'boxes.message.inline' => 'sometimes|array',
            'boxes.message.inline.*' => 'required|array:bold,italic,underline,offsetX,offsetY,fontSize',
            'boxes.message.inline.*.bold' => 'sometimes|boolean',
            'boxes.message.inline.*.italic' => 'sometimes|boolean',
            'boxes.message.inline.*.underline' => 'sometimes|boolean',
            'boxes.message.inline.*.offsetX' => 'sometimes|numeric|between:-100,100',
            'boxes.message.inline.*.offsetY' => 'sometimes|numeric|between:-100,100',
            'boxes.message.inline.*.fontSize' => 'sometimes|numeric|between:2,18',
        ]);
        if ($validator->fails()) {
            self::invalid('تأكد من حجم الخط وأبعاد حقول النص.');
        }

        $layout = [];
        $active = [];
        foreach ($boxes as $key => $input) {
            $box = [];
            foreach (['x', 'y', 'width', 'height', 'fontSize'] as $property) {
                $box[$property] = round((float) $input[$property], 2);
            }
            foreach (['bold', 'italic', 'underline', 'enabled'] as $property) {
                if (array_key_exists($property, $input)) {
                    $box[$property] = (bool) $input[$property];
                }
            }
            if ($editable) {
                if (isset($input['inline'])) {
                    $allowed = array_fill_keys(array_column(self::editableFields($type)[0]['segments'], 'key'), true);
                    if (array_diff_key($input['inline'], $allowed)) {
                        self::invalid('التنسيق لا يطابق أجزاء نص الدعوة.');
                    }
                    foreach ($input['inline'] as $part => $settings) {
                        foreach (self::STYLES as $property) {
                            if (array_key_exists($property, $settings)) {
                                $box['inline'][$part][$property] = (bool) $settings[$property];
                            }
                        }
                        foreach (['offsetX', 'offsetY', 'fontSize'] as $property) {
                            if (array_key_exists($property, $settings)) {
                                $box['inline'][$part][$property] = round((float) $settings[$property], 2);
                            }
                        }
                    }
                }
                unset($box['enabled']);
            } elseif (isset($input['inline'])) {
                self::invalid('التنسيق الداخلي متاح لنص الدعوة.');
            }
            if ($box['x'] < 0 || $box['y'] < 0 || $box['x'] > 100 || $box['y'] > 100
                || $box['x'] + $box['width'] > 100.001 || $box['y'] + $box['height'] > 100.001) {
                $label = $editable ? $fields[$key] : 'العنصر «'.$fields[$key].'»';
                self::invalid($label.' خارج إطار الصورة. عدّل الموضع أو الأبعاد.');
            }
            if ($box['width'] < 10 || $box['height'] < 5) {
                self::invalid('الحد الأدنى للعرض 10% وللارتفاع 5%.');
            }
            if ($box['enabled'] ?? true) {
                foreach ($active as $other) {
                    if (min($box['x'] + $box['width'], $other['x'] + $other['width']) - max($box['x'], $other['x']) > 0.001
                        && min($box['y'] + $box['height'], $other['y'] + $other['height']) - max($box['y'], $other['y']) > 0.001) {
                        self::invalid('العناصر متداخلة. افصل بينها قبل الحفظ.');
                    }
                }
                $active[] = $box;
            }
            $layout[$key] = $box;
        }
        if (! $active) {
            self::invalid('أظهر عنصرًا واحدًا على الأقل في التصميم.');
        }

        return $layout;
    }

    private static function invalid(string $message): never
    {
        throw ValidationException::withMessages(['text_layout' => $message]);
    }
}
