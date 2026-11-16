<?php

namespace App\Services\Invitations;

use App\Models\Person;

class InvitationMessageService
{
    /**
     * @param  array<string, string>  $extraVars
     */
    public function replacePlaceholders(string $body, ?Person $person = null, ?Person $sender = null, array $extraVars = []): string
    {
        $normalizedExtra = $this->normalizeExtraVars($extraVars);

        // Check if an external or custom recipient name was passed in extraVars
        $customName = $normalizedExtra['{name}'] ?? ($normalizedExtra['{first_name}'] ?? ($normalizedExtra['{full_name}'] ?? null));

        if ($person) {
            $firstName = $person->first_name ?: ($person->full_name ?: 'ضيفنا العزيز');
            $fullName = $person->full_name ?: ($person->first_name ?: 'زائرنا الكريم');
        } elseif (! empty($customName)) {
            $firstName = $customName;
            $fullName = $customName;
        } else {
            // Creative & dignified fallback for PIN / guest / broadcast users (e.g. PIN 112)
            $firstName = 'ضيفنا العزيز';
            $fullName = 'زائرنا الكريم';
        }

        $senderName = $sender?->full_name ?: ($sender?->first_name ?: '');

        $coreReplacements = [
            '{name}' => $firstName,
            '{first_name}' => $firstName,
            '{full_name}' => $fullName,
            '{sender_name}' => $senderName,
            '{{name}}' => $firstName,
            '{{first_name}}' => $firstName,
            '{{full_name}}' => $fullName,
            '{{sender_name}}' => $senderName,
            ':name' => $firstName,
            ':first_name' => $firstName,
            ':full_name' => $fullName,
            ':sender_name' => $senderName,
        ];

        $replacements = array_merge($coreReplacements, $normalizedExtra);

        $result = strtr($body, $replacements);

        // Safeguard: replace any unbracketed standalone "name" word following common greetings
        $result = preg_replace('/(مرحباً|أهلاً|عزيزي|عزيزتي|الغالي\/ة|الغالي|الغالية|حياك)\s+name\b/u', '$1 '.$firstName, $result);
        $result = preg_replace('/\bname\b/u', $firstName, $result);

        return $result;
    }

    /**
     * توقيع تلقائي باسم المرسل حتى لو نسي كتابته في النص.
     * لا يكرر نفسه لو النص فيه {sender_name} أو توقيع موجود.
     */
    public function withSenderSignature(string $body, ?Person $sender = null): string
    {
        $name = $sender?->full_name ?: ($sender?->first_name ?: '');

        if ($name === '') {
            return $body;
        }

        if (str_contains($body, '{sender_name}') || str_contains($body, '{{sender_name}}') || str_contains($body, ':sender_name')) {
            return $body;
        }

        if (str_contains($body, $name)) {
            return $body;
        }

        return rtrim($body) . "\n\n— مع تحيات: " . $name;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function templates(): array
    {
        return array_values(config('invitation_templates.templates', []));
    }

    public function templateByKey(string $key): ?array
    {
        foreach ($this->templates() as $template) {
            if (($template['key'] ?? '') === $key) {
                return $template;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $vars
     * @return array<string, string>
     */
    private function normalizeExtraVars(array $vars): array
    {
        $normalized = [];

        foreach ($vars as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            $placeholder = str_starts_with($key, '{') ? $key : '{'.$key.'}';
            $normalized[$placeholder] = is_scalar($value) ? (string) $value : '';
        }

        return $normalized;
    }
}
