<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\QuizCompetitionApiController;
use App\Http\Controllers\QuizCompetitionPublicController;
use App\Models\Person;
use App\Models\QuizCompetition;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class QuizCompetitionCandidateSearchTest extends TestCase
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

    public function test_candidate_tree_nodes_searches_by_lineage_and_variants()
    {
        $comp = QuizCompetition::create([
            'title' => 'مسابقة تصويت تجريبية',
            'is_active' => true,
        ]);

        $grandpa = Person::factory()->create([
            'first_name' => 'ابراهيم',
            'last_name' => 'السريع',
            'gender' => 'male',
            'death_date' => null,
            'from_outside_the_family' => false,
        ]);

        $father = Person::factory()->create([
            'first_name' => 'حمد',
            'last_name' => 'السريع',
            'gender' => 'male',
            'parent_id' => $grandpa->id,
            'death_date' => null,
            'from_outside_the_family' => false,
        ]);

        $target = Person::factory()->create([
            'first_name' => 'محمد',
            'last_name' => 'السريع',
            'gender' => 'male',
            'parent_id' => $father->id,
            'death_date' => null,
            'from_outside_the_family' => false,
        ]);

        $question = QuizQuestion::create([
            'quiz_competition_id' => $comp->id,
            'question_text' => 'تصويت أفضل شخصية',
            'answer_type' => 'vote',
            'vote_max_selections' => 1,
            'candidate_rules' => [
                'nomination_type' => 'open_tree',
                'allowed_gender' => 'male',
                'exclude_deceased' => true,
            ],
        ]);

        $apiCtrl = app(QuizCompetitionApiController::class);

        // 1. Search with hamza variant
        $req1 = Request::create('/api/v1/quiz-competitions/candidates/tree-nodes', 'GET', [
            'question_id' => $question->id,
            'q' => 'محمد حمد الإبراهيم',
            'gender' => 'male',
            'exclude_deceased' => '1',
        ]);
        $resp1 = $apiCtrl->candidateTreeNodes($req1);
        $data1 = json_decode($resp1->getContent(), true);
        $this->assertEquals('success', $data1['status']);
        $this->assertNotEmpty($data1['data']['nodes']);
        $this->assertEquals($target->id, $data1['data']['nodes'][0]['id']);
        $this->assertTrue($data1['data']['nodes'][0]['selectable']);

        // 2. Search without hamza variant
        $req2 = Request::create('/api/v1/quiz-competitions/candidates/tree-nodes', 'GET', [
            'question_id' => $question->id,
            'q' => 'محمد حمد ابراهيم',
            'gender' => 'male',
            'exclude_deceased' => '1',
        ]);
        $resp2 = $apiCtrl->candidateTreeNodes($req2);
        $data2 = json_decode($resp2->getContent(), true);
        $this->assertEquals($target->id, $data2['data']['nodes'][0]['id']);

        // 3. Search with dropped-alef branch variant
        $req3 = Request::create('/api/v1/quiz-competitions/candidates/tree-nodes', 'GET', [
            'question_id' => $question->id,
            'q' => 'محمد حمد البراهيم',
            'gender' => 'male',
            'exclude_deceased' => '1',
        ]);
        $resp3 = $apiCtrl->candidateTreeNodes($req3);
        $data3 = json_decode($resp3->getContent(), true);
        $this->assertEquals($target->id, $data3['data']['nodes'][0]['id']);
    }

    public function test_candidate_tree_nodes_strictly_enforces_male_gender_rule_overriding_query_param()
    {
        $comp = QuizCompetition::create([
            'title' => 'مسابقة تصويت مخصصة للرجال',
            'is_active' => true,
        ]);

        $malePerson = Person::factory()->create([
            'first_name' => 'خالد',
            'last_name' => 'السريع',
            'gender' => 'male',
            'death_date' => null,
            'from_outside_the_family' => false,
        ]);

        $femalePerson = Person::factory()->create([
            'first_name' => 'سارة',
            'last_name' => 'السريع',
            'gender' => 'female',
            'death_date' => null,
            'from_outside_the_family' => false,
        ]);

        $question = QuizQuestion::create([
            'quiz_competition_id' => $comp->id,
            'question_text' => 'تصويت رجال العائلة',
            'answer_type' => 'vote',
            'vote_max_selections' => 1,
            'candidate_rules' => [
                'nomination_type' => 'open_tree',
                'allowed_gender' => 'male',
                'exclude_deceased' => true,
            ],
        ]);

        $apiCtrl = app(QuizCompetitionApiController::class);

        // Even if client passes 'gender' => 'all', question allowed_gender=male MUST strictly apply
        $req = Request::create('/api/v1/quiz-competitions/candidates/tree-nodes', 'GET', [
            'question_id' => $question->id,
            'gender' => 'all',
        ]);
        $resp = $apiCtrl->candidateTreeNodes($req);
        $data = json_decode($resp->getContent(), true);

        $this->assertEquals('success', $data['status']);
        $nodes = collect($data['data']['nodes']);

        $maleNode = $nodes->firstWhere('id', $malePerson->id);
        $femaleNode = $nodes->firstWhere('id', $femalePerson->id);

        $this->assertNotNull($maleNode);
        $this->assertTrue($maleNode['selectable']);
        $this->assertNull($maleNode['unselectable_reason']);

        $this->assertNotNull($femaleNode);
        $this->assertFalse($femaleNode['selectable']);
        $this->assertEquals('غير متاح (رجال فقط)', $femaleNode['unselectable_reason']);
    }
}