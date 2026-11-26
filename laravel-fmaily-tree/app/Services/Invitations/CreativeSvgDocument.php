<?php

namespace App\Services\Invitations;

use App\Exceptions\InvitationException;
use DOMDocument;
use DOMElement;
use DOMText;

class CreativeSvgDocument
{
    private const SVG = 'http://www.w3.org/2000/svg';
    private const XLINK = 'http://www.w3.org/1999/xlink';
    private const ELEMENTS = 'svg g defs symbol use path rect circle ellipse polygon polyline line linearGradient radialGradient stop clipPath mask pattern filter feGaussianBlur feOffset feFlood feBlend feMerge feMergeNode feColorMatrix feComposite feDropShadow text tspan textPath title desc image style';
    private const GEOMETRY = 'id class version viewBox preserveAspectRatio x y x1 y1 x2 y2 cx cy r rx ry fx fy fr dx dy rotate width height d points pathLength textLength lengthAdjust startOffset method spacing transform gradientTransform gradientUnits spreadMethod offset clipPathUnits filterUnits primitiveUnits maskUnits maskContentUnits patternUnits patternContentUnits patternTransform stdDeviation edgeMode in in2 result type values mode operator k1 k2 k3 k4';
    private const STYLE = 'fill fill-opacity fill-rule stroke stroke-width stroke-opacity stroke-dasharray stroke-dashoffset stroke-linecap stroke-linejoin stroke-miterlimit opacity color stop-color stop-opacity flood-color flood-opacity clip-path clip-rule mask filter display visibility mix-blend-mode isolation paint-order vector-effect shape-rendering text-rendering font-family font-size font-weight font-style font-stretch font-variant letter-spacing word-spacing text-anchor text-decoration dominant-baseline alignment-baseline baseline-shift direction unicode-bidi writing-mode white-space';
    public const FONTS = [
        'amiri' => 'Amiri', 'amiri-regular' => 'Amiri-Regular', 'amiri-bold' => 'Amiri-Bold',
        'tajawal' => 'Tajawal', 'tajawal-regular' => 'Tajawal-Regular', 'tajawal-bold' => 'Tajawal-Bold',
    ];

    public function inspect(string $xml): array
    {
        $document = new DOMDocument;
        if (strlen($xml) > 16 * 1024 * 1024 || ! @$document->loadXML($xml, LIBXML_NONET) || $document->doctype) {
            $this->invalid('تعذّرت قراءة ملف SVG. أعد تصديره من محرّر التصميم.');
        }
        $root = $document->documentElement;
        if ($root->namespaceURI !== self::SVG || $root->localName !== 'svg') {
            $this->invalid('اختر ملف تصميم بصيغة SVG.');
        }
        [$width, $height] = $this->dimensions($root);
        $root->setAttribute('width', (string) $width);
        $root->setAttribute('height', (string) $height);

        $elements = iterator_to_array($root->getElementsByTagName('*'));
        if (count($elements) > 20000) {
            $this->invalid('ملف SVG معقّد جدًا. اجمع عناصر الخلفية وأبقِ الحقول كنص قبل التصدير.');
        }
        foreach ($elements as $element) {
            if (in_array($element->localName, ['metadata', 'namedview'], true)) {
                $element->parentNode?->removeChild($element);
            }
        }
        $rules = [];
        foreach ([$root, ...iterator_to_array($root->getElementsByTagName('*'))] as $element) {
            $this->sanitize($element);
            if ($element->localName === 'style') {
                $rules = array_merge($rules, $this->styleRules($element->textContent));
            }
        }

        $fields = $fonts = $missing = [];
        $unsupportedVariant = false;
        foreach ($root->getElementsByTagNameNS(self::SVG, 'text') as $text) {
            if (trim($text->textContent) === '') {
                continue;
            }
            foreach ([$text, ...iterator_to_array($text->getElementsByTagNameNS(self::SVG, '*'))] as $part) {
                if (trim($part->textContent) === '') {
                    continue;
                }
                $family = trim(explode(',', $this->textProperty($part, $rules, 'font-family'))[0], " \t\n\r\"'");
                $key = strtolower($family);
                if (! isset(self::FONTS[$key])) {
                    $missing[$key] = $family ?: 'خط غير محدد';
                } else {
                    $fonts[$key] = self::FONTS[$key];
                    $weight = strtolower($this->textProperty($part, $rules, 'font-weight'));
                    $style = strtolower($this->textProperty($part, $rules, 'font-style'));
                    $stretch = strtolower($this->textProperty($part, $rules, 'font-stretch'));
                    if (! in_array($weight, ['', 'normal', 'bold', '400', '700'], true)
                        || ! in_array($style, ['', 'normal'], true) || ! in_array($stretch, ['', 'normal', '100%'], true)) {
                        $unsupportedVariant = true;
                    }
                }
            }

            $leaf = $this->editableLeaf($text);
            if (! $leaf || mb_strlen(trim($leaf->textContent)) > 255) {
                continue;
            }
            $key = 'creative_text_'.(count($fields) + 1);
            $leaf->setAttribute('data-creative-field', $key);
            $fields[$key] = trim($leaf->textContent);
        }

        $warnings = [];
        if (! $fields) {
            $warnings[] = 'ما في نص قابل للتعديل في الملف. أعد تصدير SVG من ملف التصميم الأصلي مع إبقاء الحقول كنص.';
        }
        if ($missing) {
            $warnings[] = 'الخط الأصلي غير متاح: '.implode('، ', array_values($missing)).'. الخطوط المدعومة حاليًا تجوال وأميري.';
        }
        if ($unsupportedVariant) {
            $warnings[] = 'وزن الخط أو نمطه غير متاح. استخدم الوزن العادي أو العريض من الخط المدعوم.';
        }

        return [$document->saveXML($root), [
            'width' => $width, 'height' => $height, 'fields' => $fields, 'fonts' => array_values($fonts), 'warnings' => $warnings,
        ]];
    }

    private function dimensions(DOMElement $root): array
    {
        $dimensions = [];
        foreach (['width', 'height'] as $axis) {
            $value = trim($root->getAttribute($axis));
            $dimensions[] = preg_match('/^\d+(?:\.\d+)?(?:px)?$/iD', $value) ? (float) $value : null;
        }
        $viewBox = preg_split('/[\s,]+/', trim($root->getAttribute('viewBox')));
        if (count($viewBox) === 4 && count(array_filter($viewBox, 'is_numeric')) === 4 && $viewBox[2] > 0 && $viewBox[3] > 0) {
            $dimensions[0] ??= $dimensions[1] ? $dimensions[1] * $viewBox[2] / $viewBox[3] : (float) $viewBox[2];
            $dimensions[1] ??= $dimensions[0] * $viewBox[3] / $viewBox[2];
        } elseif ($root->hasAttribute('viewBox')) {
            $this->invalid('مقاس مساحة الرسم في ملف SVG غير صالح.');
        }
        foreach ($dimensions as $size) {
            if (! $size || ! is_finite($size) || $size < 1 || $size > 4096) {
                $this->invalid('حدّد عرض SVG وارتفاعه بأبعاد صالحة حتى 4096 بكسل لكل ضلع.');
            }
        }

        return array_map(fn ($value) => (int) round($value), $dimensions);
    }

    private function sanitize(DOMElement $element): void
    {
        if ($element->namespaceURI !== self::SVG || ! in_array($element->localName, explode(' ', self::ELEMENTS), true)) {
            $this->invalid('يحتوي ملف SVG على عناصر غير مدعومة. صدّر التصميم كعناصر ثابتة مع نص قابل للتعديل.');
        }
        $allowed = explode(' ', self::GEOMETRY.' '.self::STYLE);
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = $attribute->localName;
            $value = trim($attribute->value);
            if ($attribute->namespaceURI && $attribute->namespaceURI !== self::XLINK) {
                if ($attribute->nodeName !== 'xml:space') {
                    $element->removeAttributeNode($attribute);
                }
                continue;
            }
            if ($name === 'href') {
                if (! $this->reference($value, $element->localName === 'image')) {
                    $this->invalid('ضمّن صور الخلفية داخل ملف SVG وأزل الروابط الخارجية قبل الرفع.');
                }
            } elseif ($name === 'style' && ! $attribute->namespaceURI) {
                $this->declarations($value);
            } elseif ($attribute->namespaceURI || ! in_array($name, $allowed, true)) {
                $element->removeAttributeNode($attribute);
            } else {
                $this->safeValue($value);
            }
        }
        foreach (iterator_to_array($element->childNodes) as $child) {
            if ($child instanceof \DOMProcessingInstruction) {
                $element->removeChild($child);
            }
        }
    }

    private function reference(string $value, bool $image): bool
    {
        if (preg_match('/^#[\pL\pN_.:-]+$/uD', $value)) {
            return true;
        }
        if (! $image || ! preg_match('~^data:image/(png|jpeg|webp);base64,([A-Za-z0-9+/=\s]+)$~iD', $value, $parts)) {
            return false;
        }
        $bytes = base64_decode($parts[2], true);
        $info = $bytes !== false ? @getimagesizefromstring($bytes) : false;

        return $info && $info['mime'] === 'image/'.strtolower($parts[1]) && $info[0] <= 4096 && $info[1] <= 4096;
    }

    private function safeValue(string $value): void
    {
        if (preg_match('/[\\\\<>@{};]/', $value)) {
            $this->invalid('يحتوي ملف SVG على إعدادات عرض غير مدعومة.');
        }
        $withoutReferences = preg_replace('/url\(\s*([\'"]?)#[\pL\pN_.:-]+\1\s*\)/iu', '', $value);
        if (preg_match('/url\s*\(/i', $withoutReferences)) {
            $this->invalid('ضمّن الموارد داخل SVG وأزل مراجع الصور والخطوط الخارجية.');
        }
    }

    private function declarations(string $style): array
    {
        $result = [];
        $allowed = explode(' ', self::STYLE);
        foreach (explode(';', $style) as $declaration) {
            if (trim($declaration) === '') {
                continue;
            }
            [$property, $value] = array_pad(explode(':', $declaration, 2), 2, '');
            $property = strtolower(trim($property));
            $value = trim($value);
            if (! in_array($property, $allowed, true) || str_contains($value, '!')) {
                $this->invalid('يتضمّن ملف SVG تنسيقًا غير مدعوم. استخدم خصائص الرسم والخط الأساسية عند التصدير.');
            }
            $this->safeValue($value);
            $result[$property] = $value;
        }

        return $result;
    }

    private function styleRules(string $style): array
    {
        $style = preg_replace('~/\*.*?\*/~s', '', $style);
        $rules = [];
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $style, $matches, PREG_SET_ORDER);
        if (trim(preg_replace('/([^{}]+)\{([^{}]*)\}/', '', $style)) !== '') {
            $this->invalid('يحتوي ملف SVG على قواعد تنسيق غير مدعومة.');
        }
        foreach ($matches as [, $selectors, $declarations]) {
            $properties = array_intersect_key($this->declarations($declarations), array_flip(['font-family', 'font-weight', 'font-style', 'font-stretch']));
            foreach (explode(',', $selectors) as $selector) {
                if (! preg_match('/^(?<tag>[a-zA-Z][\w-]*)?(?<modifier>[.#][\pL\pN_-]+)?$/uD', trim($selector), $parts) || trim($selector) === '') {
                    $this->invalid('استخدم تنسيق SVG الأساسي عند التصدير أو ضمّن خصائص التنسيق في العناصر.');
                }
                if ($properties) {
                    $rules[] = [$parts['tag'] ?? '', $parts['modifier'] ?? '', $properties];
                }
            }
        }

        return $rules;
    }

    private function textProperty(DOMElement $element, array $rules, string $property): string
    {
        $value = $element->getAttribute($property);
        $specificity = 0;
        foreach ($rules as [$tag, $modifier, $properties]) {
            if (! isset($properties[$property]) || ($tag && $tag !== $element->localName)) {
                continue;
            }
            $score = $tag ? 1 : 0;
            if (str_starts_with($modifier, '#')) {
                if ($element->getAttribute('id') !== substr($modifier, 1)) {
                    continue;
                }
                $score += 100;
            } elseif ($modifier) {
                if (! in_array(substr($modifier, 1), preg_split('/\s+/', $element->getAttribute('class')), true)) {
                    continue;
                }
                $score += 10;
            }
            if ($score >= $specificity) {
                $value = $properties[$property];
                $specificity = $score;
            }
        }
        $value = trim($this->declarations($element->getAttribute('style'))[$property] ?? $value);
        if (($value === '' || in_array(strtolower($value), ['inherit', 'unset'], true)) && $element->parentNode instanceof DOMElement) {
            return $this->textProperty($element->parentNode, $rules, $property);
        }

        return $value === 'initial' ? '' : $value;
    }

    private function editableLeaf(DOMElement $text): ?DOMElement
    {
        $leaf = $text;
        foreach ($text->childNodes as $child) {
            if ($child instanceof DOMElement) {
                if ($leaf !== $text || $child->localName !== 'tspan' || $child->childElementCount > 0) {
                    return null;
                }
                $leaf = $child;
            }
        }
        if ($leaf !== $text) {
            foreach ($text->childNodes as $child) {
                if ($child instanceof DOMText && trim($child->data) !== '') {
                    return null;
                }
            }
        }
        foreach ([$text, $leaf] as $part) {
            if ($part->hasAttribute('textLength') || preg_match('/[\d.]\s*[\s,]\s*[-+\d.]/', trim($part->getAttribute('x')))) {
                return null;
            }
        }

        return $leaf;
    }

    private function invalid(string $message): never
    {
        throw new InvitationException($message, 'CREATIVE_SVG_INVALID');
    }
}
