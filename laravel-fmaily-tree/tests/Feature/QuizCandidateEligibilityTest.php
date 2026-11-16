<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\QuizCompetitionApiController;
use App\Models\Breastfeeding;
use App\Models\Person;
use App\Models\QuizCompetition;
use App\Models\QuizQuestion;
use App\Services\Quiz\CandidateEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class QuizCandidateEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Queue::fake();
        $this->mock(\App\Services\AI\EmbeddingsService::class, function ($mock) {
            $mock->shouldReceive('createForPerson')->andReturn(null);
            $mock->shouldReceive('deleteForPerson')->andReturn(null);
        });
    }

    public function test_candidate_eligibility_service_resolves_all_categories()
    {
        $service = app(CandidateEligibilityService::class);

        // 1. Original family member
        $familyMember = Person::factory()->create([
            'first_name' => 'سعد',
            'last_name' => 'السريع',
            'gender' => 'male',
            'from_outside_the_family' => false,
        ]);

        // Family mother
        $familyMother = Person::factory()->create([
            'first_name' => 'نورة',
            'last_name' => 'السريع',
            'gender' => 'female',
            'from_outside_the_family' => false,
        ]);

        // 2. Maternal child (Ansab): Mother from family, child marked outside
        $maternalChild = Person::factory()->create([
            'first_name' => 'عبدالوهاب',
            'last_name' => 'الصانع',
            'gender' => 'male',
            'mother_id' => $familyMother->id,
            'from_outside_the_family' => true,
        ]);

        // 3. Breastfed child: neither parent is family, but nursed by family mother
        $breastfedChild = Person::factory()->create([
            'first_name' => 'يوسف',
            'last_name' => 'الغامدي',
            'gender' => 'male',
            'from_outside_the_family' => true,
        ]);
        Breastfeeding::create([
            'nursing_mother_id' => $familyMother->id,
            'breastfed_child_id' => $breastfedChild->id,
            'is_active' => true,
        ]);

        // 4. Outside family member (Spouse / In-law / Outsider)
        $outsider = Person::factory()->create([
            'first_name' => 'خالد',
            'last_name' => 'المطيري',
            'gender' => 'male',
            'from_outside_the_family' => true,
        ]);

        $service->clearCache();

        $this->assertEquals(CandidateEligibilityService::CATEGORY_ORIGINAL_FAMILY, $service->resolveCategory($familyMember));
        $this->assertEquals(CandidateEligibilityService::CATEGORY_MATERNAL_CHILD, $service->resolveCategory($maternalChild));
        $this->assertEquals(CandidateEligibilityService::CATEGORY_BREASTFED, $service->resolveCategory($breastfedChild));
        $this->assertEquals(CandidateEligibilityService::CATEGORY_OUTSIDE_FAMILY, $service->resolveCategory($outsider));
    }

    public function test_candidate_search_shows_maternal_child_and_applies_exclusions()
    {
        $comp = QuizCompetition::create([
            'title' => 'مسابقة مرشحي العائلة والأنساب',
            'is_active' => true,
        ]);

        $familyMother = Person::factory()->create([
            'first_name' => 'حصة',
            'last_name' => 'السريع',
            'gender' => 'female',
            'from_outside_the_family' => false,
        ]);

        $maternalChild = Person::factory()->create([
            'first_name' => 'بدر',
            'last_name' => 'الهديب',
            'gender' => 'male',
            'mother_id' => $familyMother->id,
            'from_outside_the_family' => true,
            'death_date' => null,
        ]);

        $outsider = Person::factory()->create([
            'first_name' => 'بدر',
            'last_name' => 'الشمري',
            'gender' => 'male',
            'from_outside_the_family' => true,
            'death_date' => null,
        ]);

        $question = QuizQuestion::create([
            'quiz_competition_id' => $comp->id,
            'question_text' => 'من هو الشخص المفضل؟',
            'answer_type' => 'vote',
            'vote_max_selections' => 1,
            'candidate_rules' => [
                'nomination_type' => 'open_tree',
                'allowed_gender' => 'all',
                'exclude_deceased' => true,
                'exclude_outside_family' => true,
                'exclude_maternal_children' => false,
                'exclude_breastfed' => true,
                'exclude_original_family' => false,
            ],
        ]);

        $apiCtrl = app(QuizCompetitionApiController::class);

        // Case A: Maternal child is allowed and should be selectable
        $req = Request::create('/api/v1/quiz-competitions/candidates/tree-nodes', 'GET', [
            'question_id' => $question->id,
            'q' => 'بدر',
        ]);
        $resp = $apiCtrl->candidateTreeNodes($req);
        $data = json_decode($resp->getContent(), true);

        $this->assertEquals('success', $data['status']);
        $nodes = collect($data['data']['nodes']);

        $maternalNode = $nodes->firstWhere('id', $maternalChild->id);
        $this->assertNotNull($maternalNode, 'Maternal child should appear in search results');
        $this->assertTrue($maternalNode['selectable'], 'Maternal child should be selectable when not excluded');
        $this->assertEquals('maternal_children', $maternalNode['candidate_category']);

        $outsiderNode = $nodes->firstWhere('id', $outsider->id);
        $this->assertNotNull($outsiderNode, 'Outsider also appears in search');
        $this->assertFalse($outsiderNode['selectable'], 'Outsider should NOT be selectable when exclude_outside_family is true');
        $this->assertStringContainsString('خارج العائلة', $outsiderNode['unselectable_reason']);

        // Case B: Exclude maternal children as well
        $question->candidate_rules = array_merge($question->candidate_rules, [
            'exclude_maternal_children' => true,
        ]);
        $question->save();

        $resp2 = $apiCtrl->candidateTreeNodes($req);
        $data2 = json_decode($resp2->getContent(), true);
        $nodes2 = collect($data2['data']['nodes']);

        $maternalNode2 = $nodes2->firstWhere('id', $maternalChild->id);
        $this->assertFalse($maternalNode2['selectable'], 'Maternal child should NOT be selectable when exclude_maternal_children is true');
        $this->assertStringContainsString('الأنساب', $maternalNode2['unselectable_reason']);
    }

    public function test_target_scope_maternal_only_excludes_original_family()
    {
        $comp = QuizCompetition::create([
            'title' => 'مسابقة مخصصة للأنساب فقط',
            'is_active' => true,
        ]);

        $familyMember = Person::factory()->create([
            'first_name' => 'فهد',
            'last_name' => 'السريع',
            'gender' => 'male',
            'from_outside_the_family' => false,
        ]);

        $familyMother = Person::factory()->create([
            'first_name' => 'هيلة',
            'last_name' => 'السريع',
            'gender' => 'female',
            'from_outside_the_family' => false,
        ]);

        $maternalChild = Person::factory()->create([
            'first_name' => 'فهد',
            'last_name' => 'المنصور',
            'gender' => 'male',
            'mother_id' => $familyMother->id,
            'from_outside_the_family' => true,
        ]);

        $question = QuizQuestion::create([
            'quiz_competition_id' => $comp->id,
            'question_text' => 'أفضل شخصية من الأنساب',
            'answer_type' => 'vote',
            'vote_max_selections' => 1,
            'candidate_rules' => [
                'nomination_type' => 'open_tree',
                'candidate_scope' => 'maternal_only',
                'exclude_original_family' => true,
                'exclude_maternal_children' => false,
                'exclude_breastfed' => true,
                'exclude_outside_family' => true,
            ],
        ]);

        $apiCtrl = app(QuizCompetitionApiController::class);
        $req = Request::create('/api/v1/quiz-competitions/candidates/tree-nodes', 'GET', [
            'question_id' => $question->id,
            'q' => 'فهد',
        ]);
        $resp = $apiCtrl->candidateTreeNodes($req);
        $data = json_decode($resp->getContent(), true);

        $nodes = collect($data['data']['nodes']);

        $maternalNode = $nodes->firstWhere('id', $maternalChild->id);
        $this->assertTrue($maternalNode['selectable']);

        $familyNode = $nodes->firstWhere('id', $familyMember->id);
        $this->assertFalse($familyNode['selectable'], 'Original family should be unselectable when exclude_original_family is true');
        $this->assertStringContainsString('سلالة العائلة الأساسية', $familyNode['unselectable_reason']);

        // Check model checkCandidateEligibility directly
        $checkFamily = $question->checkCandidateEligibility($familyMember);
        $this->assertFalse($checkFamily['eligible']);

        $checkMaternal = $question->checkCandidateEligibility($maternalChild);
        $this->assertTrue($checkMaternal['eligible']);
    }
}
