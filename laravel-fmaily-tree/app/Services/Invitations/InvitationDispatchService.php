<?php

namespace App\Services\Invitations;

use App\Exceptions\InvitationException;
use App\Models\CreativeTemplate;
use App\Models\Notification;
use App\Models\Person;
use App\Services\Auth\WhatsappPersonLookup;
use App\Support\NotificationSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class InvitationDispatchService
{
    private const SELF_INVITATION_MESSAGE = 'لا يمكنك إرسال دعوة لنفسك.';

    public function __construct(
        protected InvitationMessageService $messages,
        protected InvitationGroupService $groups,
        protected InvitationBackgroundService $backgrounds,
        protected CreativeTemplateService $creatives,
        protected InvitationDeliveryService $delivery,
        protected InvitationCardRenderer $cards,
        protected WhatsappPersonLookup $whatsappLookup,
    ) {}

    /**
     * @param  array{
     *     channels: list<string>,
     *     recipient_type: string,
     *     person_ids?: list<int>,
     *     group_id?: int|null,
     *     title?: string|null,
     *     body?: string|null,
     *     template_key?: string|null,
     *     template_vars?: array<string, string>,
     *     exclude_deceased?: bool,
     *     media_type?: string|null,
     *     media?: UploadedFile|null,
     *     background_id?: int|null
     *     creative_id?: int|null,
     *     creative_edit_source?: string|null
     * }  $payload
     * @return array{notification: Notification, whatsapp_jobs: int, push_jobs: int}
     */
    public function send(Person $sender, array $payload): array
    {
        $recipientType = $payload['recipient_type'] ?? '';
        $isExternal = ($recipientType === 'external');

        if ($isExternal) {
            $channels = ['whatsapp'];
        } else {
            $channels = array_values(array_intersect($payload['channels'] ?? [], ['whatsapp', 'push']));
        }

        if ($channels === []) {
            throw new InvitationException('يجب اختيار قناة إرسال واحدة على الأقل.', 'CHANNEL_REQUIRED');
        }

        $this->assertDailyLimit($sender->id);

        $body = $this->resolveBody($payload);
        $title = $payload['title'] ?? null;
        $excludeDeceased = (bool) ($payload['exclude_deceased'] ?? true);
        $externalRecipients = [];
        $persons = collect();

        if ($isExternal) {
            $externalRecipients = $this->normalizeExternalRecipients($payload);
            if (empty($externalRecipients)) {
                throw new InvitationException('يرجى إدخال اسم ورقم واتساب صحيح لشخص واحد على الأقل من خارج العائلة.', 'NO_EXTERNAL_RECIPIENTS');
            }
            $senderPhones = $sender->contactAccounts()->whereIn('type', ['whatsapp', 'phone'])->pluck('value')
                ->map(fn ($phone) => $this->whatsappLookup->canonicalMobile((string) $phone))->filter()->all();
            $externalRecipients = array_values(array_filter(
                $externalRecipients,
                fn (array $recipient) => ! in_array($this->whatsappLookup->canonicalMobile($recipient['phone']), $senderPhones, true),
            ));
            if ($externalRecipients === []) {
                throw new InvitationException(self::SELF_INVITATION_MESSAGE, 'SELF_INVITATION');
            }
        } else {
            $persons = $this->resolveRecipients($sender->id, $payload, $excludeDeceased);
            if ($persons->isEmpty()) {
                throw new InvitationException('لا توجد نتائج مطابقة للمستلمين المحددين.', 'NO_RECIPIENTS');
            }
        }

        if (! empty($payload['background_id']) && ! empty($payload['creative_id'])) {
            throw new InvitationException('لا يمكن اختيار خلفية وقالب ابداعي معا.', 'IMAGE_CONFLICT', 422);
        }

        $background = $this->backgrounds->usableBackground(
            isset($payload['background_id']) ? (int) $payload['background_id'] : null
        );
        $creative = $this->creativeFor($payload, allowWeb: $payload['allow_web_template'] ?? false);
        [$mediaPath, $mediaType] = $this->storeMedia($payload['media'] ?? null, $payload['media_type'] ?? null);
        if ($background && $background->image_path) {
            $copied = $this->copyBackgroundToNotification($background->image_path);
            if ($copied) {
                $mediaPath = $copied;
                $mediaType = 'image';
            }
        }

        $creativeCard = null;
        if ($creative) {
            $ready = $creative->designMode() === CreativeTemplate::MODE_READY;
            $creativeCard = $creative->invitation_type !== null ? $this->creativeCard($creative, $sender, $payload) : null;
            $copiedCreative = $creativeCard ?? ($creative->image_path ? $this->copyBackgroundToNotification($creative->image_path) : null);
            if (! $copiedCreative && ($ready || $creative->invitation_type !== null)) {
                throw new InvitationException('صورة القالب غير متاحة. اختر قالب صورة آخر.', 'CREATIVE_IMAGE_UNAVAILABLE', 422);
            }
            if ($copiedCreative) {
                $mediaPath = $copiedCreative;
                $mediaType = 'image';
            }
        }

        $templateVars = is_array($payload['template_vars'] ?? null) ? $payload['template_vars'] : [];

        if (trim($body) === '' && ! $mediaPath) {
            throw new InvitationException('أدخل نص الرسالة أو اختر صورة.', 'CONTENT_REQUIRED', 422);
        }

        $attributes = [
            'user_id' => null,
            'person_id' => $sender->id,
            'title' => $title,
            'body' => $body,
            'media_type' => $mediaType,
            'media_path' => $mediaPath,
            'channels' => $channels,
            'targeting_filters' => [
                'recipient_type' => $recipientType,
                'group_id' => $payload['group_id'] ?? null,
                'external_recipients' => $externalRecipients,
                'exclude_deceased' => $excludeDeceased,
                'template_key' => $payload['template_key'] ?? null,
                'template_vars' => $templateVars,
                'background_id' => $background?->id,
                'creative_id' => $creative?->id,
                'creative_design_mode' => $creative?->design_mode,
                'creative_card_rendered' => $creativeCard !== null,
                'font_family' => $payload['font_family'] ?? null,
                'font_weight' => $payload['font_weight'] ?? null,
                'requires_approval' => false,
            ],
            'status' => 'sending',
        ];

        [$notification, $result] = DB::transaction(function () use ($attributes, $isExternal, $externalRecipients, $persons) {
            $notification = Notification::create($attributes);

            if ($isExternal) {
                foreach ($externalRecipients as $ext) {
                    $notification->recipients()->create([
                        'person_id' => null,
                        'recipient_name' => $ext['name'],
                        'recipient_phone' => $ext['phone'],
                    ]);
                }
            } else {
                foreach ($persons as $person) {
                    $notification->recipients()->firstOrCreate(['person_id' => $person->id]);
                }
            }

            $result = $this->delivery->queueRecipients($notification);
            $notification->update(['status' => $result['status']]);

            return [$notification, $result];
        });

        return ['notification' => $notification->fresh(['recipients'])] + $result;
    }

    public function creativeFor(array $payload, bool $allowWeb = false): ?CreativeTemplate
    {
        $creative = $this->creatives->usableCreative(
            isset($payload['creative_id']) ? (int) $payload['creative_id'] : null,
            $payload['template_key'] ?? null,
        );
        if (! $allowWeb && in_array($creative?->designMode(), CreativeTemplate::WEB_MODES, true) && ! $creative->hasPreparedArtwork()) {
            throw new InvitationException('هذا القالب متاح من الموقع.', 'CREATIVE_NOT_AVAILABLE');
        }
        $revision = $creative?->artworkRevision();
        if ($revision && isset($payload['creative_edit_source']) && $revision !== $payload['creative_edit_source']) {
            throw new InvitationException('تغيّر القالب. أعد اختياره من المكتبة لتحديث الكتابة.', 'CREATIVE_EDIT_SOURCE_CHANGED', 422);
        }

        return $creative;
    }

    public function creativeCard(CreativeTemplate $creative, Person $sender, array $payload): ?string
    {
        if ($creative->designMode() === CreativeTemplate::MODE_SVG) {
            return app(CreativeSvgArtwork::class)->card($creative, $payload['template_vars'] ?? [], $payload['svg_card'] ?? null);
        }

        if ($creative->editable_text) {
            $path = $this->cards->renderEditableArtwork($creative, $payload['template_vars'] ?? []);
            if (! $path) {
                throw new InvitationException('تعذّر تجهيز صورة الدعوة. جرّب قالب صورة آخر.', 'CREATIVE_RENDER_FAILED', 422);
            }

            return $path;
        }
        if (in_array($creative->designMode(), [CreativeTemplate::MODE_READY, CreativeTemplate::MODE_WEB_ARTWORK], true)) {
            return null;
        }
        $usesPreset = $creative->design_mode === CreativeTemplate::MODE_OVERLAY && $creative->invitation_type !== null;
        $preset = $usesPreset ? $this->messages->templateByKey($creative->invitation_type) : null;
        $body = $preset['body'] ?? $this->resolveBody($payload);
        $vars = $payload['template_vars'] ?? [];
        if ($usesPreset) {
            foreach (['name', 'first_name', 'full_name', 'sender_name'] as $key) {
                $vars[$key] ??= $this->messages->replacePlaceholders('{'.$key.'}', null, $sender, $vars);
            }
        }
        ksort($vars);
        $usesFields = $creative->text_layout || $creative->invitation_type === 'wedding';
        if ($creative->design_mode !== CreativeTemplate::MODE_OVERLAY
            && trim($body) === '' && (! $usesFields || array_filter($vars) === [])) {
            return null;
        }

        $title = (string) ($preset['title'] ?? $payload['title'] ?? '');
        if ($title === '' && $usesFields) {
            $title = $this->messages->templateByKey($creative->invitation_type)['title'] ?? '';
        }
        $title = $this->messages->replacePlaceholders($title, null, $sender, $vars);
        $message = $this->messages->replacePlaceholders($body, null, $sender, $vars);
        if (! $usesPreset && $creative->text_layout && CreativeTextLayout::content(
            $creative->text_layout,
            ['title' => $title, 'message' => $message] + $vars,
        ) === []) {
            return null;
        }
        $style = [
            'font_family' => $payload['font_family'] ?? null,
            'font_weight' => $payload['font_weight'] ?? null,
            'template_vars' => $vars,
        ];
        $card = $this->cards->renderPreview($creative, $title, $message, $style);
        if ($card === null && $creative->invitation_type !== null) {
            throw new InvitationException('تعذّر تجهيز صورة الدعوة. جرّب قالب صورة آخر.', 'CREATIVE_RENDER_FAILED', 422);
        }

        return $card;
    }

    public function creativeArtworkLivePreview(?CreativeTemplate $creative, array $payload): string
    {
        if (! $creative?->hasPreparedArtwork()) {
            throw new InvitationException('اختر قالب صورة متاحًا للتعديل.', 'CREATIVE_EDITS_UNAVAILABLE', 422);
        }

        $preview = $this->cards->renderEditableArtwork($creative, $payload['template_vars'] ?? [], inlinePreview: true);
        if (! $preview) {
            throw new InvitationException('تعذّر تجهيز معاينة القالب.', 'CREATIVE_RENDER_FAILED', 422);
        }

        return $preview;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function preview(Person $sender, array $payload, ?int $samplePersonId = null, ?string $sampleExternalName = null): string
    {
        $body = $this->resolveBody($payload);
        if (trim($body) === '') {
            return '';
        }
        $body = $this->messages->withSenderSignature($body, $sender);
        $templateVars = is_array($payload['template_vars'] ?? null) ? $payload['template_vars'] : [];

        if (($payload['recipient_type'] ?? '') === 'external' || (! $samplePersonId && $sampleExternalName)) {
            $guestName = $sampleExternalName ?: ($payload['external_name'] ?? 'ضيفنا العزيز');

            return $this->messages->replacePlaceholders($body, null, $sender, array_merge($templateVars, [
                '{name}' => $guestName,
                '{first_name}' => $guestName,
                '{full_name}' => $guestName,
            ]));
        }

        if ($samplePersonId) {
            $person = Person::query()
                ->with(['contactAccounts', 'parent.parent.parent.parent.parent'])
                ->find($samplePersonId);

            if ($person) {
                return $this->messages->replacePlaceholders($body, $person, $sender, $templateVars);
            }
        }

        return $this->messages->replacePlaceholders($body, null, $sender, $templateVars);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{name: string, phone: string}>
     */
    public function normalizeExternalRecipients(array $payload): array
    {
        $recipients = [];

        if (! empty($payload['external_recipients']) && is_array($payload['external_recipients'])) {
            foreach ($payload['external_recipients'] as $item) {
                if (is_array($item)) {
                    $name = trim((string) ($item['name'] ?? ''));
                    $countryCode = (string) ($item['country_code'] ?? ($item['dial_code'] ?? ''));
                    $phone = $this->cleanPhoneNumber((string) ($item['phone'] ?? ''), $countryCode);
                    if ($name !== '' && $phone !== '') {
                        $recipients[] = [
                            'name' => $name,
                            'phone' => $phone,
                            'country_code' => $countryCode ?: null,
                        ];
                    }
                }
            }
        }

        // Support single external inputs if passed
        if (! empty($payload['external_phone'])) {
            $name = trim((string) ($payload['external_name'] ?? 'ضيف كريم'));
            $countryCode = (string) ($payload['external_country_code'] ?? ($payload['country_code'] ?? ''));
            $phone = $this->cleanPhoneNumber((string) $payload['external_phone'], $countryCode);
            if ($phone !== '') {
                $already = false;
                foreach ($recipients as $r) {
                    if ($r['phone'] === $phone) {
                        $already = true;
                        break;
                    }
                }
                if (! $already) {
                    $recipients[] = [
                        'name' => $name ?: 'ضيف كريم',
                        'phone' => $phone,
                        'country_code' => $countryCode ?: null,
                    ];
                }
            }
        }

        return $recipients;
    }

    public function cleanPhoneNumber(string $phone, ?string $countryCode = null): string
    {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($clean, '00')) {
            $clean = substr($clean, 2);
        }

        // If an explicit country code is passed (e.g. "966", "20", "971")
        if (! empty($countryCode)) {
            $code = preg_replace('/[^0-9]/', '', $countryCode);
            // If phone starts with local leading zero (e.g. 05x, 01x), strip it
            if (str_starts_with($clean, '0')) {
                $clean = substr($clean, 1);
            }
            // If not already prefixed with this country code, prepend it
            if (! str_starts_with($clean, $code)) {
                $clean = $code.$clean;
            }

            return $clean;
        }

        // If starts with 0 and matches 10 digits (Saudi local standard 05...), convert to 9665...
        if (str_starts_with($clean, '05') && strlen($clean) === 10) {
            $clean = '966'.substr($clean, 1);
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function resolveRecipients(int $ownerPersonId, array $payload, bool $excludeDeceased = true): Collection
    {
        $type = $payload['recipient_type'] ?? '';
        $query = Person::query()->whereKeyNot($ownerPersonId)->with(['contactAccounts', 'parent.parent.parent.parent.parent']);

        if ($excludeDeceased) {
            $query->whereNull('death_date');
        }

        if ($type === 'group') {
            $groupId = (int) ($payload['group_id'] ?? 0);
            $group = $this->groups->findOwned($groupId, $ownerPersonId);
            $ids = $group->persons()->pluck('persons.id')->all();
            $query->whereIn('id', $ids ?: [0]);
        } elseif ($type === 'persons') {
            $ids = array_values(array_unique(array_map('intval', $payload['person_ids'] ?? [])));
            if ($ids === []) {
                throw new InvitationException('يجب اختيار شخص واحد على الأقل.', 'NO_RECIPIENTS');
            }
            if ($ids === [$ownerPersonId]) {
                throw new InvitationException(self::SELF_INVITATION_MESSAGE, 'SELF_INVITATION');
            }
            $query->whereIn('id', $ids);
        } else {
            throw new InvitationException('نوع المستلمين غير مسموح.', 'INVALID_RECIPIENT_TYPE');
        }

        return $query->get();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveBody(array $payload): string
    {
        $templateKey = $payload['template_key'] ?? null;

        if (is_string($templateKey) && $templateKey !== '') {
            $template = $this->messages->templateByKey($templateKey);
            if (! $template) {
                throw new InvitationException('القالب غير موجود.', 'TEMPLATE_NOT_FOUND');
            }

            return array_key_exists('body', $payload)
                ? (string) $payload['body']
                : (string) ($template['body'] ?? '');
        }

        return (string) ($payload['body'] ?? '');
    }

    private function assertDailyLimit(int $personId): void
    {
        $limit = (int) (NotificationSettings::limits()['daily_limit'] ?? 0);

        if ($limit <= 0) {
            return;
        }

        $sentToday = Notification::query()
            ->where('person_id', $personId)
            ->whereDate('created_at', Carbon::today())
            ->count();

        if ($sentToday >= $limit) {
            throw new InvitationException(
                'تم بلوغ الحد اليومي لإرسال الدعوات. حاول مرة أخرى غداً.',
                'DAILY_LIMIT',
                429
            );
        }
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function copyBackgroundToNotification(string $sourcePath): ?string
    {
        try {
            if (filter_var($sourcePath, FILTER_VALIDATE_URL)) {
                return null;
            }
            if (! Storage::disk('public')->exists($sourcePath)) {
                return null;
            }
            $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'jpg');
            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                $ext = 'jpg';
            }
            $dest = 'notifications/inv-bg-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
            Storage::disk('public')->copy($sourcePath, $dest);

            return $dest;
        } catch (\Throwable) {
            return null;
        }
    }

    private function storeMedia(?UploadedFile $file, ?string $mediaType): array
    {
        if (! $file) {
            return [null, $mediaType];
        }

        $path = $file->store('notifications', 'public');
        $type = $mediaType ?: $this->guessMediaType($file->getMimeType());

        return [$path, $type];
    }

    private function guessMediaType(?string $mime): string
    {
        if (! $mime) {
            return 'image';
        }
        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }
        if (str_starts_with($mime, 'audio/')) {
            return 'voice';
        }

        return 'image';
    }
}
