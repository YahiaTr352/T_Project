<?php

namespace App\Services\Invitations;

use App\Models\Person;
use App\Services\AI\Tools\PersonLookupTool;
use Illuminate\Support\Facades\Cache;

class InvitationTreePickerService
{
    public function __construct(
        protected InvitationGroupService $groups,
        protected PersonLookupTool $personLookup,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function fullTree(): array
    {
        return Cache::remember('invitation_tree_picker_v2', 600, function () {
            return $this->buildTree();
        });
    }

    /**
     * @return array<int, array{has_whatsapp: bool, selectable: bool, whatsapp_descendant_count: int, expandable: bool}>
     */
    public function flagsMap(): array
    {
        $flags = [];
        $walk = function (array $nodes) use (&$walk, &$flags): void {
            foreach ($nodes as $node) {
                $flags[(int) $node['id']] = [
                    'has_whatsapp' => (bool) $node['has_whatsapp'],
                    'selectable' => (bool) $node['selectable'],
                    'whatsapp_descendant_count' => (int) $node['whatsapp_descendant_count'],
                    'expandable' => (bool) $node['expandable'],
                ];
                $walk($node['children'] ?? []);
            }
        };
        $walk($this->fullTree());

        return $flags;
    }

    public function forgetCache(): void
    {
        Cache::forget('invitation_tree_picker');
        Cache::forget('invitation_tree_picker_v2');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $query, int $limit = 40): array
    {
        $term = trim($query);
        if ($term === '') {
            return [];
        }

        $whatsappIds = array_flip($this->groups->personIdsWithWhatsApp());
        $people = $this->personLookup->search($term, $limit);

        $people->each(function (Person $person) {
            $person->loadMissing(['parent.parent.parent.parent.parent', 'contactAccounts']);
        });

        return $people->map(function (Person $person) use ($whatsappIds) {
            $hasWhatsapp = isset($whatsappIds[$person->id]);

            return [
                'id' => $person->id,
                'first_name' => $person->first_name,
                'full_name' => $person->full_name,
                'photo_url' => $person->avatar,
                'gender' => $person->gender,
                'has_whatsapp' => $hasWhatsapp,
                'selectable' => $hasWhatsapp && $person->death_date === null,
                'is_deceased' => $person->death_date !== null,
            ];
        })->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildTree(): array
    {
        $whatsappIds = array_flip($this->groups->personIdsWithWhatsApp());

        $people = Person::query()
            ->select(['id', 'first_name', 'last_name', 'gender', 'photo_url', 'death_date', 'parent_id', 'from_outside_the_family'])
            ->where('from_outside_the_family', false)
            ->get();

        $childrenMap = [];
        foreach ($people as $person) {
            if ($person->parent_id) {
                $childrenMap[(int) $person->parent_id][] = $person;
            }
        }

        $roots = $people->filter(fn (Person $person) => $person->parent_id === null);

        $buildNode = function (Person $person) use (&$buildNode, $childrenMap, $whatsappIds): array {
            $childPeople = $childrenMap[$person->id] ?? [];
            $children = [];
            $descendantCount = 0;

            foreach ($childPeople as $child) {
                $childNode = $buildNode($child);
                $children[] = $childNode;
                $descendantCount += ($childNode['has_whatsapp'] ? 1 : 0) + $childNode['whatsapp_descendant_count'];
            }

            $hasWhatsapp = isset($whatsappIds[$person->id]);

            return [
                'id' => $person->id,
                'first_name' => $person->first_name,
                'photo_url' => $person->avatar,
                'gender' => $person->gender,
                'has_whatsapp' => $hasWhatsapp,
                'selectable' => $hasWhatsapp && $person->death_date === null,
                'is_deceased' => $person->death_date !== null,
                'whatsapp_descendant_count' => $descendantCount,
                'expandable' => $children !== [],
                'children' => $children,
            ];
        };

        return $roots->map(fn (Person $person) => $buildNode($person))->values()->all();
    }
}
