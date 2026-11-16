<?php

namespace Tests\Unit;

use App\Models\Person;
use App\Models\QuizCompetition;
use App\Models\QuizQuestion;
use Tests\TestCase;

class QuizGenderVisibilityTest extends TestCase
{
    public function test_walking_challenge_female_only_is_visible_only_to_females(): void
    {
        $question = new QuizQuestion([
            'answer_type' => 'walking',
            'walking_settings' => [
                'target_gender' => 'female',
            ],
        ]);

        $femalePerson = new Person(['gender' => 'female']);
        $malePerson = new Person(['gender' => 'male']);

        $this->assertTrue($question->isVisibleTo($femalePerson));
        $this->assertFalse($question->isVisibleTo($malePerson));
        $this->assertFalse($question->isVisibleTo(null)); // Guest / Unauthenticated
    }

    public function test_walking_challenge_male_only_is_visible_to_everyone_as_view_only_for_non_males(): void
    {
        $question = new QuizQuestion([
            'answer_type' => 'walking',
            'walking_settings' => [
                'target_gender' => 'male',
            ],
        ]);

        $femalePerson = new Person(['gender' => 'female']);
        $malePerson = new Person(['gender' => 'male']);

        // مسابقات الرجال ظاهرة للجميع: كاملة للرجال، وعرض فقط للنساء والزوار
        $this->assertTrue($question->isVisibleTo($malePerson));
        $this->assertTrue($question->isVisibleTo($femalePerson));
        $this->assertTrue($question->isVisibleTo(null));

        // العرض فقط للنساء والزوار، والرجال مشاركة كاملة
        $this->assertFalse($question->isViewOnlyFor($malePerson));
        $this->assertTrue($question->isViewOnlyFor($femalePerson));
        $this->assertTrue($question->isViewOnlyFor(null));
    }

    public function test_walking_challenge_both_segregated_is_visible_to_all(): void
    {
        $question = new QuizQuestion([
            'answer_type' => 'walking',
            'walking_settings' => [
                'target_gender' => 'both_segregated',
            ],
        ]);

        $femalePerson = new Person(['gender' => 'female']);
        $malePerson = new Person(['gender' => 'male']);

        $this->assertTrue($question->isVisibleTo($malePerson));
        $this->assertTrue($question->isVisibleTo($femalePerson));
        $this->assertTrue($question->isVisibleTo(null));
    }

    public function test_vote_with_female_voter_eligibility_is_visible_only_to_females(): void
    {
        $question = new QuizQuestion([
            'answer_type' => 'vote',
            'voter_eligibility' => [
                'allowed_gender' => 'female',
            ],
        ]);

        $femalePerson = new Person(['gender' => 'female']);
        $malePerson = new Person(['gender' => 'male']);

        $this->assertTrue($question->isVisibleTo($femalePerson));
        $this->assertFalse($question->isVisibleTo($malePerson));
        $this->assertFalse($question->isVisibleTo(null));
    }

    public function test_vote_with_male_voter_eligibility_is_visible_only_to_males(): void
    {
        $question = new QuizQuestion([
            'answer_type' => 'vote',
            'voter_eligibility' => [
                'allowed_gender' => 'male',
            ],
        ]);

        $femalePerson = new Person(['gender' => 'female']);
        $malePerson = new Person(['gender' => 'male']);

        $this->assertTrue($question->isVisibleTo($malePerson));
        $this->assertFalse($question->isVisibleTo($femalePerson));
        $this->assertFalse($question->isVisibleTo(null));
    }

    public function test_vote_for_female_candidates_with_all_voters_is_visible_to_everyone(): void
    {
        $question = new QuizQuestion([
            'answer_type' => 'vote',
            'candidate_rules' => [
                'allowed_gender' => 'female',
            ],
            'voter_eligibility' => [
                'allowed_gender' => 'all',
            ],
        ]);

        $femalePerson = new Person(['gender' => 'female']);
        $malePerson = new Person(['gender' => 'male']);

        $this->assertTrue($question->isVisibleTo($femalePerson));
        $this->assertTrue($question->isVisibleTo($malePerson));
        $this->assertTrue($question->isVisibleTo(null));
    }

    public function test_can_view_walking_leaderboard_protects_female_steps(): void
    {
        $femaleWalking = new QuizQuestion([
            'answer_type' => 'walking',
            'walking_settings' => [
                'target_gender' => 'female',
            ],
        ]);

        $femalePerson = new Person(['gender' => 'female']);
        $malePerson = new Person(['gender' => 'male']);

        $this->assertTrue($femaleWalking->canViewWalkingLeaderboard($femalePerson));
        $this->assertFalse($femaleWalking->canViewWalkingLeaderboard($malePerson));
        $this->assertFalse($femaleWalking->canViewWalkingLeaderboard(null));
    }

    public function test_can_view_walking_leaderboard_male_is_visible_to_all(): void
    {
        $maleWalking = new QuizQuestion([
            'answer_type' => 'walking',
            'walking_settings' => [
                'target_gender' => 'male',
            ],
        ]);

        $femalePerson = new Person(['gender' => 'female']);
        $malePerson = new Person(['gender' => 'male']);

        // صدارة الرجال ظاهرة للجميع (عرض فقط لغير الرجال)
        $this->assertTrue($maleWalking->canViewWalkingLeaderboard($malePerson));
        $this->assertTrue($maleWalking->canViewWalkingLeaderboard($femalePerson));
        $this->assertTrue($maleWalking->canViewWalkingLeaderboard(null));
    }

    public function test_male_chat_is_hidden_from_females_and_guests(): void
    {
        $maleWalking = new QuizQuestion([
            'answer_type' => 'walking',
            'walking_settings' => [
                'target_gender' => 'male',
            ],
            'has_chat' => true,
        ]);

        $femalePerson = new Person(['gender' => 'female']);
        $malePerson = new Person(['gender' => 'male']);

        $this->assertTrue($maleWalking->canViewChatFor($malePerson));
        $this->assertFalse($maleWalking->canViewChatFor($femalePerson));
        $this->assertFalse($maleWalking->canViewChatFor(null));
    }

    public function test_linked_walking_vote_allows_male_to_vote_while_protecting_female_walking_standings(): void
    {
        $femaleWalking = new QuizQuestion([
            'answer_type' => 'walking',
            'walking_settings' => [
                'target_gender' => 'female',
            ],
        ]);

        $voteQuestion = new QuizQuestion([
            'answer_type' => 'vote',
            'candidate_rules' => [
                'allowed_gender' => 'female',
            ],
            'voter_eligibility' => [
                'allowed_gender' => 'all',
            ],
            'linked_walking_question_id' => 999,
        ]);
        $voteQuestion->setRelation('linkedWalkingQuestion', $femaleWalking);

        $femalePerson = new Person(['gender' => 'female']);
        $malePerson = new Person(['gender' => 'male']);

        // كلا الجنسين يرى كرت التصويت ويحق له التصويت
        $this->assertTrue($voteQuestion->isVisibleTo($femalePerson));
        $this->assertTrue($voteQuestion->isVisibleTo($malePerson));

        // لكن لوحة خطوات المشي النسائية محجوبة تماماً عن الذكر ومتاحة للأنثى
        $this->assertTrue($voteQuestion->canViewWalkingLeaderboard($femalePerson));
        $this->assertFalse($voteQuestion->canViewWalkingLeaderboard($malePerson));
        $this->assertFalse($voteQuestion->canViewWalkingLeaderboard(null));
    }

    public function test_competition_visibility_excludes_empty_when_all_questions_hidden(): void
    {
        $femaleComp = new QuizCompetition([
            'is_active' => true,
        ]);

        $femaleQuestion = new QuizQuestion([
            'answer_type' => 'walking',
            'walking_settings' => [
                'target_gender' => 'female',
            ],
        ]);

        $femaleComp->setRelation('questions', collect([$femaleQuestion]));

        $femalePerson = new Person(['gender' => 'female']);
        $malePerson = new Person(['gender' => 'male']);

        // مسابقة النساء تظهر للإناث وتختفي عن الذكور والزوار
        $this->assertTrue($femaleComp->isVisibleTo($femalePerson));
        $this->assertFalse($femaleComp->isVisibleTo($malePerson));
        $this->assertFalse($femaleComp->isVisibleTo(null));

        $maleComp = new QuizCompetition([
            'is_active' => true,
        ]);
        $maleQuestion = new QuizQuestion([
            'answer_type' => 'walking',
            'walking_settings' => [
                'target_gender' => 'male',
            ],
        ]);
        $maleComp->setRelation('questions', collect([$maleQuestion]));

        // مسابقة الرجال ظاهرة للجميع (كاملة للرجال، وعرض فقط للنساء والزوار)
        $this->assertTrue($maleComp->isVisibleTo($malePerson));
        $this->assertTrue($maleComp->isVisibleTo($femalePerson));
        $this->assertTrue($maleComp->isVisibleTo(null));
    }

    public function test_linked_male_walking_prediction_is_visible_to_all_as_view_only(): void
    {
        $maleWalking = new QuizQuestion([
            'answer_type' => 'walking',
            'walking_settings' => [
                'target_gender' => 'male',
            ],
        ]);

        $voteQuestion = new QuizQuestion([
            'answer_type' => 'vote',
            'voter_eligibility' => [
                'allowed_gender' => 'all',
            ],
            'linked_walking_question_id' => 999,
        ]);
        $voteQuestion->setRelation('linkedWalkingQuestion', $maleWalking);

        $femalePerson = new Person(['gender' => 'female']);
        $malePerson = new Person(['gender' => 'male']);

        $this->assertTrue($voteQuestion->isVisibleTo($malePerson));
        $this->assertTrue($voteQuestion->isVisibleTo($femalePerson));
        $this->assertTrue($voteQuestion->isVisibleTo(null));

        // النساء والزوار عرض فقط في التوقعات المرتبطة بتحدي الرجال
        $this->assertFalse($voteQuestion->isViewOnlyFor($malePerson));
        $this->assertTrue($voteQuestion->isViewOnlyFor($femalePerson));
        $this->assertTrue($voteQuestion->isViewOnlyFor(null));
    }
}
