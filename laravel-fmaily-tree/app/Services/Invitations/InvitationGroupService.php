<?php

namespace App\Services\Invitations;

use App\Exceptions\InvitationException;
use App\Models\NotificationGroup;
use App\Models\Person;
use Illuminate\Support\Collection;

class InvitationGroupService
{
    public function ownedByPerson(int $personId, bool $excludeOwner = false): Collection
    {
        return NotificationGroup::query()
            ->where('person_id', $personId)
            ->withCount($excludeOwner ? ['persons' => fn ($members) => $members->whereKeyNot($personId)] : 'persons')
            ->latest()
            ->get();
    }

    public function findOwned(int $groupId, int $personId): NotificationGroup
    {
        $group = NotificationGroup::query()
            ->where('id', $groupId)
            ->where('person_id', $personId)
            ->first();

        if (! $group) {
            throw new InvitationException(
                'المجموعة غير موجودة أو ليست ملكك.',
                'GROUP_NOT_OWNED',
                403
            );
        }

        return $group;
    }

    /**
     * @param  list<int>  $personIds
     */
    public function create(int $ownerPersonId, string $name, ?string $description = null, array $personIds = []): NotificationGroup
    {
        foreach (array_unique(array_map('intval', $personIds)) as $personId) {
            $this->assertHasWhatsApp($personId);
        }

        $group = NotificationGroup::create([
            'name' => $name,
            'description' => $description,
            'person_id' => $ownerPersonId,
            'user_id' => null,
        ]);

        if ($personIds !== []) {
            $this->syncPersons($group, $personIds);
        }

        return $group->loadCount('persons')->load('persons');
    }

    public function update(NotificationGroup $group, string $name, ?string $description = null): NotificationGroup
    {
        $group->update([
            'name' => $name,
            'description' => $description,
        ]);

        return $group->fresh(['persons'])->loadCount('persons');
    }

    public function delete(NotificationGroup $group): void
    {
        $group->delete();
    }

    public function attachPerson(NotificationGroup $group, int $personId): void
    {
        $this->assertHasWhatsApp($personId);

        if ($group->persons()->where('persons.id', $personId)->exists()) {
            return;
        }

        $maxOrder = (int) ($group->persons()->max('notification_group_person.sort_order') ?? 0);
        $group->persons()->syncWithoutDetaching([
            $personId => ['sort_order' => $maxOrder + 1],
        ]);
    }

    public function detachPerson(NotificationGroup $group, int $personId): void
    {
        $group->persons()->detach($personId);
    }

    /**
     * @param  list<int>  $personIds
     */
    public function syncPersons(NotificationGroup $group, array $personIds): void
    {
        $uniqueIds = array_values(array_unique(array_map('intval', $personIds)));
        $payload = [];

        foreach ($uniqueIds as $index => $personId) {
            $this->assertHasWhatsApp($personId);
            $payload[$personId] = ['sort_order' => $index + 1];
        }

        $group->persons()->sync($payload);
    }

    public function assertHasWhatsApp(int $personId): Person
    {
        $person = Person::query()->with('contactAccounts')->find($personId);

        if (! $person) {
            throw new InvitationException('الشخص غير موجود.', 'PERSON_NOT_FOUND', 404);
        }

        if ($this->whatsAppNumbers($person) === []) {
            throw new InvitationException(
                'لا يمكن إضافة هذا الشخص — لا يوجد رقم واتساب.',
                'PERSON_HAS_NO_WHATSAPP',
                422
            );
        }

        return $person;
    }

    /**
     * @return list<string>
     */
    public function whatsAppNumbers(Person $person): array
    {
        if (! $person->relationLoaded('contactAccounts')) {
            $person->load('contactAccounts');
        }

        $numbers = [];

        foreach ($person->contactAccounts->where('type', 'whatsapp') as $account) {
            $clean = preg_replace('/[^0-9]/', '', (string) $account->value);
            if ($clean && ! in_array($clean, $numbers, true)) {
                $numbers[] = $clean;
            }
        }

        return $numbers;
    }

    /**
     * @return list<int>
     */
    public function personIdsWithWhatsApp(): array
    {
        return Person::query()
            ->whereHas('contactAccounts', function ($query) {
                $query->whereIn('type', ['whatsapp', 'phone'])
                    ->whereNotNull('value')
                    ->where('value', '!=', '');
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
