<?php

namespace Tests\Unit;

use App\Models\Marriage;
use App\Models\Person;
use App\Services\MarriageOrderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ChildrenSortingOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    /**
     * اختبار سيناريو تداخل تواريخ الميلاد بين عدة زوجات (الأكبر سناً أولاً بغض النظر عن الزوجة).
     */
    public function test_children_from_multiple_wives_are_sorted_chronologically_by_birth_date(): void
    {
        $father = Person::create(['first_name' => 'الأب', 'gender' => 'male']);
        $wife1 = Person::create(['first_name' => 'الزوجة الأولى', 'gender' => 'female']);
        $wife2 = Person::create(['first_name' => 'الزوجة الثانية', 'gender' => 'female']);
        $wife3 = Person::create(['first_name' => 'الزوجة الثالثة', 'gender' => 'female']);

        // ترتيب الزيجات
        Marriage::create(['husband_id' => $father->id, 'wife_id' => $wife1->id, 'sort_order' => 1]);
        Marriage::create(['husband_id' => $father->id, 'wife_id' => $wife2->id, 'sort_order' => 2]);
        Marriage::create(['husband_id' => $father->id, 'wife_id' => $wife3->id, 'sort_order' => 3]);

        // أبناء بتواريخ متداخلة
        $child1 = Person::create([
            'first_name' => 'ابن 1 (الزوجة 1)',
            'gender' => 'male',
            'parent_id' => $father->id,
            'mother_id' => $wife1->id,
            'birth_date' => Carbon::parse('2000-01-01'),
        ]);

        $child2 = Person::create([
            'first_name' => 'ابن 2 (الزوجة 2)',
            'gender' => 'male',
            'parent_id' => $father->id,
            'mother_id' => $wife2->id,
            'birth_date' => Carbon::parse('2002-05-10'),
        ]);

        $child3 = Person::create([
            'first_name' => 'ابنة 1 (الزوجة 3)',
            'gender' => 'female',
            'parent_id' => $father->id,
            'mother_id' => $wife3->id,
            'birth_date' => Carbon::parse('2004-03-15'),
        ]);

        $child4 = Person::create([
            'first_name' => 'ابن 3 (الزوجة 1)',
            'gender' => 'male',
            'parent_id' => $father->id,
            'mother_id' => $wife1->id,
            'birth_date' => Carbon::parse('2006-08-20'),
        ]);

        $child5 = Person::create([
            'first_name' => 'ابن 4 (الزوجة 2)',
            'gender' => 'male',
            'parent_id' => $father->id,
            'mother_id' => $wife2->id,
            'birth_date' => Carbon::parse('2008-11-25'),
        ]);

        $children = collect([$child5, $child3, $child4, $child1, $child2]);
        $sorted = app(MarriageOrderService::class)->sortChildrenByMotherOrder($children, $father->id);

        $expectedIds = [$child1->id, $child2->id, $child3->id, $child4->id, $child5->id];
        $this->assertEquals($expectedIds, $sorted->pluck('id')->all());
    }

    /**
     * اختبار في حال غياب تاريخ الميلاد تماماً: يتم الاعتماد على الترتيب اليدوي (display_order) ثم ترتيب الزوجة.
     */
    public function test_children_without_birth_dates_fallback_to_display_order_then_wife_order(): void
    {
        $father = Person::create(['first_name' => 'الأب', 'gender' => 'male']);
        $wife1 = Person::create(['first_name' => 'الأولى', 'gender' => 'female']);
        $wife2 = Person::create(['first_name' => 'الثانية', 'gender' => 'female']);

        Marriage::create(['husband_id' => $father->id, 'wife_id' => $wife1->id, 'sort_order' => 1]);
        Marriage::create(['husband_id' => $father->id, 'wife_id' => $wife2->id, 'sort_order' => 2]);

        // ابن من الزوجة 2 لكن الآدمن رتبه أولاً يدوياً عبر display_order
        $childWife2ManualFirst = Person::create([
            'first_name' => 'ابن الزوجة الثانية مرتب يدوياً 1',
            'gender' => 'male',
            'parent_id' => $father->id,
            'mother_id' => $wife2->id,
            'birth_date' => null,
            'display_order' => 1,
        ]);

        // ابن من الزوجة 1 مرتب يدوياً ثانياً
        $childWife1ManualSecond = Person::create([
            'first_name' => 'ابن الزوجة الأولى مرتب يدوياً 2',
            'gender' => 'male',
            'parent_id' => $father->id,
            'mother_id' => $wife1->id,
            'birth_date' => null,
            'display_order' => 2,
        ]);

        // ابن من الأولى بدون ترتيب يدوي وبدون تاريخ
        $childWife1NoOrder = Person::create([
            'first_name' => 'ابن الزوجة الأولى بدون ترتيب',
            'gender' => 'male',
            'parent_id' => $father->id,
            'mother_id' => $wife1->id,
            'birth_date' => null,
            'display_order' => 0,
        ]);

        // ابن من الثانية بدون ترتيب يدوي وبدون تاريخ
        $childWife2NoOrder = Person::create([
            'first_name' => 'ابن الزوجة الثانية بدون ترتيب',
            'gender' => 'male',
            'parent_id' => $father->id,
            'mother_id' => $wife2->id,
            'birth_date' => null,
            'display_order' => 0,
        ]);

        $children = collect([$childWife2NoOrder, $childWife1NoOrder, $childWife1ManualSecond, $childWife2ManualFirst]);
        $sorted = app(MarriageOrderService::class)->sortChildrenByMotherOrder($children, $father->id);

        $expectedIds = [
            $childWife2ManualFirst->id,
            $childWife1ManualSecond->id,
            $childWife1NoOrder->id,
            $childWife2NoOrder->id,
        ];

        $this->assertEquals($expectedIds, $sorted->pluck('id')->all());
    }

    /**
     * اختبار المفاضلة بين أصحاب التواريخ المعلومة ومن لا يملكون تاريخاً.
     */
    public function test_children_with_birth_date_precede_undated_children(): void
    {
        $father = Person::create(['first_name' => 'الأب', 'gender' => 'male']);
        $wife1 = Person::create(['first_name' => 'الأولى', 'gender' => 'female']);

        Marriage::create(['husband_id' => $father->id, 'wife_id' => $wife1->id, 'sort_order' => 1]);

        $datedChild = Person::create([
            'first_name' => 'ابن بتاريخ',
            'gender' => 'male',
            'parent_id' => $father->id,
            'mother_id' => $wife1->id,
            'birth_date' => Carbon::parse('2010-01-01'),
        ]);

        $undatedChild = Person::create([
            'first_name' => 'ابن بدون تاريخ',
            'gender' => 'male',
            'parent_id' => $father->id,
            'mother_id' => $wife1->id,
            'birth_date' => null,
        ]);

        $children = collect([$undatedChild, $datedChild]);
        $sorted = app(MarriageOrderService::class)->sortChildrenByMotherOrder($children, $father->id);

        $this->assertEquals([$datedChild->id, $undatedChild->id], $sorted->pluck('id')->all());
    }
}
