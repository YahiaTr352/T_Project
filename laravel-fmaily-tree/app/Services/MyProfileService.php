<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Marriage;
use App\Models\Person;
use App\Models\PersonContactAccount;
use App\Models\PersonLocation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MyProfileService
{
    /**
     * @return \Illuminate\Support\Collection<int, Person>
     */
    public function spouseCandidates(Person $person)
    {
        $targetGender = $person->gender === 'male' ? 'female' : 'male';

        return Person::where('gender', $targetGender)
            ->with(['parent.parent.parent.parent.parent'])
            ->orderBy('first_name')
            ->limit(30)
            ->get(['id', 'first_name', 'last_name', 'gender', 'parent_id', 'photo_url']);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Person>
     */
    public function wifeCandidates(Person $person)
    {
        if ($person->gender !== 'male') {
            return collect();
        }

        // زوجات مرتبة (الأولى ثم الثانية...) لاختيار الأم
        $orderService = app(MarriageOrderService::class);
        $marriages = $orderService->orderedMarriagesForHusband((int) $person->id)->keyBy('wife_id');
        $wives = $orderService->orderedWives((int) $person->id);
        $wives->loadMissing(['parent.parent.parent.parent.parent']);

        return $wives->values()->map(function ($w) use ($marriages) {
            $w->marriage_id = $marriages->get($w->id)?->id;
            $w->married_at = $marriages->get($w->id)?->married_at;
            $w->sort_order = $marriages->get($w->id)?->sort_order;
            return $w;
        });
    }

    /**
     * أزواج المرأة المسجلين في الشجرة (سواء حاليين أو منفصلين) لاختيار الأب بدقة
     *
     * @return \Illuminate\Support\Collection<int, Person>
     */
    public function husbandCandidates(Person $person)
    {
        if ($person->gender !== 'female') {
            return collect();
        }

        $orderService = app(MarriageOrderService::class);
        $marriages = $orderService->orderedMarriagesForWife((int) $person->id)->keyBy('husband_id');
        $husbands = $orderService->orderedHusbands((int) $person->id);
        $husbands->loadMissing(['parent.parent.parent.parent.parent']);

        return $husbands->values()->map(function ($h) use ($marriages) {
            $m = $marriages->get($h->id);
            $h->marriage_id = $m?->id;
            $h->married_at = $m?->married_at;
            $h->divorced_at = $m?->divorced_at;
            $h->is_divorced = $m?->isDivorced();
            $h->status_text = $m?->status_text;
            $h->sort_order = $m?->sort_order;
            return $h;
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, Location>
     */
    public function locationsCatalog()
    {
        return Location::orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return array<string, mixed>
     */
    public function profileApiPayload(Person $person, ?string $authMethod = null): array
    {
        $payload = $this->profilePayload($person);
        $payload['auth_method'] = $authMethod;
        $payload['spouse_candidates'] = $this->spouseCandidates($person)
            ->map(fn ($p) => $this->serializePersonBrief($p))
            ->values();
        $payload['wife_candidates'] = $this->wifeCandidates($person)
            ->map(fn ($p) => $this->serializePersonBrief($p))
            ->values();
        $payload['husband_candidates'] = $this->husbandCandidates($person)
            ->map(fn ($p) => $this->serializePersonBrief($p))
            ->values();
        $payload['father_candidates'] = Person::where('gender', 'male')
            ->where('from_outside_the_family', false)
            ->with(['parent.parent.parent.parent.parent'])
            ->orderBy('first_name')
            ->limit(50)
            ->get(['id', 'first_name', 'last_name', 'gender', 'parent_id', 'photo_url'])
            ->map(fn ($p) => $this->serializePersonBrief($p))
            ->values();
        $payload['locations_catalog'] = $this->locationsCatalog()
            ->map(fn ($l) => ['id' => $l->id, 'name' => $l->name])
            ->values();

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function profilePayload(Person $person): array
    {
        $relations = [
            'parent:id,first_name,last_name,gender',
            'mother:id,first_name,last_name,gender',
            'contactAccounts',
            'personLocations.location',
            // يجب تأهيل persons.id لأن علاقة wives/husband تعمل JOIN مع marriages
            'wives' => fn ($q) => $q->select('persons.id', 'persons.first_name', 'persons.last_name'),
            'husband' => fn ($q) => $q->select('persons.id', 'persons.first_name', 'persons.last_name'),
        ];

        if ($person->gender === 'female') {
            $relations['childrenFromMother'] = fn ($q) => $q->orderBy('birth_date');
        } else {
            $relations['children'] = fn ($q) => $q->orderBy('birth_date');
        }

        $person->load($relations);

        $marriages = Marriage::query()
            ->where(function ($q) use ($person) {
                $q->where('husband_id', $person->id)
                    ->orWhere('wife_id', $person->id);
            })
            ->with(['husband:id,first_name,last_name', 'wife:id,first_name,last_name'])
            ->ordered()
            ->get();

        if ($person->gender === 'female') {
            $children = $person->childrenFromMother ?? collect();
        } else {
            $children = $person->children ?? collect();
            if ($children->isNotEmpty()) {
                $children = app(MarriageOrderService::class)
                    ->sortChildrenByMotherOrder($children, (int) $person->id);
            }
        }

        return [
            'person' => $this->serializePerson($person),
            'privacy_settings' => $person->getResolvedPrivacySettings(),
            'parents' => [
                'father' => $person->parent ? $this->serializePersonBrief($person->parent) : null,
                'mother' => $person->mother ? $this->serializePersonBrief($person->mother) : null,
            ],
            'contacts' => $person->contactAccounts->map(fn ($c) => $this->serializeContact($c))->values(),
            'children' => $children->map(fn ($c) => $this->serializePersonBrief($c))->values(),
            'marriages' => $marriages->values()->map(fn ($m, $i) => $this->serializeMarriage($m, $person, $i + 1))->values(),
            'locations' => $person->personLocations->map(fn ($l) => $this->serializeLocation($l))->values(),
        ];
    }

    public function updateProfile(Person $person, array $data, ?UploadedFile $photo = null): Person
    {
        unset($data['parent_id'], $data['mother_id']);

        if ($photo) {
            if ($person->photo_url) {
                Storage::disk('public')->delete($person->photo_url);
            }
            $data['photo_url'] = $photo->store('people-photos', 'public');
        }

        unset($data['photo']);

        $person->update($data);

        return $person->fresh();
    }

    public function storeContact(Person $person, array $data): PersonContactAccount
    {
        $maxOrder = $person->contactAccounts()->max('sort_order');

        return $person->contactAccounts()->create([
            'type' => $data['type'],
            'value' => $this->normalizeContactValue($data['type'], $data['value']),
            'visibility' => in_array($data['visibility'] ?? 'family', ['public', 'family', 'private'], true) ? $data['visibility'] : 'family',
            'label' => $data['label'] ?? null,
            'sort_order' => $data['sort_order'] ?? ($maxOrder ? $maxOrder + 1 : 0),
        ]);
    }

    public function updatePrivacySettings(Person $person, array $settings): array
    {
        $allowedFields = ['contacts', 'photo', 'birth_date', 'location', 'biography', 'occupation'];
        $validLevels = ['public', 'family', 'private'];

        $current = $person->getResolvedPrivacySettings();

        foreach ($allowedFields as $field) {
            if (isset($settings[$field]) && in_array($settings[$field], $validLevels, true)) {
                $current[$field] = $settings[$field];
            }
        }

        $person->privacy_settings = $current;
        $person->save();

        // تنظيف الكاش لضمان سريان التحديث الفوري
        cache()->forget("person_raw_details_{$person->id}");

        return $current;
    }

    public function updateContact(Person $person, PersonContactAccount $contact, array $data): PersonContactAccount
    {
        $this->assertOwnsContact($person, $contact);

        if (isset($data['type'], $data['value'])) {
            $data['value'] = $this->normalizeContactValue($data['type'], $data['value']);
        } elseif (isset($data['value'])) {
            $data['value'] = $this->normalizeContactValue($contact->type, $data['value']);
        }

        $contact->update($data);

        return $contact->fresh();
    }

    /**
     * استخراج رقم نظيف من روابط wa.me عند حفظ واتساب/هاتف.
     */
    protected function normalizeContactValue(string $type, string $value): string
    {
        if (!in_array($type, ['whatsapp', 'phone'], true)) {
            return trim($value);
        }

        $lookup = app(\App\Services\Auth\WhatsappPersonLookup::class);
        $canonical = $lookup->canonicalMobile($value);

        return $canonical ? '+' . $canonical : trim($value);
    }

    public function deleteContact(Person $person, PersonContactAccount $contact): void
    {
        $this->assertOwnsContact($person, $contact);
        $contact->delete();
    }

    public function storeChild(Person $owner, array $data): Person
    {
        if ($owner->gender === 'male') {
            $data['parent_id'] = $owner->id;
            if (empty($data['mother_id'])) {
                throw ValidationException::withMessages([
                    'mother_id' => ['يجب اختيار الأم عند إضافة ابن/ابنة.'],
                ]);
            }
            $this->assertIsWife((int) $owner->id, (int) $data['mother_id']);
        } else {
            $data['mother_id'] = $owner->id;
            if (empty($data['parent_id'])) {
                throw ValidationException::withMessages([
                    'parent_id' => ['يجب اختيار الأب عند إضافة ابن/ابنة.'],
                ]);
            }
        }

        if (!empty($data['photo']) && $data['photo'] instanceof UploadedFile) {
            $data['photo_url'] = $data['photo']->store('people-photos', 'public');
        }
        unset($data['photo']);

        if (!empty($data['location']) && empty($data['location_id'])) {
            $location = Location::findOrCreateByName($data['location']);
            $data['location_id'] = $location->id;
        }
        unset($data['location']);

        return DB::transaction(function () use ($data) {
            $parentId = $data['parent_id'] ?? null;

            $child = Person::create($data);

            if ($parentId) {
                $parent = Person::find($parentId);
                if ($parent) {
                    $child->appendToNode($parent)->save();
                }
            }

            return $child;
        });
    }

    public function storeMarriage(Person $owner, array $data): Marriage
    {
        if ($owner->gender === 'male') {
            $data['husband_id'] = $owner->id;
            if (empty($data['wife_id'])) {
                throw ValidationException::withMessages(['wife_id' => ['يجب اختيار الزوجة.']]);
            }
        } else {
            $data['wife_id'] = $owner->id;
            if (empty($data['husband_id'])) {
                throw ValidationException::withMessages(['husband_id' => ['يجب اختيار الزوج.']]);
            }
        }

        $exists = Marriage::where('husband_id', $data['husband_id'])
            ->where('wife_id', $data['wife_id'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['marriage' => ['هذا الزواج مسجّل بالفعل.']]);
        }

        $data = array_intersect_key($data, array_flip(['husband_id', 'wife_id', 'married_at', 'divorced_at', 'is_divorced', 'sort_order']));
        if (empty($data['sort_order'])) {
            $data['sort_order'] = (int) (Marriage::where('husband_id', $data['husband_id'])->max('sort_order') ?? 0) + 1;
        } else {
            $data['sort_order'] = max(1, min(100, (int) $data['sort_order']));
            app(MarriageOrderService::class)->makeRoomForSortOrder((int) $data['husband_id'], (int) $data['sort_order']);
        }

        $created = Marriage::create($data);
        app(MarriageOrderService::class)->clearMarriageCaches((int) $created->husband_id, (int) $created->wife_id);

        return $created;
    }

    public function updateMarriage(Person $owner, Marriage $marriage, array $data): Marriage
    {
        $this->assertOwnsMarriage($owner, $marriage);

        unset($data['husband_id'], $data['wife_id']);
        $data = array_intersect_key($data, array_flip(['married_at', 'divorced_at', 'is_divorced', 'sort_order']));
        if (array_key_exists('sort_order', $data)) {
            if (empty($data['sort_order'])) {
                unset($data['sort_order']);
            } else {
                $data['sort_order'] = max(1, min(100, (int) $data['sort_order']));
            }
        }

        if (!empty($data)) {
            $marriage->update($data);
        }
        app(MarriageOrderService::class)->clearMarriageCaches((int) $marriage->husband_id, (int) $marriage->wife_id);

        return $marriage->fresh();
    }

    public function storeLocation(Person $person, array $data): PersonLocation
    {
        if ($data['is_primary'] ?? false) {
            $person->personLocations()->update(['is_primary' => false]);
        }

        $maxOrder = $person->personLocations()->max('sort_order');

        return $person->personLocations()->create([
            'location_id' => $data['location_id'],
            'label' => $data['label'] ?? null,
            'url' => $data['url'] ?? null,
            'is_primary' => $data['is_primary'] ?? false,
            'sort_order' => $data['sort_order'] ?? ($maxOrder ? $maxOrder + 1 : 0),
        ]);
    }

    public function updateLocation(Person $person, PersonLocation $location, array $data): PersonLocation
    {
        $this->assertOwnsLocation($person, $location);

        if ($data['is_primary'] ?? false) {
            $person->personLocations()->where('id', '!=', $location->id)->update(['is_primary' => false]);
        }

        $location->update($data);

        return $location->fresh(['location']);
    }

    public function deleteLocation(Person $person, PersonLocation $location): void
    {
        $this->assertOwnsLocation($person, $location);
        $location->delete();
    }

    protected function assertIsWife(int $husbandId, int $wifeId): void
    {
        $isWife = Marriage::where('husband_id', $husbandId)
            ->where('wife_id', $wifeId)
            ->exists();

        if (!$isWife) {
            throw ValidationException::withMessages([
                'mother_id' => ['يجب أن تكون الأم من زوجاتك المسجّلات.'],
            ]);
        }
    }

    protected function assertOwnsContact(Person $person, PersonContactAccount $contact): void
    {
        if ($contact->person_id !== $person->id) {
            abort(403, 'لا يمكنك تعديل هذا الحساب.');
        }
    }

    protected function assertOwnsMarriage(Person $owner, Marriage $marriage): void
    {
        if ($marriage->husband_id !== $owner->id && $marriage->wife_id !== $owner->id) {
            abort(403, 'لا يمكنك تعديل هذا الزواج.');
        }
    }

    protected function assertOwnsLocation(Person $person, PersonLocation $location): void
    {
        if ($location->person_id !== $person->id) {
            abort(403, 'لا يمكنك تعديل هذا الموقع.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializePerson(Person $person): array
    {
        return [
            'id' => $person->id,
            'first_name' => $person->first_name,
            'last_name' => $person->last_name,
            'full_name' => $person->full_name,
            'gender' => $person->gender,
            'birth_date' => optional($person->birth_date)?->format('Y-m-d'),
            'birth_place' => $person->birth_place,
            'death_date' => optional($person->death_date)?->format('Y-m-d'),
            'death_place' => $person->death_place,
            'cemetery' => $person->cemetery,
            'cemetery_location' => $person->cemetery_location,
            'grave_number' => $person->grave_number,
            'biography' => $person->biography,
            'occupation' => $person->occupation,
            'avatar' => $person->avatar,
            'phone' => $person->resolveQuizPhone(),
            'location_id' => $person->location_id,
            'privacy_settings' => $person->getResolvedPrivacySettings(),
        ] + $person->whatsAppStatusPayload();
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializePersonBrief(Person $person): array
    {
        $data = [
            'id' => $person->id,
            'full_name' => $person->full_name,
            'gender' => $person->gender,
            'avatar' => $person->avatar,
            'birth_date' => optional($person->birth_date)?->format('Y-m-d'),
        ];

        if (isset($person->marriage_id)) {
            $data['marriage_id'] = $person->marriage_id;
        }
        if (isset($person->is_divorced)) {
            $data['is_divorced'] = (bool) $person->is_divorced;
        }
        if (isset($person->status_text)) {
            $data['status_text'] = $person->status_text;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeContact(PersonContactAccount $contact): array
    {
        return [
            'id' => $contact->id,
            'type' => $contact->type,
            'value' => $contact->value,
            'visibility' => $contact->visibility ?: 'family',
            'label' => $contact->label,
            'sort_order' => $contact->sort_order,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeMarriage(Marriage $marriage, Person $owner, ?int $orderIndex = null): array
    {
        $spouse = $marriage->husband_id === (int) $owner->id ? $marriage->wife : $marriage->husband;

        return [
            'id' => $marriage->id,
            'spouse' => $spouse ? $this->serializePersonBrief($spouse) : null,
            'married_at' => optional($marriage->married_at)?->format('Y-m-d'),
            'divorced_at' => optional($marriage->divorced_at)?->format('Y-m-d'),
            'is_divorced' => $marriage->isDivorced(),
            'status' => $marriage->status_text,
            'sort_order' => $marriage->sort_order,
            'order_index' => $orderIndex,
            'order_label' => $orderIndex ? app(MarriageOrderService::class)->arabicOrdinal($orderIndex, $owner->gender === 'male' ? 'الزوجة' : 'الزوج') : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeLocation(PersonLocation $location): array
    {
        return [
            'id' => $location->id,
            'location_id' => $location->location_id,
            'location_name' => $location->location?->name,
            'label' => $location->label,
            'url' => $location->url,
            'is_primary' => $location->is_primary,
            'sort_order' => $location->sort_order,
        ];
    }
}
