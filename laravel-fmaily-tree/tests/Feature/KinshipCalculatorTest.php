<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\Marriage;
use App\Services\KinshipCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KinshipCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected KinshipCalculatorService $kinshipService;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Queue::fake();
        $this->kinshipService = app(KinshipCalculatorService::class);
    }

    /** @test */
    public function it_calculates_kinship_for_same_person()
    {
        $person = Person::factory()->create([
            'first_name' => 'سليمان',
            'last_name' => 'السريّع',
            'gender' => 'male',
        ]);

        $result = $this->kinshipService->calculateKinship($person->id, $person->id);

        $this->assertEquals('نفس الشخص', $result->relationship['title']);
        $this->assertEquals(0, $result->relationship['total_degree']);
    }

    /** @test */
    public function it_calculates_father_and_son_relationship()
    {
        $father = Person::factory()->create([
            'first_name' => 'محمد',
            'last_name' => 'السريّع',
            'gender' => 'male',
        ]);

        $son = Person::factory()->create([
            'first_name' => 'أحمد',
            'last_name' => 'السريّع',
            'gender' => 'male',
            'parent_id' => $father->id,
        ]);

        // From Son to Father -> Father (أب)
        $resultA = $this->kinshipService->calculateKinship($son->id, $father->id);
        $this->assertEquals('أب', $resultA->relationship['title']);
        $this->assertEquals(1, $resultA->relationship['distance_a']);
        $this->assertEquals(0, $resultA->relationship['distance_b']);

        // From Father to Son -> Son (ابن)
        $resultB = $this->kinshipService->calculateKinship($father->id, $son->id);
        $this->assertEquals('ابن', $resultB->relationship['title']);
        $this->assertEquals(0, $resultB->relationship['distance_a']);
        $this->assertEquals(1, $resultB->relationship['distance_b']);
    }

    /** @test */
    public function it_calculates_brother_relationship()
    {
        $father = Person::factory()->create(['first_name' => 'سليمان', 'gender' => 'male']);

        $brotherA = Person::factory()->create([
            'first_name' => 'علي',
            'gender' => 'male',
            'parent_id' => $father->id,
        ]);

        $brotherB = Person::factory()->create([
            'first_name' => 'خالد',
            'gender' => 'male',
            'parent_id' => $father->id,
        ]);

        $result = $this->kinshipService->calculateKinship($brotherA->id, $brotherB->id);

        $this->assertStringContainsString('أخ', $result->relationship['title']);
        $this->assertEquals($father->id, $result->relationship['common_ancestor']['id']);
        $this->assertEquals(2, $result->relationship['total_degree']);
    }

    /** @test */
    public function it_calculates_first_cousin_relationship()
    {
        $grandfather = Person::factory()->create(['first_name' => 'عبد الله', 'gender' => 'male']);

        $fatherA = Person::factory()->create(['first_name' => 'سعد', 'gender' => 'male', 'parent_id' => $grandfather->id]);
        $fatherB = Person::factory()->create(['first_name' => 'فهد', 'gender' => 'male', 'parent_id' => $grandfather->id]);

        $cousinA = Person::factory()->create(['first_name' => 'طرق', 'gender' => 'male', 'parent_id' => $fatherA->id]);
        $cousinB = Person::factory()->create(['first_name' => 'سعود', 'gender' => 'male', 'parent_id' => $fatherB->id]);

        $result = $this->kinshipService->calculateKinship($cousinA->id, $cousinB->id);

        $this->assertStringContainsString('ابن عم', $result->relationship['title']);
        $this->assertEquals($grandfather->id, $result->relationship['common_ancestor']['id']);
        $this->assertEquals(4, $result->relationship['total_degree']);
    }

    /** @test */
    public function it_falls_back_to_virtual_family_root_for_disjoint_branches()
    {
        $branchRootA = Person::factory()->create(['first_name' => 'إبراهيم', 'gender' => 'male', 'parent_id' => null]);
        $branchRootB = Person::factory()->create(['first_name' => 'عبد العزيز', 'gender' => 'male', 'parent_id' => null]);

        $result = $this->kinshipService->calculateKinship($branchRootA->id, $branchRootB->id);

        $this->assertEquals('family_root', $result->relationship['type']);
        $this->assertStringContainsString('السريّع', $result->relationship['title']);
        $this->assertTrue($result->relationship['common_ancestor']['is_virtual_family_root']);
    }

    /** @test */
    public function it_returns_standardized_json_response_via_api_endpoint()
    {
        $father = Person::factory()->create(['first_name' => 'محمد', 'gender' => 'male']);
        $son = Person::factory()->create(['first_name' => 'سليم', 'gender' => 'male', 'parent_id' => $father->id]);

        $response = $this->withSession([
            'site_password_verified' => true,
            'site_auth_method' => 'pin',
        ])->getJson("/api/v1/kinship/calculate?person_a_id={$son->id}&person_b_id={$father->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'تم حساب صلة القرابة والنسب بنجاح.',
                'errors' => null,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'person_a',
                    'person_b',
                    'relationship' => [
                        'title',
                        'type',
                        'common_ancestor',
                        'distance_a',
                        'distance_b',
                        'total_degree',
                    ],
                    'path',
                ],
                'message',
                'errors',
            ]);
    }

    /** @test */
    public function pin_session_does_not_resolve_viewer_even_with_stale_site_person_id()
    {
        $person = Person::factory()->create();
        $other = Person::factory()->create();

        $this->withSession([
            'site_password_verified' => true,
            'site_auth_method' => 'pin',
            'site_person_id' => $person->id,
        ]);

        $this->assertNull($this->kinshipService->resolvePersonForUser());

        $response = $this->getJson("/api/v1/kinship/calculate?person_b_id={$other->id}");

        $response->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.requires_person_a_selection', true)
            ->assertJsonPath('data.requires_identity', true);
    }

    /** @test */
    public function face_session_uses_site_person_id_as_person_a()
    {
        $father = Person::factory()->create([
            'first_name' => 'محمد',
            'gender' => 'male',
        ]);
        $viewer = Person::factory()->create([
            'first_name' => 'أحمد',
            'gender' => 'male',
            'parent_id' => $father->id,
        ]);

        $response = $this->withSession([
            'site_password_verified' => true,
            'site_auth_method' => 'face',
            'site_person_id' => $viewer->id,
        ])->getJson("/api/v1/kinship/calculate?person_b_id={$father->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.person_a.id', $viewer->id)
            ->assertJsonPath('data.relationship.title', 'أب');
    }

    /** @test */
    public function whatsapp_session_uses_site_person_id_as_person_a()
    {
        $father = Person::factory()->create([
            'first_name' => 'سعد',
            'gender' => 'male',
        ]);
        $viewer = Person::factory()->create([
            'first_name' => 'فهد',
            'gender' => 'male',
            'parent_id' => $father->id,
        ]);

        $response = $this->withSession([
            'site_password_verified' => true,
            'site_auth_method' => 'whatsapp',
            'site_person_id' => $viewer->id,
        ])->getJson("/api/v1/kinship/calculate?person_b_id={$father->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.person_a.id', $viewer->id);
    }

    /** @test */
    public function it_calculates_kinship_and_includes_upper_chain_above_common_ancestor_up_to_family_root()
    {
        // Lineage tree:
        // Root (سريع) -> G2 (راشد) -> G1 (عبد الله) -> LCA (إبراهيم)
        // -> FatherA (محمد) -> PersonA (عبد الله)
        // -> FatherB (فهد) -> PersonB (خالد)
        $root = Person::factory()->create(['first_name' => 'سريع', 'gender' => 'male', 'parent_id' => null]);
        $g2 = Person::factory()->create(['first_name' => 'راشد', 'gender' => 'male', 'parent_id' => $root->id]);
        $g1 = Person::factory()->create(['first_name' => 'عبد الله', 'gender' => 'male', 'parent_id' => $g2->id]);
        $lca = Person::factory()->create(['first_name' => 'إبراهيم', 'gender' => 'male', 'parent_id' => $g1->id]);

        $fatherA = Person::factory()->create(['first_name' => 'محمد', 'gender' => 'male', 'parent_id' => $lca->id]);
        $personA = Person::factory()->create(['first_name' => 'عبد الله', 'gender' => 'male', 'parent_id' => $fatherA->id]);

        $fatherB = Person::factory()->create(['first_name' => 'فهد', 'gender' => 'male', 'parent_id' => $lca->id]);
        $personB = Person::factory()->create(['first_name' => 'خالد', 'gender' => 'male', 'parent_id' => $fatherB->id]);

        $result = $this->kinshipService->calculateKinship($personA->id, $personB->id);

        $this->assertEquals($lca->id, $result->relationship['common_ancestor']['id']);
        $this->assertCount(3, $result->branchA); // [personA, fatherA, lca]
        $this->assertCount(3, $result->branchB); // [personB, fatherB, lca]

        // Upper chain should contain: Virtual Root ('السريّع'), Root ('سريع'), G2 ('راشد'), G1 ('عبد الله')
        $this->assertNotNull($result->upperChain);
        $this->assertCount(4, $result->upperChain);
        $this->assertEquals('السريّع', $result->upperChain[0]['first_name']);
        $this->assertEquals('سريع', $result->upperChain[1]['first_name']);
        $this->assertEquals('راشد', $result->upperChain[2]['first_name']);
        $this->assertEquals('عبد الله', $result->upperChain[3]['first_name']);
    }

    /** @test */
    public function it_returns_full_ancestry_chain_for_disjoint_branches_without_truncation()
    {
        // 5 generations for Branch A
        $f5 = Person::factory()->create(['first_name' => 'جد5', 'gender' => 'male', 'parent_id' => null]);
        $f4 = Person::factory()->create(['first_name' => 'جد4', 'gender' => 'male', 'parent_id' => $f5->id]);
        $f3 = Person::factory()->create(['first_name' => 'جد3', 'gender' => 'male', 'parent_id' => $f4->id]);
        $f2 = Person::factory()->create(['first_name' => 'جد2', 'gender' => 'male', 'parent_id' => $f3->id]);
        $f1 = Person::factory()->create(['first_name' => 'أب1', 'gender' => 'male', 'parent_id' => $f2->id]);
        $personA = Person::factory()->create(['first_name' => 'شخص_أ', 'gender' => 'male', 'parent_id' => $f1->id]);

        // 4 generations for Branch B
        $g4 = Person::factory()->create(['first_name' => 'سلف4', 'gender' => 'male', 'parent_id' => null]);
        $g3 = Person::factory()->create(['first_name' => 'سلف3', 'gender' => 'male', 'parent_id' => $g4->id]);
        $g2 = Person::factory()->create(['first_name' => 'سلف2', 'gender' => 'male', 'parent_id' => $g3->id]);
        $g1 = Person::factory()->create(['first_name' => 'أب_ب', 'gender' => 'male', 'parent_id' => $g2->id]);
        $personB = Person::factory()->create(['first_name' => 'شخص_ب', 'gender' => 'male', 'parent_id' => $g1->id]);

        $result = $this->kinshipService->calculateKinship($personA->id, $personB->id);

        $this->assertEquals('family_root', $result->relationship['type']);
        $this->assertCount(6, $result->branchA); // personA + 5 ancestors
        $this->assertCount(5, $result->branchB); // personB + 4 ancestors
        $this->assertEquals('السريّع', $result->relationship['common_ancestor']['first_name']);
        $this->assertEmpty($result->upperChain); // upperChain is empty because common_ancestor is already the virtual root (avoids duplicate root display)
    }

    /** @test */
    public function it_rejects_comparing_a_person_to_themselves()
    {
        $person = Person::factory()->create();

        $response = $this->withSession([
            'site_password_verified' => true,
            'site_auth_method' => 'pin',
        ])->getJson("/api/v1/kinship/calculate?person_a_id={$person->id}&person_b_id={$person->id}");

        $response->assertOk()
            ->assertJson([
                'success' => false,
                'data' => ['is_self' => true],
                'message' => 'لا يمكن مقارنة الشخص بنفسه.',
            ]);
    }

    /** @test */
    public function it_calculates_direct_marriage_with_outside_spouse_without_attaching_outside_spouse_to_virtual_root()
    {
        $father = Person::factory()->create([
            'first_name' => 'عبد الرحمن',
            'gender' => 'male',
            'from_outside_the_family' => false,
        ]);

        $wife = Person::factory()->create([
            'first_name' => 'أيسل',
            'gender' => 'female',
            'parent_id' => $father->id,
            'from_outside_the_family' => false,
        ]);

        $outsideHusband = Person::factory()->create([
            'first_name' => 'يوسف',
            'last_name' => 'الدوسري',
            'gender' => 'male',
            'from_outside_the_family' => true,
            'parent_id' => null,
        ]);

        Marriage::create([
            'husband_id' => $outsideHusband->id,
            'wife_id' => $wife->id,
        ]);

        $result = $this->kinshipService->calculateKinship($wife->id, $outsideHusband->id);

        $this->assertEquals('زوج', $result->relationship['title']);
        $this->assertEquals('affinal_spouse', $result->relationship['type']);
        $this->assertTrue($result->relationship['is_external_spouse']);
        $this->assertCount(2, $result->branchA); // wife + father
        $this->assertCount(1, $result->branchB); // outside husband only
    }

    /** @test */
    public function it_calculates_in_law_kinship_between_father_in_law_and_outside_husband()
    {
        $father = Person::factory()->create([
            'first_name' => 'عبد الرحمن',
            'gender' => 'male',
            'from_outside_the_family' => false,
        ]);

        $daughter = Person::factory()->create([
            'first_name' => 'أيسل',
            'gender' => 'female',
            'parent_id' => $father->id,
            'from_outside_the_family' => false,
        ]);

        $outsideHusband = Person::factory()->create([
            'first_name' => 'يوسف',
            'last_name' => 'الدوسري',
            'gender' => 'male',
            'from_outside_the_family' => true,
            'parent_id' => null,
        ]);

        Marriage::create([
            'husband_id' => $outsideHusband->id,
            'wife_id' => $daughter->id,
        ]);

        // Father-in-law comparing with outside husband
        $result = $this->kinshipService->calculateKinship($father->id, $outsideHusband->id);

        $this->assertEquals('affinal_in_law', $result->relationship['type']);
        $this->assertStringContainsString('زوج ابنة', $result->relationship['title']);
        $this->assertTrue($result->relationship['is_external_spouse']);
        $this->assertEquals($daughter->id, $result->relationship['connecting_spouse']['id']);
    }

    /** @test */
    public function it_returns_unrelated_for_outside_person_with_no_marriage_connection()
    {
        $familyMember = Person::factory()->create([
            'first_name' => 'سعد',
            'gender' => 'male',
            'from_outside_the_family' => false,
        ]);

        $unrelatedOutsider = Person::factory()->create([
            'first_name' => 'طارق',
            'gender' => 'male',
            'from_outside_the_family' => true,
            'parent_id' => null,
        ]);

        $result = $this->kinshipService->calculateKinship($familyMember->id, $unrelatedOutsider->id);

        $this->assertEquals('unrelated', $result->relationship['type']);
        $this->assertNull($result->relationship['common_ancestor']);
        $this->assertEmpty($result->upperChain);
    }

    /** @test */
    public function it_prioritizes_paternal_cousin_kinship_over_marriage_for_family_cousins()
    {
        $grandfather = Person::factory()->create(['first_name' => 'صالح', 'gender' => 'male', 'from_outside_the_family' => false]);
        $fatherA = Person::factory()->create(['first_name' => 'علي', 'gender' => 'male', 'parent_id' => $grandfather->id, 'from_outside_the_family' => false]);
        $fatherB = Person::factory()->create(['first_name' => 'عمر', 'gender' => 'male', 'parent_id' => $grandfather->id, 'from_outside_the_family' => false]);

        $husband = Person::factory()->create(['first_name' => 'فيصل', 'gender' => 'male', 'parent_id' => $fatherA->id, 'from_outside_the_family' => false]);
        $wife = Person::factory()->create(['first_name' => 'نورة', 'gender' => 'female', 'parent_id' => $fatherB->id, 'from_outside_the_family' => false]);

        Marriage::create([
            'husband_id' => $husband->id,
            'wife_id' => $wife->id,
        ]);

        // When cousins within the family are married, their natural paternal kinship tree is preserved
        $result = $this->kinshipService->calculateKinship($husband->id, $wife->id);

        $this->assertStringContainsString('ابنة عم', $result->relationship['title']);
        $this->assertEquals($grandfather->id, $result->relationship['common_ancestor']['id']);
        $this->assertEquals(4, $result->relationship['total_degree']);
        $this->assertNull($result->branchA[0]['spouse'] ?? null);
    }

    /** @test */
    public function it_calculates_in_law_kinship_for_outside_spouse_when_family_member_is_from_disjoint_branch()
    {
        // Branch A root & daughter
        $branchRootA = Person::factory()->create(['first_name' => 'إبراهيم', 'gender' => 'male', 'parent_id' => null, 'from_outside_the_family' => false]);
        $familyWife = Person::factory()->create(['first_name' => 'هيلة', 'gender' => 'female', 'parent_id' => $branchRootA->id, 'from_outside_the_family' => false]);

        // Outside husband (like Sultan Al-Rwaished)
        $outsideHusband = Person::factory()->create([
            'first_name' => 'سلطان',
            'last_name' => 'الرويشد',
            'gender' => 'male',
            'from_outside_the_family' => true,
            'parent_id' => null,
        ]);

        Marriage::create([
            'husband_id' => $outsideHusband->id,
            'wife_id' => $familyWife->id,
        ]);

        // Branch B member from a different grandfather/branch of the family
        $branchRootB = Person::factory()->create(['first_name' => 'عبد الرحمن', 'gender' => 'male', 'parent_id' => null, 'from_outside_the_family' => false]);
        $personB = Person::factory()->create(['first_name' => 'خالد', 'gender' => 'male', 'parent_id' => $branchRootB->id, 'from_outside_the_family' => false]);

        // Comparing Outside Husband with a family member from a different branch
        $result = $this->kinshipService->calculateKinship($outsideHusband->id, $personB->id);

        $this->assertEquals('affinal_in_law', $result->relationship['type']);
        $this->assertStringContainsString('زوج فرد من العائلة', $result->relationship['title']);
        $this->assertTrue($result->relationship['is_external_spouse']);
        $this->assertTrue($result->relationship['common_ancestor']['is_virtual_family_root']);
        // The outside husband is attached as spouse to his wife at bottom of Branch A
        $this->assertNotNull($result->branchA[0]['spouse'] ?? null);
        $this->assertEquals($outsideHusband->id, $result->branchA[0]['spouse']['id']);
        $this->assertEquals('زوج', $result->branchA[0]['spouse']['role']);
        // Branch B contains the family member from the other branch
        $this->assertEquals($personB->id, $result->branchB[0]['id']);
    }

    /** @test */
    public function it_calculates_in_law_kinship_when_person_a_is_the_spouse()
    {
        $father = Person::factory()->create(['first_name' => 'عبد العزيز', 'gender' => 'male', 'from_outside_the_family' => false]);
        $daughter = Person::factory()->create(['first_name' => 'ريم', 'gender' => 'female', 'parent_id' => $father->id, 'from_outside_the_family' => false]);
        $husband = Person::factory()->create(['first_name' => 'ماجد', 'gender' => 'male', 'from_outside_the_family' => true]);

        Marriage::create([
            'husband_id' => $husband->id,
            'wife_id' => $daughter->id,
        ]);

        // Husband (Person A) comparing with Father-in-law (Person B)
        $result = $this->kinshipService->calculateKinship($husband->id, $father->id);

        $this->assertEquals('affinal_in_law', $result->relationship['type']);
        $this->assertStringContainsString('والد الزوجة', $result->relationship['title']);
        $this->assertEquals($daughter->id, $result->relationship['connecting_spouse']['id']);
        $this->assertNotNull($result->branchA[0]['spouse']);
        $this->assertEquals($husband->id, $result->branchA[0]['spouse']['id']);
    }

    /** @test */
    public function it_calculates_co_in_laws_relationship()
    {
        $father = Person::factory()->create(['first_name' => 'حمد', 'gender' => 'male', 'from_outside_the_family' => false]);
        $sister1 = Person::factory()->create(['first_name' => 'هند', 'gender' => 'female', 'parent_id' => $father->id, 'from_outside_the_family' => false]);
        $sister2 = Person::factory()->create(['first_name' => 'منى', 'gender' => 'female', 'parent_id' => $father->id, 'from_outside_the_family' => false]);

        $husband1 = Person::factory()->create(['first_name' => 'أنس', 'gender' => 'male', 'from_outside_the_family' => true]);
        $husband2 = Person::factory()->create(['first_name' => 'باسل', 'gender' => 'male', 'from_outside_the_family' => true]);

        Marriage::create(['husband_id' => $husband1->id, 'wife_id' => $sister1->id]);
        Marriage::create(['husband_id' => $husband2->id, 'wife_id' => $sister2->id]);

        $result = $this->kinshipService->calculateKinship($husband1->id, $husband2->id);

        $this->assertNotNull($result);
        $this->assertStringContainsString('عديل', $result->relationship['title']);
    }

    /** @test */
    public function search_persons_endpoint_includes_outside_family_spouses()
    {
        $outsideSpouse = Person::factory()->create([
            'first_name' => 'صهر_تجريبي',
            'last_name' => 'خارجي',
            'gender' => 'male',
            'from_outside_the_family' => true,
        ]);

        $response = $this->getJson('/api/persons/search?query=صهر_تجريبي');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $results = $response->json('data');
        $found = collect($results)->firstWhere('id', $outsideSpouse->id);
        $this->assertNotNull($found, 'Outside spouse was not found in search results');
        $this->assertTrue((bool) $found['from_outside_the_family']);
    }

    /** @test */
    public function it_calculates_kinship_via_mother_when_father_is_from_outside_the_family()
    {
        // 1. Grandfather in the family
        $grandfather = Person::factory()->create([
            'first_name' => 'محمد',
            'last_name' => 'السريّع',
            'gender' => 'male',
            'from_outside_the_family' => false,
        ]);

        // 2. Mother in the family (daughter of grandfather)
        $mother = Person::factory()->create([
            'first_name' => 'لولوة',
            'last_name' => 'السريّع',
            'gender' => 'female',
            'parent_id' => $grandfather->id,
            'from_outside_the_family' => false,
        ]);

        // 3. Father from OUTSIDE the family
        $outsideFather = Person::factory()->create([
            'first_name' => 'عبدالمحسن',
            'last_name' => 'الهديب',
            'gender' => 'male',
            'from_outside_the_family' => true,
        ]);

        // 4. Child whose father is outside and mother is inside
        $child = Person::factory()->create([
            'first_name' => 'بدر',
            'last_name' => 'الهديب',
            'gender' => 'male',
            'parent_id' => $outsideFather->id,
            'mother_id' => $mother->id,
            'from_outside_the_family' => true,
        ]);

        // Calculate kinship between Grandfather and Child
        $result = $this->kinshipService->calculateKinship($grandfather->id, $child->id);

        $this->assertNotNull($result);
        $this->assertEquals('direct', $result->relationship['type']);
        $this->assertEquals('حفيد', $result->relationship['title']);

        // Verify Branch B goes: Child -> Mother -> Grandfather
        $branchBIds = array_map(fn($n) => $n['id'], $result->branchB);
        $this->assertEquals([$child->id, $mother->id, $grandfather->id], $branchBIds);

        // Verify outside father is NOT in the vertical lineage branch
        $this->assertNotContains($outsideFather->id, $branchBIds);
    }
}
