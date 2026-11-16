<?php

namespace App\Services;

use App\Models\Person;
use App\Models\PersonContactAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfilePrivacyService
{
    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_FAMILY = 'family';
    public const VISIBILITY_PRIVATE = 'private';

    public function __construct(
        protected SiteIdentityResolver $identityResolver
    ) {}

    /**
     * تحديد هوية المشاهد وصلاحياته
     *
     * @return array{
     *     is_admin: bool,
     *     viewer_person_id: ?int,
     *     is_family: bool,
     *     is_outside_family: bool,
     *     is_guest: bool,
     *     scope: string
     * }
     */
    public function resolveViewerContext(?Request $request = null, ?int $explicitViewerPersonId = null): array
    {
        // 1. فحص هل المستخدم أدمن مسجل دخوله في لوحة التحكم أو API
        $user = Auth::user();
        $isAdmin = false;
        if ($user instanceof User) {
            if (method_exists($user, 'hasRole') && ($user->hasRole('admin') || $user->hasRole('super_admin'))) {
                $isAdmin = true;
            } elseif (method_exists($user, 'can') && $user->can('people.view')) {
                $isAdmin = true;
            }
        }

        // 2. استخراج معرّف الشخص للمشاهد
        $viewerPersonId = $explicitViewerPersonId;

        if (!$viewerPersonId && $request) {
            /** @var Person|null $identityPerson */
            $identityPerson = $request->attributes->get('identity_person');
            if ($identityPerson) {
                $viewerPersonId = $identityPerson->id;
            }
        }

        if (!$viewerPersonId) {
            $viewerPersonId = $this->identityResolver->viewerPersonId($request);
        }

        if (!$viewerPersonId && $user && !empty($user->person_id)) {
            $viewerPersonId = (int) $user->person_id;
        }

        if ($isAdmin) {
            return [
                'is_admin' => true,
                'viewer_person_id' => $viewerPersonId,
                'is_family' => true,
                'is_outside_family' => false,
                'is_guest' => false,
                'scope' => 'admin',
            ];
        }

        if (!$viewerPersonId) {
            return [
                'is_admin' => false,
                'viewer_person_id' => null,
                'is_family' => false,
                'is_outside_family' => true,
                'is_guest' => true,
                'scope' => 'public',
            ];
        }

        // 3. التحقق من انتماء المشاهد للعائلة
        $viewerPerson = Person::select(['id', 'from_outside_the_family'])->find($viewerPersonId);
        $isFamily = false;

        if ($viewerPerson) {
            $isFamily = !((bool) $viewerPerson->from_outside_the_family);
        } elseif ($user && $user->is_from_ancestry) {
            $isFamily = true;
        }

        return [
            'is_admin' => false,
            'viewer_person_id' => $viewerPersonId,
            'is_family' => $isFamily,
            'is_outside_family' => !$isFamily,
            'is_guest' => false,
            'scope' => $isFamily ? 'family' : 'public',
        ];
    }

    /**
     * هل يحق للمشاهد رؤية حقل محدد في ملف الشخص؟
     */
    public function canViewField(
        Person $target,
        string $field,
        ?Request $request = null,
        ?int $explicitViewerPersonId = null
    ): bool {
        // الاسم الكامل لا يمكن حجبه نهائياً
        if ($field === 'full_name' || $field === 'first_name') {
            return true;
        }

        $viewer = $this->resolveViewerContext($request, $explicitViewerPersonId);

        // صاحب الملف يرى كامل بياناته دائماً
        if ($viewer['viewer_person_id'] && (int) $viewer['viewer_person_id'] === (int) $target->id) {
            return true;
        }

        // الأدمن يرى كل شيء
        if ($viewer['is_admin']) {
            return true;
        }

        $requiredVisibility = $target->getPrivacySetting($field, self::VISIBILITY_FAMILY);

        if ($requiredVisibility === self::VISIBILITY_PUBLIC) {
            return true;
        }

        if ($requiredVisibility === self::VISIBILITY_FAMILY) {
            return $viewer['is_family'];
        }

        // private: فقط الأدمن وصاحب الحساب (تم التحقق منهما مسبقاً)
        return false;
    }

    /**
     * هل يحق للمشاهد رؤية حساب تواصل محدد؟
     */
    public function canViewContactAccount(
        PersonContactAccount $account,
        ?Person $target = null,
        ?Request $request = null,
        ?int $explicitViewerPersonId = null
    ): bool {
        $person = $target ?? $account->person;

        if (!$person && $account->person_id) {
            $person = Person::find($account->person_id);
        }

        if (!$person) {
            return false;
        }

        $viewer = $this->resolveViewerContext($request, $explicitViewerPersonId);

        // صاحب الحساب أو الأدمن
        if (($viewer['viewer_person_id'] && (int) $viewer['viewer_person_id'] === (int) $person->id) || $viewer['is_admin']) {
            return true;
        }

        // أولاً: فحص خصوصية هذا الحساب المحدد (Granular visibility)
        // خصوصية كل حساب على حدة تأخذ الأولوية — إذا كانت "public" يراه الجميع
        // حتى لو كانت الخصوصية العامة للمجموعة "family".
        $accountVisibility = $account->visibility ?: self::VISIBILITY_FAMILY;

        if ($accountVisibility === self::VISIBILITY_PUBLIC) {
            return true;
        }

        // ثانياً: للحسابات غير العامة — نطبّق الخصوصية العامة للمجموعة كحد أدنى
        $overallContactsPrivacy = $person->getPrivacySetting('contacts', self::VISIBILITY_FAMILY);

        if ($overallContactsPrivacy === self::VISIBILITY_PRIVATE) {
            return false;
        }

        if ($accountVisibility === self::VISIBILITY_PRIVATE) {
            return false;
        }

        // كلا المستويين "family" — يجب أن يكون المشاهد من العائلة
        return $viewer['is_family'];
    }

    /**
     * فلترة مصفوفة تفاصيل الشخص (API Payload) وفقاً لخصوصيات المشاهد
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function filterPersonPayload(array $data, Person $person, ?Request $request = null): array
    {
        $viewer = $this->resolveViewerContext($request);

        // صاحب الحساب أو الأدمن يرى كل شيء دون أي حجب
        if (($viewer['viewer_person_id'] && (int) $viewer['viewer_person_id'] === (int) $person->id) || $viewer['is_admin']) {
            return $data;
        }

        // 1. الصورة الشخصية
        if (!$this->canViewField($person, 'photo', $request)) {
            // صورة رمزية افتراضية بحسب الجنس
            $data['photo_url'] = $person->gender === 'female'
                ? asset('assets/img/avatar-female-hejap-1.png')
                : asset('assets/img/avatar-male.png');
            $data['avatar'] = $data['photo_url'];
            $data['is_photo_hidden'] = true;
        } else {
            $data['is_photo_hidden'] = false;
        }

        // 2. تاريخ الميلاد والعمر
        if (!$this->canViewField($person, 'birth_date', $request)) {
            $data['birth_date'] = null;
            $data['age'] = null;
            $data['is_birth_date_hidden'] = true;
        } else {
            $data['is_birth_date_hidden'] = false;
        }

        // 3. مكان الإقامة والمواقع
        if (!$this->canViewField($person, 'location', $request)) {
            $data['location'] = null;
            $data['locations'] = [];
            $data['is_location_hidden'] = true;
        } else {
            $data['is_location_hidden'] = false;
        }

        // 4. المهنة
        if (!$this->canViewField($person, 'occupation', $request)) {
            $data['occupation'] = null;
            $data['is_occupation_hidden'] = true;
        } else {
            $data['is_occupation_hidden'] = false;
        }

        // 5. النبذة
        if (!$this->canViewField($person, 'biography', $request)) {
            $data['biography'] = null;
            $data['is_biography_hidden'] = true;
        } else {
            $data['is_biography_hidden'] = false;
        }

        // 6. حسابات التواصل (فلترة على مستوى القسم وعلى مستوى كل حساب)
        if (isset($data['contact_accounts']) && is_array($data['contact_accounts'])) {
            $canViewContactsSection = $this->canViewField($person, 'contacts', $request);

            if (!$canViewContactsSection) {
                $data['contact_accounts'] = [];
                $data['is_contacts_hidden'] = true;
            } else {
                $filteredContacts = [];
                foreach ($data['contact_accounts'] as $accData) {
                    $accVisibility = $accData['visibility'] ?? self::VISIBILITY_FAMILY;

                    if ($accVisibility === self::VISIBILITY_PUBLIC) {
                        $filteredContacts[] = $accData;
                    } elseif ($accVisibility === self::VISIBILITY_FAMILY && $viewer['is_family']) {
                        $filteredContacts[] = $accData;
                    }
                }
                $data['contact_accounts'] = $filteredContacts;
                $data['is_contacts_hidden'] = empty($filteredContacts);
            }
        }

        return $data;
    }
}
