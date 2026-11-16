<?php

namespace Tests\Unit;

use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonFullNameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Queue::fake();
    }

    public function test_full_name_returns_ancestors_without_bin_for_male(): void
    {
        $grandfather = Person::create([
            'first_name' => 'إبراهيم',
            'gender'     => 'male',
        ]);

        $father = Person::create([
            'first_name' => 'خالد',
            'gender'     => 'male',
            'parent_id'  => $grandfather->id,
        ]);

        $son = Person::create([
            'first_name' => 'عبدالله',
            'gender'     => 'male',
            'parent_id'  => $father->id,
        ]);

        // full_name without 'بن', with 'ال' prefixed to the root ancestor
        $this->assertEquals('عبدالله خالد الإبراهيم', $son->full_name);

        // full_name_with_relations includes 'بن'
        $this->assertEquals('عبدالله بن خالد بن الإبراهيم', $son->full_name_with_relations);

        // aliases match
        $this->assertEquals('عبدالله بن خالد بن الإبراهيم', $son->full_name_with_lineage);
        $this->assertEquals('عبدالله بن خالد بن الإبراهيم', $son->full_name_with_bin);
    }

    public function test_full_name_returns_ancestors_without_bint_for_female(): void
    {
        $grandfather = Person::create([
            'first_name' => 'إبراهيم',
            'gender'     => 'male',
        ]);

        $father = Person::create([
            'first_name' => 'خالد',
            'gender'     => 'male',
            'parent_id'  => $grandfather->id,
        ]);

        $daughter = Person::create([
            'first_name' => 'سارة',
            'gender'     => 'female',
            'parent_id'  => $father->id,
        ]);

        // full_name without 'بنت' or 'بن', with 'ال' on root ancestor
        $this->assertEquals('سارة خالد الإبراهيم', $daughter->full_name);

        // full_name_with_relations includes 'بنت' and 'بن'
        $this->assertEquals('سارة بنت خالد بن الإبراهيم', $daughter->full_name_with_relations);
        $this->assertEquals('سارة بنت خالد بن الإبراهيم', $daughter->full_name_with_lineage);
    }

    public function test_full_name_prefixes_al_for_mohammad_and_abdulaziz(): void
    {
        $rootMohammad = Person::create([
            'first_name' => 'محمد',
            'gender'     => 'male',
        ]);

        $childOfMohammad = Person::create([
            'first_name' => 'حمد',
            'gender'     => 'male',
            'parent_id'  => $rootMohammad->id,
        ]);

        $this->assertEquals('حمد المحمد', $childOfMohammad->full_name);
        $this->assertEquals('حمد بن المحمد', $childOfMohammad->full_name_with_relations);

        $rootAbdulaziz = Person::create([
            'first_name' => 'عبدالعزيز',
            'gender'     => 'male',
        ]);

        $childOfAbdulaziz = Person::create([
            'first_name' => 'سريع',
            'gender'     => 'male',
            'parent_id'  => $rootAbdulaziz->id,
        ]);

        $this->assertEquals('سريع العبدالعزيز', $childOfAbdulaziz->full_name);
        $this->assertEquals('سريع بن العبدالعزيز', $childOfAbdulaziz->full_name_with_relations);
    }

    public function test_full_name_does_not_duplicate_al_if_ancestor_already_has_it(): void
    {
        $rootExternal = Person::create([
            'first_name' => 'محمد الصانع',
            'gender'     => 'male',
        ]);

        $child = Person::create([
            'first_name' => 'عبدالوهاب',
            'gender'     => 'male',
            'parent_id'  => $rootExternal->id,
        ]);

        $this->assertEquals('عبدالوهاب محمد الصانع', $child->full_name);
        $this->assertEquals('عبدالوهاب بن محمد الصانع', $child->full_name_with_relations);
    }

    public function test_single_person_without_parents(): void
    {
        $person = Person::create([
            'first_name' => 'سلمان',
            'gender'     => 'male',
        ]);

        $this->assertEquals('سلمان', $person->full_name);
        $this->assertEquals('سلمان', $person->full_name_with_relations);
    }

    public function test_lineage_stops_when_ancestor_is_female(): void
    {
        $grandfather = Person::create([
            'first_name' => 'محمد',
            'gender'     => 'male',
        ]);

        $mother = Person::create([
            'first_name' => 'فاطمة',
            'gender'     => 'female',
            'parent_id'  => $grandfather->id,
        ]);

        $child = Person::create([
            'first_name' => 'علي',
            'gender'     => 'male',
            'parent_id'  => $mother->id,
        ]);

        // Because parent (mother) is female, male lineage stops
        $this->assertEquals('علي', $child->full_name);
        $this->assertEquals('علي', $child->full_name_with_relations);
    }

    public function test_attributes_included_in_serialization(): void
    {
        $person = Person::create([
            'first_name' => 'سعود',
            'gender'     => 'male',
        ]);

        $array = $person->toArray();

        $this->assertArrayHasKey('full_name', $array);
        $this->assertArrayHasKey('full_name_with_relations', $array);
        $this->assertEquals('سعود', $array['full_name']);
        $this->assertEquals('سعود', $array['full_name_with_relations']);
    }

    public function test_full_name_strips_bare_alif_when_prefixing_al(): void
    {
        $grandfather = Person::create([
            'first_name' => 'ابراهيم', // ألف مجردة (همزة وصل)
            'gender'     => 'male',
        ]);

        $father = Person::create([
            'first_name' => 'خالد',
            'gender'     => 'male',
            'parent_id'  => $grandfather->id,
        ]);

        $son = Person::create([
            'first_name' => 'عبدالله',
            'gender'     => 'male',
            'parent_id'  => $father->id,
        ]);

        $this->assertEquals('عبدالله خالد البراهيم', $son->full_name);
        $this->assertEquals('عبدالله بن خالد بن البراهيم', $son->full_name_with_relations);
    }

    public function test_outside_family_person_combines_first_and_last_name(): void
    {
        $outsideWife = Person::create([
            'first_name' => 'سارة',
            'last_name' => 'العتيبي',
            'gender' => 'female',
            'from_outside_the_family' => true,
        ]);

        $this->assertEquals('سارة العتيبي', $outsideWife->full_name);
        $this->assertEquals('سارة العتيبي', $outsideWife->full_name_with_relations);

        $outsideHusband = Person::create([
            'first_name' => 'عبدالعزيز',
            'last_name' => 'بن الوهيب',
            'gender' => 'male',
            'from_outside_the_family' => true,
        ]);

        $this->assertEquals('عبدالعزيز بن الوهيب', $outsideHusband->full_name);
        $this->assertEquals('عبدالعزيز بن الوهيب', $outsideHusband->full_name_with_relations);
    }

    public function test_outside_family_person_does_not_duplicate_when_already_in_first_name(): void
    {
        $outsidePerson = Person::create([
            'first_name' => 'سارة بنت علي الدوسري',
            'last_name' => 'الدوسري',
            'gender' => 'female',
            'from_outside_the_family' => true,
        ]);

        $this->assertEquals('سارة بنت علي الدوسري', $outsidePerson->full_name);

        $outsidePersonWithPatronymic = Person::create([
            'first_name' => 'حصة بنت عبدالحكيم بن عبدالرحمن بن عقيل',
            'last_name' => 'العقيل',
            'gender' => 'female',
            'from_outside_the_family' => true,
        ]);

        $this->assertEquals('حصة بنت عبدالحكيم بن عبدالرحمن بن عقيل', $outsidePersonWithPatronymic->full_name);
    }

    public function test_outside_family_person_without_last_name_returns_first_name(): void
    {
        $outsidePerson = Person::create([
            'first_name' => 'فاطمة',
            'last_name' => null,
            'gender' => 'female',
            'from_outside_the_family' => true,
        ]);

        $this->assertEquals('فاطمة', $outsidePerson->full_name);
        $this->assertEquals('فاطمة', $outsidePerson->full_name_with_relations);
    }

    public function test_child_of_outside_father_includes_outside_father_last_name_in_lineage(): void
    {
        $outsideFather = Person::create([
            'first_name' => 'حمدان',
            'last_name' => 'الهديب',
            'gender' => 'male',
            'from_outside_the_family' => true,
        ]);

        $child = Person::create([
            'first_name' => 'شريفة',
            'last_name' => 'السريع',
            'gender' => 'female',
            'parent_id' => $outsideFather->id,
            'from_outside_the_family' => true,
        ]);

        $this->assertEquals('شريفة حمدان الهديب', $child->full_name);
        $this->assertEquals('شريفة بنت حمدان الهديب', $child->full_name_with_relations);
    }
}
