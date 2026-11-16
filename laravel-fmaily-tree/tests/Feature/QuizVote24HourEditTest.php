<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\QuizCompetitionApiController;
use App\Models\Person;
use App\Models\QuizAnswer;
use App\Models\QuizCompetition;
use App\Models\QuizQuestion;
use App\Models\QuizQuestionChoice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class QuizVote24HourEditTest extends TestCase
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
        config(['site.password_protection_enabled' => false]);
    }

    /**
     * Test a user can vote, edit vote 8h later, and edit again up to 24h from initial vote,
     * but is strictly rejected after 24h from the first vote.
     */
    public function test_user_can_edit_vote_within_24_hours_of_first_vote_and_locks_strictly_afterwards()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 13:00:00')); // 1:00 PM on Day 1

        $comp = QuizCompetition::create([
            'title' => 'انتخابات مجلس العائلة',
            'is_active' => true,
            'start_at' => now()->subDay(),
            'end_at' => now()->addDays(5),
        ]);

        $question = QuizQuestion::create([
            'quiz_competition_id' => $comp->id,
            'question_text' => 'صوّت لمرشحك المفضل',
            'answer_type' => 'vote',
            'vote_max_selections' => 1,
            'candidate_rules' => [
                'nomination_type' => 'predefined',
            ],
            'voter_eligibility' => [
                'require_identity' => false,
            ],
        ]);

        $choiceA = QuizQuestionChoice::create([
            'quiz_question_id' => $question->id,
            'choice_text' => 'المرشح أ',
            'is_correct' => false,
        ]);

        $choiceB = QuizQuestionChoice::create([
            'quiz_question_id' => $question->id,
            'choice_text' => 'المرشح ب',
            'is_correct' => false,
        ]);

        $choiceC = QuizQuestionChoice::create([
            'quiz_question_id' => $question->id,
            'choice_text' => 'المرشح ج',
            'is_correct' => false,
        ]);

        $user = User::factory()->create([
            'phone' => '0599998888',
            'name' => 'صالح السريع',
        ]);

        $controller = app(QuizCompetitionApiController::class);

        // Step 1: Initial vote at 1:00 PM for Choice A
        $req1 = Request::create(
            "/api/v1/quiz-competitions/{$comp->id}/questions/{$question->id}/answer",
            'POST',
            [
                'name' => 'صالح السريع',
                'phone' => '0599998888',
                'answer' => $choiceA->id,
            ]
        );
        $req1->setUserResolver(fn() => $user);

        $resp1 = $controller->storeAnswer($req1, $comp, $question);
        $this->assertEquals(200, $resp1->getStatusCode());
        $data1 = json_decode($resp1->getContent(), true);
        $this->assertEquals('success', $data1['status']);
        $this->assertFalse($data1['data']['is_updated'] ?? false);
        $this->assertTrue($data1['data']['user_vote']['can_edit']);

        $answerRecord = QuizAnswer::where('quiz_question_id', $question->id)->where('user_id', $user->id)->first();
        $this->assertNotNull($answerRecord);
        $initialCreatedAt = $answerRecord->created_at->toDateTimeString();
        $this->assertEquals('2026-10-20 13:00:00', $initialCreatedAt);
        $this->assertEquals([$choiceA->id], $answerRecord->selectedChoices->pluck('quiz_question_choice_id')->toArray());

        // Step 2: 8 Hours Later (9:00 PM Day 1) -> User edits vote to Choice B
        Carbon::setTestNow(Carbon::parse('2026-10-20 21:00:00'));

        $req2 = Request::create(
            "/api/v1/quiz-competitions/{$comp->id}/questions/{$question->id}/answer",
            'POST',
            [
                'name' => 'صالح السريع',
                'phone' => '0599998888',
                'answer' => $choiceB->id,
            ]
        );
        $req2->setUserResolver(fn() => $user);

        $resp2 = $controller->storeAnswer($req2, $comp, $question);
        $this->assertEquals(200, $resp2->getStatusCode());
        $data2 = json_decode($resp2->getContent(), true);
        $this->assertEquals('success', $data2['status']);
        $this->assertTrue($data2['data']['is_updated']);
        $this->assertTrue($data2['data']['user_vote']['can_edit']);

        // Verify the database record: created_at MUST REMAIN 1:00 PM (first vote), NOT 9:00 PM!
        $answerRecord->refresh();
        $this->assertEquals('2026-10-20 13:00:00', $answerRecord->created_at->toDateTimeString());
        $this->assertEquals('2026-10-20 21:00:00', $answerRecord->updated_at->toDateTimeString());
        $this->assertEquals([$choiceB->id], $answerRecord->selectedChoices->pluck('quiz_question_choice_id')->toArray());

        // Step 3: 23 Hours after initial vote (12:00 PM Day 2) -> User edits vote to Choice C
        Carbon::setTestNow(Carbon::parse('2026-10-21 12:00:00'));

        $req3 = Request::create(
            "/api/v1/quiz-competitions/{$comp->id}/questions/{$question->id}/answer",
            'POST',
            [
                'name' => 'صالح السريع',
                'phone' => '0599998888',
                'answer' => $choiceC->id,
            ]
        );
        $req3->setUserResolver(fn() => $user);

        $resp3 = $controller->storeAnswer($req3, $comp, $question);
        $this->assertEquals(200, $resp3->getStatusCode());
        $data3 = json_decode($resp3->getContent(), true);
        $this->assertEquals('success', $data3['status']);
        $this->assertTrue($data3['data']['is_updated']);

        $answerRecord->refresh();
        $this->assertEquals('2026-10-20 13:00:00', $answerRecord->created_at->toDateTimeString());
        $this->assertEquals([$choiceC->id], $answerRecord->selectedChoices->pluck('quiz_question_choice_id')->toArray());

        // Step 4: 25 Hours after initial vote (2:00 PM Day 2) -> Edit should be STRICTLY LOCKED
        // Note: Even though the last edit was at 12:00 PM (only 2 hours ago), editing must lock at 1:00 PM (24h from first vote)
        Carbon::setTestNow(Carbon::parse('2026-10-21 14:00:00'));

        $req4 = Request::create(
            "/api/v1/quiz-competitions/{$comp->id}/questions/{$question->id}/answer",
            'POST',
            [
                'name' => 'صالح السريع',
                'phone' => '0599998888',
                'answer' => $choiceA->id,
            ]
        );
        $req4->setUserResolver(fn() => $user);

        $resp4 = $controller->storeAnswer($req4, $comp, $question);
        $this->assertEquals(422, $resp4->getStatusCode());
        $data4 = json_decode($resp4->getContent(), true);
        $this->assertEquals('error', $data4['status']);
        $this->assertStringContainsString('انتهت المهلة المحددة لتعديل التصويت (24 ساعة من تاريخ التصويت الأول)', $data4['message']);

        // Verify choice C is still the preserved final vote
        $answerRecord->refresh();
        $this->assertEquals([$choiceC->id], $answerRecord->selectedChoices->pluck('quiz_question_choice_id')->toArray());

        Carbon::setTestNow(); // Reset time mock
    }

    /**
     * Test a user with custom 6-hour edit window can edit at 4h, but is locked at 7h.
     */
    public function test_user_can_edit_vote_within_custom_hours_and_locks_strictly_afterwards()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00'));

        $comp = QuizCompetition::create([
            'title' => 'استطلاع سريع',
            'is_active' => true,
            'start_at' => now()->subDay(),
            'end_at' => now()->addDays(5),
        ]);

        $question = QuizQuestion::create([
            'quiz_competition_id' => $comp->id,
            'question_text' => 'اختيار مرشح المجلس',
            'answer_type' => 'vote',
            'vote_max_selections' => 1,
            'vote_edit_duration_hours' => 6,
            'candidate_rules' => ['nomination_type' => 'predefined'],
            'voter_eligibility' => ['require_identity' => false],
        ]);

        $choiceA = QuizQuestionChoice::create([
            'quiz_question_id' => $question->id,
            'choice_text' => 'المرشح 1',
            'is_correct' => false,
        ]);
        $choiceB = QuizQuestionChoice::create([
            'quiz_question_id' => $question->id,
            'choice_text' => 'المرشح 2',
            'is_correct' => false,
        ]);

        $user = User::factory()->create([
            'phone' => '0511112222',
            'name' => 'فهد السريع',
        ]);

        $controller = app(QuizCompetitionApiController::class);

        // Vote at 10:00 AM
        $req1 = Request::create("/api/v1/quiz-competitions/{$comp->id}/questions/{$question->id}/answer", 'POST', [
            'name' => 'فهد السريع',
            'phone' => '0511112222',
            'answer' => $choiceA->id,
        ]);
        $req1->setUserResolver(fn() => $user);
        $resp1 = $controller->storeAnswer($req1, $comp, $question);
        $this->assertEquals(200, $resp1->getStatusCode());
        $data1 = json_decode($resp1->getContent(), true);
        $this->assertTrue($data1['data']['user_vote']['can_edit']);

        // Edit 4 hours later (2:00 PM) -> Allowed (< 6h)
        Carbon::setTestNow(Carbon::parse('2026-10-20 14:00:00'));
        $req2 = Request::create("/api/v1/quiz-competitions/{$comp->id}/questions/{$question->id}/answer", 'POST', [
            'name' => 'فهد السريع',
            'phone' => '0511112222',
            'answer' => $choiceB->id,
        ]);
        $req2->setUserResolver(fn() => $user);
        $resp2 = $controller->storeAnswer($req2, $comp, $question);
        $this->assertEquals(200, $resp2->getStatusCode());
        $data2 = json_decode($resp2->getContent(), true);
        $this->assertTrue($data2['data']['is_updated']);

        // Try edit 7 hours from initial vote (5:00 PM) -> Locked (> 6h)
        Carbon::setTestNow(Carbon::parse('2026-10-20 17:00:00'));
        $req3 = Request::create("/api/v1/quiz-competitions/{$comp->id}/questions/{$question->id}/answer", 'POST', [
            'name' => 'فهد السريع',
            'phone' => '0511112222',
            'answer' => $choiceA->id,
        ]);
        $req3->setUserResolver(fn() => $user);
        $resp3 = $controller->storeAnswer($req3, $comp, $question);
        $this->assertEquals(422, $resp3->getStatusCode());
        $data3 = json_decode($resp3->getContent(), true);
        $this->assertEquals('error', $data3['status']);
        $this->assertStringContainsString('6 ساعة من تاريخ التصويت الأول', $data3['message']);

        Carbon::setTestNow();
    }

    /**
     * Test unlimited vote editing (-1) allows edits anytime while competition is active.
     */
    public function test_user_can_edit_vote_indefinitely_when_unlimited_mode()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00'));

        $comp = QuizCompetition::create([
            'title' => 'تصويت مفتوح',
            'is_active' => true,
            'start_at' => now()->subDay(),
            'end_at' => now()->addDays(10),
        ]);

        $question = QuizQuestion::create([
            'quiz_competition_id' => $comp->id,
            'question_text' => 'تصويت مرن بدون مهلة',
            'answer_type' => 'vote',
            'vote_max_selections' => 1,
            'vote_edit_duration_hours' => -1, // Unlimited
            'candidate_rules' => ['nomination_type' => 'predefined'],
            'voter_eligibility' => ['require_identity' => false],
        ]);

        $choiceA = QuizQuestionChoice::create([
            'quiz_question_id' => $question->id,
            'choice_text' => 'الخيار 1',
            'is_correct' => false,
        ]);
        $choiceB = QuizQuestionChoice::create([
            'quiz_question_id' => $question->id,
            'choice_text' => 'الخيار 2',
            'is_correct' => false,
        ]);

        $user = User::factory()->create([
            'phone' => '0533334444',
            'name' => 'عبدالله السريع',
        ]);

        $controller = app(QuizCompetitionApiController::class);

        // Day 1: Vote
        $req1 = Request::create("/api/v1/quiz-competitions/{$comp->id}/questions/{$question->id}/answer", 'POST', [
            'name' => 'عبدالله السريع',
            'phone' => '0533334444',
            'answer' => $choiceA->id,
        ]);
        $req1->setUserResolver(fn() => $user);
        $resp1 = $controller->storeAnswer($req1, $comp, $question);
        $this->assertEquals(200, $resp1->getStatusCode());
        $data1 = json_decode($resp1->getContent(), true);
        $this->assertTrue($data1['data']['user_vote']['can_edit']);
        $this->assertNull($data1['data']['user_vote']['edit_until']);

        // 3 Days later (72h later) -> Still allowed because it is unlimited!
        Carbon::setTestNow(Carbon::parse('2026-10-23 15:00:00'));
        $req2 = Request::create("/api/v1/quiz-competitions/{$comp->id}/questions/{$question->id}/answer", 'POST', [
            'name' => 'عبدالله السريع',
            'phone' => '0533334444',
            'answer' => $choiceB->id,
        ]);
        $req2->setUserResolver(fn() => $user);
        $resp2 = $controller->storeAnswer($req2, $comp, $question);
        $this->assertEquals(200, $resp2->getStatusCode());
        $data2 = json_decode($resp2->getContent(), true);
        $this->assertTrue($data2['data']['is_updated']);
        $this->assertTrue($data2['data']['user_vote']['can_edit']);
        $this->assertNull($data2['data']['user_vote']['edit_until']);

        Carbon::setTestNow();
    }

    /**
     * Test forbidden vote editing (0) prevents any edits immediately after initial vote.
     */
    public function test_user_cannot_edit_vote_when_editing_is_forbidden()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00'));

        $comp = QuizCompetition::create([
            'title' => 'تصويت صارم',
            'is_active' => true,
            'start_at' => now()->subDay(),
            'end_at' => now()->addDays(5),
        ]);

        $question = QuizQuestion::create([
            'quiz_competition_id' => $comp->id,
            'question_text' => 'تصويت نهائي غير قابل للتعديل',
            'answer_type' => 'vote',
            'vote_max_selections' => 1,
            'vote_edit_duration_hours' => 0, // Forbidden
            'candidate_rules' => ['nomination_type' => 'predefined'],
            'voter_eligibility' => ['require_identity' => false],
        ]);

        $choiceA = QuizQuestionChoice::create([
            'quiz_question_id' => $question->id,
            'choice_text' => 'الخيار 1',
            'is_correct' => false,
        ]);
        $choiceB = QuizQuestionChoice::create([
            'quiz_question_id' => $question->id,
            'choice_text' => 'الخيار 2',
            'is_correct' => false,
        ]);

        $user = User::factory()->create([
            'phone' => '0555556666',
            'name' => 'خالد السريع',
        ]);

        $controller = app(QuizCompetitionApiController::class);

        // Vote at 10:00 AM
        $req1 = Request::create("/api/v1/quiz-competitions/{$comp->id}/questions/{$question->id}/answer", 'POST', [
            'name' => 'خالد السريع',
            'phone' => '0555556666',
            'answer' => $choiceA->id,
        ]);
        $req1->setUserResolver(fn() => $user);
        $resp1 = $controller->storeAnswer($req1, $comp, $question);
        $this->assertEquals(200, $resp1->getStatusCode());
        $data1 = json_decode($resp1->getContent(), true);
        $this->assertFalse($data1['data']['user_vote']['can_edit']);

        // Try edit immediately 1 minute later -> Strictly rejected!
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:01:00'));
        $req2 = Request::create("/api/v1/quiz-competitions/{$comp->id}/questions/{$question->id}/answer", 'POST', [
            'name' => 'خالد السريع',
            'phone' => '0555556666',
            'answer' => $choiceB->id,
        ]);
        $req2->setUserResolver(fn() => $user);
        $resp2 = $controller->storeAnswer($req2, $comp, $question);
        $this->assertEquals(422, $resp2->getStatusCode());
        $data2 = json_decode($resp2->getContent(), true);
        $this->assertEquals('error', $data2['status']);
        $this->assertStringContainsString('غير مسموح بتعديل التصويت بعد إرساله', $data2['message']);

        Carbon::setTestNow();
    }
}
