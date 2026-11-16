<?php

namespace Tests\Feature\Dashboard;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Marriage;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PersonSearchAndMarriageTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Person $grandfather;
    protected Person $father;
    protected Person $son;
    protected Person $daughter;
    protected Person $outsideWife;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Queue::fake();
        config(['site.password_protection_enabled' => false]);
        $this->withoutMiddleware(VerifyCsrfToken::class);

        // Ensure permissions exist
        $peopleView = Permission::firstOrCreate(['name' => 'people.view', 'guard_name' => 'web']);
        $peopleCreate = Permission::firstOrCreate(['name' => 'people.create', 'guard_name' => 'web']);
        $peopleUpdate = Permission::firstOrCreate(['name' => 'people.update', 'guard_name' => 'web']);
        $marriagesView = Permission::firstOrCreate(['name' => 'marriages.view', 'guard_name' => 'web']);
        $marriagesCreate = Permission::firstOrCreate(['name' => 'marriages.create', 'guard_name' => 'web']);
        $marriagesUpdate = Permission::firstOrCreate(['name' => 'marriages.update', 'guard_name' => 'web']);
        $marriagesDelete = Permission::firstOrCreate(['name' => 'marriages.delete', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo([
            $peopleView, $peopleCreate, $peopleUpdate,
            $marriagesView, $marriagesCreate, $marriagesUpdate, $marriagesDelete
        ]);

        // Create sample family hierarchy
        $this->grandfather = Person::create([
            'first_name' => 'إبراهيم',
            'last_name'  => 'السريّع',
            'gender'     => 'male',
            'birth_date' => '1940-01-01',
            'from_outside_the_family' => false,
        ]);

        $this->father = Person::create([
            'first_name' => 'خالد',
            'last_name'  => 'السريّع',
            'gender'     => 'male',
            'parent_id'  => $this->grandfather->id,
            'birth_date' => '1965-05-10',
            'from_outside_the_family' => false,
        ]);

        $this->son = Person::create([
            'first_name' => 'عبدالله',
            'last_name'  => 'السريّع',
            'gender'     => 'male',
            'parent_id'  => $this->father->id,
            'birth_date' => '1995-03-15',
            'from_outside_the_family' => false,
        ]);

        $this->daughter = Person::create([
            'first_name' => 'سارة',
            'last_name'  => 'السريّع',
            'gender'     => 'female',
            'parent_id'  => $this->father->id,
            'birth_date' => '1998-08-20',
            'from_outside_the_family' => false,
        ]);

        $this->outsideWife = Person::create([
            'first_name' => 'نورة',
            'last_name'  => 'العتيبي',
            'gender'     => 'female',
            'birth_date' => '1997-11-12',
            'from_outside_the_family' => true,
        ]);
    }

    public function test_search_returns_rich_json_structure(): void
    {
        $response = $this->actingAs($this->admin)->getJson(route('persons.search', ['term' => 'عبدالله']));

        $response->assertOk()
            ->assertJsonStructure([
                'results' => [
                    '*' => [
                        'id',
                        'text',
                        'full_name',
                        'first_name',
                        'last_name',
                        'gender',
                        'gender_label',
                        'avatar',
                        'photo_url',
                        'age',
                        'birth_date',
                        'death_date',
                        'is_alive',
                        'from_outside_the_family',
                        'location',
                        'occupation',
                    ]
                ]
            ]);
    }

    public function test_search_by_lineage_and_full_name(): void
    {
        // Searching for grandfather name should match grandson due to lineage search
        // and rank the grandfather FIRST (Relevance Priority: Self > Father > Grandfather)
        $response = $this->actingAs($this->admin)->getJson(route('persons.search', ['term' => 'إبراهيم']));

        $response->assertOk();
        $results = $response->json('results');
        $ids = collect($results)->pluck('id')->toArray();

        $this->assertContains($this->grandfather->id, $ids);
        $this->assertContains($this->father->id, $ids);
        $this->assertContains($this->son->id, $ids);

        // Grandfather (Self match) MUST be ranked first
        $this->assertEquals($this->grandfather->id, $ids[0]);
    }

    public function test_search_ranks_patronymic_compound_name_first(): void
    {
        // Searching for "عبدالله بن خالد" should rank the son ("عبدالله بن خالد") at the very top
        $response = $this->actingAs($this->admin)->getJson(route('persons.search', ['term' => 'عبدالله بن خالد']));

        $response->assertOk();
        $results = $response->json('results');
        $this->assertNotEmpty($results);
        $this->assertEquals($this->son->id, $results[0]['id']);

        // Even without "بن", searching "عبدالله خالد" should rank the son first
        $response2 = $this->actingAs($this->admin)->getJson(route('persons.search', ['term' => 'عبدالله خالد']));
        $response2->assertOk();
        $results2 = $response2->json('results');
        $this->assertNotEmpty($results2);
        $this->assertEquals($this->son->id, $results2[0]['id']);
    }

    public function test_search_filters_by_gender(): void
    {
        // Search males only
        $maleResponse = $this->actingAs($this->admin)->getJson(route('persons.search', [
            'term'   => 'السريّع',
            'gender' => 'male',
        ]));

        $maleResponse->assertOk();
        $maleResults = $maleResponse->json('results');
        foreach ($maleResults as $result) {
            $this->assertEquals('male', $result['gender']);
        }

        // Search females only
        $femaleResponse = $this->actingAs($this->admin)->getJson(route('persons.search', [
            'term'   => 'السريّع',
            'gender' => 'female',
        ]));

        $femaleResponse->assertOk();
        $femaleResults = $femaleResponse->json('results');
        foreach ($femaleResults as $result) {
            $this->assertEquals('female', $result['gender']);
        }
    }

    public function test_search_supports_exclude_id(): void
    {
        $response = $this->actingAs($this->admin)->getJson(route('persons.search', [
            'term'       => 'السريّع',
            'exclude_id' => $this->son->id,
        ]));

        $response->assertOk();
        $ids = collect($response->json('results'))->pluck('id')->toArray();
        $this->assertNotContains($this->son->id, $ids);
    }

    public function test_search_preloads_single_id(): void
    {
        $response = $this->actingAs($this->admin)->getJson(route('persons.search', [
            'id' => $this->son->id,
        ]));

        $response->assertOk();
        $results = $response->json('results');
        $this->assertCount(1, $results);
        $this->assertEquals($this->son->id, $results[0]['id']);
        $this->assertEquals($this->son->full_name, $results[0]['full_name']);
    }

    public function test_search_supports_include_outside_flag(): void
    {
        $response = $this->actingAs($this->admin)->getJson(route('persons.search', [
            'term'            => 'نورة',
            'include_outside' => 0,
        ]));

        $response->assertOk();
        $ids = collect($response->json('results'))->pluck('id')->toArray();
        $this->assertNotContains($this->outsideWife->id, $ids);
    }

    public function test_marriages_index_loads_successfully(): void
    {
        Marriage::create([
            'husband_id' => $this->son->id,
            'wife_id'    => $this->outsideWife->id,
            'married_at' => '2020-06-01',
        ]);

        $response = $this->actingAs($this->admin)->get(route('marriages.index'));
        $response->assertOk()
            ->assertSee($this->son->full_name)
            ->assertSee($this->outsideWife->full_name);
    }

    public function test_can_create_marriage_record(): void
    {
        $response = $this->actingAs($this->admin)->post(route('marriages.store'), [
            'husband_id' => $this->son->id,
            'wife_id'    => $this->outsideWife->id,
            'married_at' => '2022-01-15',
        ]);

        $response->assertRedirect(route('marriages.index'));
        $marriage = Marriage::where('husband_id', $this->son->id)
            ->where('wife_id', $this->outsideWife->id)
            ->first();
        $this->assertNotNull($marriage);
        $this->assertEquals('2022-01-15', \Carbon\Carbon::parse($marriage->married_at)->format('Y-m-d'));
    }

    public function test_cannot_marry_same_person(): void
    {
        $response = $this->actingAs($this->admin)->post(route('marriages.store'), [
            'husband_id' => $this->son->id,
            'wife_id'    => $this->son->id,
        ]);

        $response->assertSessionHasErrors('wife_id');
    }

    public function test_reorder_people_updates_display_order_and_clears_caches(): void
    {
        cache()->put('family_tree_data', 'cached_tree', 3600);
        cache()->put("person_children_{$this->father->id}", 'cached_children', 3600);

        $response = $this->actingAs($this->admin)->postJson(route('people.reorder'), [
            'order' => [
                ['id' => $this->son->id, 'order' => 2],
                ['id' => $this->daughter->id, 'order' => 1],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'success']);

        $this->assertEquals(2, $this->son->fresh()->display_order);
        $this->assertEquals(1, $this->daughter->fresh()->display_order);

        $this->assertNull(cache()->get('family_tree_data'));
        $this->assertNull(cache()->get("person_children_{$this->father->id}"));
    }

    public function test_marriages_reorder_updates_sort_order_and_clears_caches(): void
    {
        $wife1 = Person::create(['first_name' => 'زوجة 1', 'gender' => 'female']);
        $wife2 = Person::create(['first_name' => 'زوجة 2', 'gender' => 'female']);

        $m1 = Marriage::create(['husband_id' => $this->father->id, 'wife_id' => $wife1->id, 'sort_order' => 1]);
        $m2 = Marriage::create(['husband_id' => $this->father->id, 'wife_id' => $wife2->id, 'sort_order' => 2]);

        cache()->put('family_tree_data', 'cached_tree', 3600);
        cache()->put("person_children_{$this->father->id}", 'cached_children', 3600);

        // إعادة ترتيب الزوجات بالسحب والإفلات لتقديم الزوجة الثانية
        $response = $this->actingAs($this->admin)->postJson(route('marriages.reorder'), [
            'husband_id' => $this->father->id,
            'ordered_ids' => [$m2->id, $m1->id],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertEquals(1, $m2->fresh()->sort_order);
        $this->assertEquals(2, $m1->fresh()->sort_order);

        $this->assertNull(cache()->get('family_tree_data'));
        $this->assertNull(cache()->get("person_children_{$this->father->id}"));
    }

    public function test_marriages_ordered_scope_prioritizes_marriage_date_with_sort_order_fallback(): void
    {
        $wifeDatedOlder = Person::create(['first_name' => 'تاريخ زواج قديم', 'gender' => 'female']);
        $wifeDatedNewer = Person::create(['first_name' => 'تاريخ زواج أحدث', 'gender' => 'female']);
        $wifeUndatedManualFirst = Person::create(['first_name' => 'بدون تاريخ مرتبة يدويا 1', 'gender' => 'female']);
        $wifeUndatedManualSecond = Person::create(['first_name' => 'بدون تاريخ مرتبة يدويا 2', 'gender' => 'female']);

        // زواج بتاريخ 2000 ولكن sort_order = 5
        $m1 = Marriage::create([
            'husband_id' => $this->father->id,
            'wife_id' => $wifeDatedOlder->id,
            'married_at' => '2000-01-01',
            'sort_order' => 5,
        ]);

        // زواج بتاريخ 2010 و sort_order = 1
        $m2 = Marriage::create([
            'husband_id' => $this->father->id,
            'wife_id' => $wifeDatedNewer->id,
            'married_at' => '2010-01-01',
            'sort_order' => 1,
        ]);

        // زواج بدون تاريخ و sort_order = 1
        $m3 = Marriage::create([
            'husband_id' => $this->father->id,
            'wife_id' => $wifeUndatedManualFirst->id,
            'married_at' => null,
            'sort_order' => 1,
        ]);

        // زواج بدون تاريخ و sort_order = 2
        $m4 = Marriage::create([
            'husband_id' => $this->father->id,
            'wife_id' => $wifeUndatedManualSecond->id,
            'married_at' => null,
            'sort_order' => 2,
        ]);

        $orderedIds = Marriage::where('husband_id', $this->father->id)
            ->ordered()
            ->pluck('id')
            ->all();

        // المتوقع: أصحاب تواريخ الزواج أولاً بالأقدم (2000 ثم 2010)، ثم من لا يملكون تاريخاً بحسب sort_order (1 ثم 2)
        $this->assertEquals([$m1->id, $m2->id, $m3->id, $m4->id], $orderedIds);
    }
}
